<?php
/**
 * Export data Absensi ke file Excel (.xlsx) asli.
 *
 * File ini dipanggil oleh tombol "XLSX" di absensi.php, yang mengirim
 * ulang semua parameter filter (GET) yang sedang aktif di halaman
 * (tanggal, status, jenis, bukti, cari, jabatan, unit, sort, dir),
 * kecuali "halaman" -> hasil export selalu mencakup SEMUA data yang
 * cocok dengan filter (tidak dipotong per-halaman).
 *
 * Tidak butuh library luar (PHPSpreadsheet dll). File .xlsx dibangun
 * langsung sebagai paket OOXML minimal memakai ekstensi ZipArchive
 * bawaan PHP.
 */

session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once 'koneksi.php';

/* ==========================================================
   1) Ambil & validasi filter — logikanya disamakan persis
      dengan absensi.php supaya hasil export == isi tabel.
   ========================================================== */

$jam_masuk_standar  = '08:00:00';
$jam_pulang_standar = '16:00:00';

if ($koneksi) {
    $q_batas = mysqli_query(
        $koneksi,
        "SELECT jam_batas FROM batas_waktu_hadir WHERE scope_type = 'semua' ORDER BY id DESC LIMIT 1"
    );
    if ($q_batas && ($r_batas = mysqli_fetch_assoc($q_batas))) {
        $jam_masuk_standar = $r_batas['jam_batas'];
    }
}

$status_filter  = $_GET['status'] ?? '';
$jenis_filter   = $_GET['jenis'] ?? '';
$bukti_filter   = $_GET['bukti'] ?? '';
$cari           = trim($_GET['cari'] ?? '');
$jabatan_filter = $_GET['jabatan'] ?? '';
$unit_filter    = $_GET['unit'] ?? '';

$date_from = $_GET['tanggal_mulai'] ?? date('Y-m-d', strtotime('-6 days'));
$date_to   = $_GET['tanggal_selesai'] ?? date('Y-m-d');

$kolom_sortir_valid = ['tanggal', 'nama', 'masuk', 'keluar', 'status'];
$sort_by  = $_GET['sort'] ?? 'tanggal';
$sort_by  = in_array($sort_by, $kolom_sortir_valid, true) ? $sort_by : 'tanggal';
$sort_dir = (($_GET['dir'] ?? 'desc') === 'asc') ? 'asc' : 'desc';

function tanggal_valid_export($tanggal)
{
    if (!$tanggal) return false;
    $d = DateTime::createFromFormat('Y-m-d', $tanggal);
    return $d && $d->format('Y-m-d') === $tanggal;
}

if (!tanggal_valid_export($date_from)) $date_from = date('Y-m-d', strtotime('-6 days'));
if (!tanggal_valid_export($date_to))   $date_to = date('Y-m-d');
if ($date_from > $date_to) {
    [$date_from, $date_to] = [$date_to, $date_from];
}

function tentukan_status_export($absensi, $masuk, $keluar, $jam_masuk_standar, $jam_pulang_standar)
{
    switch ($absensi) {
        case 'H':
            if (!empty($masuk) && $masuk > $jam_masuk_standar) {
                return ['key' => 'terlambat', 'label' => 'Terlambat Masuk'];
            } elseif (!empty($keluar) && $keluar < $jam_pulang_standar) {
                return ['key' => 'pulang_cepat', 'label' => 'Pulang Sebelum Waktunya'];
            } else {
                return ['key' => 'tepat_waktu', 'label' => 'Tepat Waktu'];
            }
        case 'I': return ['key' => 'izin', 'label' => 'Izin'];
        case 'S': return ['key' => 'sakit', 'label' => 'Sakit'];
        case 'C': return ['key' => 'cuti', 'label' => 'Cuti'];
        case 'OFF': return ['key' => 'off', 'label' => 'Libur'];
        case 'TK':
        default: return ['key' => 'tidak_absen', 'label' => 'Tidak Absen'];
    }
}

/* ==========================================================
   2) Ambil data (SEMUA baris sesuai filter, tanpa pagination)
   ========================================================== */

$data_absen = [];

if ($koneksi) {
    $where = [];

    $date_from_esc = mysqli_real_escape_string($koneksi, $date_from);
    $date_to_esc   = mysqli_real_escape_string($koneksi, $date_to);
    $where[] = "DATE(ab.tanggal) BETWEEN '$date_from_esc' AND '$date_to_esc'";

    if ($cari !== '') {
        $cari_esc = mysqli_real_escape_string($koneksi, $cari);
        $where[] = "(k.nama LIKE '%$cari_esc%' OR k.nik LIKE '%$cari_esc%' OR ab.ket LIKE '%$cari_esc%')";
    }

    if ($jabatan_filter !== '') {
        $jabatan_esc = mysqli_real_escape_string($koneksi, $jabatan_filter);
        $where[] = "ab.id_jabatan = '$jabatan_esc'";
    }

    if ($unit_filter !== '') {
        $unit_esc = mysqli_real_escape_string($koneksi, $unit_filter);
        $where[] = "ab.id_unit = '$unit_esc'";
    }

    if ($jenis_filter === 'masuk') {
        $where[] = "ab.masuk IS NOT NULL AND ab.masuk <> ''";
    } elseif ($jenis_filter === 'keluar') {
        $where[] = "ab.keluar IS NOT NULL AND ab.keluar <> '' AND (ab.masuk IS NULL OR ab.masuk = '')";
    }

    if ($bukti_filter === 'foto') {
        $where[] = "ab.foto_bukti IS NOT NULL AND ab.foto_bukti <> ''";
    } elseif ($bukti_filter === 'koordinat') {
        $where[] = "ab.latitude IS NOT NULL AND ab.latitude <> '' AND ab.longitude IS NOT NULL AND ab.longitude <> ''";
    } elseif ($bukti_filter === 'lengkap') {
        $where[] = "ab.foto_bukti IS NOT NULL AND ab.foto_bukti <> ''
                    AND ab.latitude IS NOT NULL AND ab.latitude <> ''
                    AND ab.longitude IS NOT NULL AND ab.longitude <> ''";
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where);

    $query_absen = "SELECT
                        ab.id_absensi AS id, ab.tanggal, ab.masuk, ab.keluar, ab.absensi, ab.ket,
                        ab.latitude, ab.longitude,
                        k.nik, k.nama,
                        j.nm_jabatan AS jabatan,
                        u.nm_unit AS unit
                    FROM absensi ab
                    JOIN karyawan k ON k.nik = ab.nik
                    LEFT JOIN jabatan j ON j.id_jabatan = ab.id_jabatan
                    LEFT JOIN unit u ON u.id_unit = ab.id_unit
                    $where_sql
                    ORDER BY ab.tanggal DESC, k.nama ASC";

    $result_absen = mysqli_query($koneksi, $query_absen);

    if ($result_absen) {
        while ($row = mysqli_fetch_assoc($result_absen)) {
            $status = tentukan_status_export($row['absensi'], $row['masuk'], $row['keluar'], $jam_masuk_standar, $jam_pulang_standar);
            $row['status'] = $status;

            if ($status_filter !== '' && $status['key'] !== $status_filter) continue;

            $data_absen[] = $row;
        }
    }
}

usort($data_absen, function ($a, $b) use ($sort_by, $sort_dir) {
    switch ($sort_by) {
        case 'nama':
            $cmp = strcasecmp($a['nama'], $b['nama']);
            break;
        case 'masuk':
            $cmp = strcmp($a['masuk'] ?? '', $b['masuk'] ?? '');
            break;
        case 'keluar':
            $cmp = strcmp($a['keluar'] ?? '', $b['keluar'] ?? '');
            break;
        case 'status':
            $cmp = strcasecmp($a['status']['label'], $b['status']['label']);
            break;
        case 'tanggal':
        default:
            $cmp = strcmp($a['tanggal'], $b['tanggal']);
            if ($cmp === 0) $cmp = strcasecmp($a['nama'], $b['nama']);
            break;
    }
    return $sort_dir === 'asc' ? $cmp : -$cmp;
});

/* ==========================================================
   3) Susun baris-baris untuk sheet Excel
   ========================================================== */

$header = ['No', 'NIK', 'Nama Pegawai', 'Jabatan', 'Unit', 'Tanggal', 'Waktu Masuk', 'Waktu Keluar', 'Status', 'Keterangan', 'Latitude', 'Longitude'];

$rows = [];
$no = 1;
foreach ($data_absen as $row) {
    $rows[] = [
        $no++,
        $row['nik'],
        $row['nama'],
        $row['jabatan'] ?? '-',
        $row['unit'] ?? '-',
        date('Y-m-d', strtotime($row['tanggal'])),
        $row['masuk'] ?: '-',
        $row['keluar'] ?: '-',
        $row['status']['label'],
        $row['ket'] ?: '-',
        $row['latitude'] ?: '-',
        $row['longitude'] ?: '-',
    ];
}

$nama_file = 'Absensi_' . $date_from . '_sd_' . $date_to . '.xlsx';

/* ==========================================================
   4) Generator .xlsx murni (tanpa library luar) via ZipArchive
   ========================================================== */

function xlsx_col_letter($index)
{
    $letter = '';
    $index++;
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letter = chr(65 + $mod) . $letter;
        $index = intdiv($index - $mod, 26);
    }
    return $letter;
}

function xlsx_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function xlsx_build_sheet_xml(array $header, array $rows)
{
    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

    $col_count = count($header);
    $xml .= '<cols>';
    for ($c = 0; $c < $col_count; $c++) {
        $width = $c === 2 ? 24 : ($c === 3 || $c === 4 ? 18 : ($c === 9 ? 22 : 14));
        $xml .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $width . '" customWidth="1"/>';
    }
    $xml .= '</cols>';

    $xml .= '<sheetData>';

    /* Baris header (bold, style s="1") */
    $xml .= '<row r="1">';
    foreach ($header as $i => $val) {
        $ref = xlsx_col_letter($i) . '1';
        $xml .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t xml:space="preserve">' . xlsx_escape($val) . '</t></is></c>';
    }
    $xml .= '</row>';

    /* Baris data */
    $r = 2;
    foreach ($rows as $data_row) {
        $xml .= '<row r="' . $r . '">';
        foreach ($data_row as $i => $val) {
            $ref = xlsx_col_letter($i) . $r;
            if ($i === 0 && is_numeric($val)) {
                $xml .= '<c r="' . $ref . '" t="n"><v>' . (int) $val . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . xlsx_escape($val) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
        $r++;
    }

    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function xlsx_content_types_xml()
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';
}

function xlsx_rels_root_xml()
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
}

function xlsx_workbook_xml($sheet_name)
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . xlsx_escape($sheet_name) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';
}

function xlsx_workbook_rels_xml()
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
}

function xlsx_styles_xml()
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2">'
        . '<font><sz val="10"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="2">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF2F7D3C"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

/* Bangun file .xlsx di folder temp lalu kirim ke browser. */
function xlsx_kirim_download(array $header, array $rows, $nama_file, $nama_sheet = 'Sheet1')
{
    if (!class_exists('ZipArchive')) {
        // Fallback: kalau ekstensi zip tidak tersedia di server, tetap
        // kirim data dalam format CSV (bisa dibuka Excel) supaya tombol
        // export tidak gagal total.
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . str_replace('.xlsx', '.csv', $nama_file) . '"');
        echo "\xEF\xBB\xBF"; // BOM biar Excel baca UTF-8 dengan benar
        $out = fopen('php://output', 'w');
        fputcsv($out, $header, ';');
        foreach ($rows as $r) {
            fputcsv($out, $r, ';');
        }
        fclose($out);
        exit();
    }

    $tmp_file = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    $zip->open($tmp_file, ZipArchive::OVERWRITE);

    $zip->addEmptyDir('_rels');
    $zip->addEmptyDir('xl');
    $zip->addEmptyDir('xl/_rels');
    $zip->addEmptyDir('xl/worksheets');

    $zip->addFromString('[Content_Types].xml', xlsx_content_types_xml());
    $zip->addFromString('_rels/.rels', xlsx_rels_root_xml());
    $zip->addFromString('xl/workbook.xml', xlsx_workbook_xml($nama_sheet));
    $zip->addFromString('xl/_rels/workbook.xml.rels', xlsx_workbook_rels_xml());
    $zip->addFromString('xl/styles.xml', xlsx_styles_xml());
    $zip->addFromString('xl/worksheets/sheet1.xml', xlsx_build_sheet_xml($header, $rows));

    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nama_file . '"');
    header('Content-Length: ' . filesize($tmp_file));
    header('Cache-Control: max-age=0');

    readfile($tmp_file);
    unlink($tmp_file);
    exit();
}

xlsx_kirim_download($header, $rows, $nama_file, 'Absensi');
