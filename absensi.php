<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();

$menu_aktif = $_GET['menu'] ?? 'absensi';
require_once 'koneksi.php';

if ($koneksi) {
    mysqli_query(
        $koneksi,
        "UPDATE absensi SET status_approval = 'approved'
         WHERE status_approval = 'pending' AND diluar_radius = 0"
    );
}

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
$per_halaman    = (int) ($_GET['per_halaman'] ?? 10);
$per_halaman    = in_array($per_halaman, [5, 10, 25, 50, 100], true) ? $per_halaman : 10;
$halaman        = max(1, (int) ($_GET['halaman'] ?? 1));

$date_from = $_GET['tanggal_mulai'] ?? date('Y-m-d', strtotime('-6 days'));
$date_to   = $_GET['tanggal_selesai'] ?? date('Y-m-d');

$aksi_status = $_GET['aksi_status'] ?? '';
$aksi_pesan  = isset($_GET['aksi_pesan']) ? htmlspecialchars($_GET['aksi_pesan']) : '';

/* Sortir tabel absensi lewat klik header kolom. */
$kolom_sortir_valid = ['tanggal', 'nama', 'masuk', 'keluar', 'status'];
$sort_by  = $_GET['sort'] ?? 'tanggal';
$sort_by  = in_array($sort_by, $kolom_sortir_valid, true) ? $sort_by : 'tanggal';
$sort_dir = (($_GET['dir'] ?? 'desc') === 'asc') ? 'asc' : 'desc';

function tanggal_valid($tanggal)
{
    if (!$tanggal) return false;
    $d = DateTime::createFromFormat('Y-m-d', $tanggal);
    return $d && $d->format('Y-m-d') === $tanggal;
}

if (!tanggal_valid($date_from)) $date_from = date('Y-m-d', strtotime('-6 days'));
if (!tanggal_valid($date_to))   $date_to = date('Y-m-d');
if ($date_from > $date_to) {
    [$date_from, $date_to] = [$date_to, $date_from];
}

$daftar_jabatan = [];
if ($koneksi) {
    $q_jabatan = mysqli_query($koneksi, "SELECT id_jabatan, nm_jabatan FROM jabatan ORDER BY nm_jabatan ASC");
    if ($q_jabatan) {
        while ($r = mysqli_fetch_assoc($q_jabatan)) $daftar_jabatan[] = $r;
    }
}

$daftar_karyawan = [];
if ($koneksi) {
    $q_kar = mysqli_query(
        $koneksi,
        "SELECT nik, nama FROM karyawan WHERE status_aktif = 'Aktif' ORDER BY nama ASC"
    );
    if ($q_kar) {
        while ($r = mysqli_fetch_assoc($q_kar)) $daftar_karyawan[] = $r;
    }
}

$daftar_unit = [];
if ($koneksi) {
    $q_unit = mysqli_query($koneksi, "SELECT id_unit, nm_unit FROM unit ORDER BY nm_unit ASC");
    if ($q_unit) {
        while ($r = mysqli_fetch_assoc($q_unit)) $daftar_unit[] = $r;
    }
}

function tentukan_status($absensi, $masuk, $keluar, $jam_masuk_standar, $jam_pulang_standar)
{
    switch ($absensi) {
        case 'H':
            if (!empty($masuk) && $masuk > $jam_masuk_standar) {
                return ['key' => 'terlambat', 'label' => 'Terlambat Masuk', 'class' => 'telat'];
            } elseif (!empty($keluar) && $keluar < $jam_pulang_standar) {
                return ['key' => 'pulang_cepat', 'label' => 'Pulang Sebelum Waktunya', 'class' => 'pulang'];
            } else {
                return ['key' => 'tepat_waktu', 'label' => 'Tepat Waktu', 'class' => 'tepat'];
            }
        case 'I': return ['key' => 'izin', 'label' => 'Izin', 'class' => 'izin'];
        case 'S': return ['key' => 'sakit', 'label' => 'Sakit', 'class' => 'sakit'];
        case 'C': return ['key' => 'cuti', 'label' => 'Cuti', 'class' => 'izin'];
        case 'OFF': return ['key' => 'off', 'label' => 'Libur', 'class' => 'off'];
        case 'TK':
        default: return ['key' => 'tidak_absen', 'label' => 'Tidak Absen', 'class' => 'tidak'];
    }
}

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
                        ab.foto_bukti, ab.latitude, ab.longitude,
                        ab.status_approval, ab.catatan_approval, ab.diluar_radius,
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
            $status = tentukan_status($row['absensi'], $row['masuk'], $row['keluar'], $jam_masuk_standar, $jam_pulang_standar);
            $row['status'] = $status;

            /* Status dihitung di PHP karena bergantung pada jam standar. */
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

$total_data    = count($data_absen);
$total_halaman = max(1, (int) ceil($total_data / $per_halaman));
$halaman       = min($halaman, $total_halaman);
$offset        = ($halaman - 1) * $per_halaman;
$data_tampil   = array_slice($data_absen, $offset, $per_halaman);

/* Ringkasan hasil filter. */
$statistik = [
    'total' => $total_data, 'tepat_waktu' => 0, 'terlambat' => 0,
    'pulang_cepat' => 0, 'izin' => 0, 'sakit' => 0, 'cuti' => 0,
    'off' => 0, 'tidak_absen' => 0
];

foreach ($data_absen as $row_stat) {
    $key = $row_stat['status']['key'];
    if (isset($statistik[$key])) $statistik[$key]++;
}

function build_query($override = [])
{
    $params = array_merge($_GET, $override);
    return htmlspecialchars('?' . http_build_query($params));
}

function sort_link($kolom, $label)
{
    global $sort_by, $sort_dir;

    $default_dir = in_array($kolom, ['tanggal', 'masuk', 'keluar'], true) ? 'desc' : 'asc';
    $is_aktif    = $sort_by === $kolom;
    $dir_baru    = $is_aktif ? ($sort_dir === 'asc' ? 'desc' : 'asc') : $default_dir;

    $icon = 'bi-arrow-down-up';
    if ($is_aktif) {
        $icon = $sort_dir === 'asc' ? 'bi-sort-alpha-down' : 'bi-sort-alpha-up';
        if (in_array($kolom, ['tanggal', 'masuk', 'keluar'], true)) {
            $icon = $sort_dir === 'asc' ? 'bi-sort-up' : 'bi-sort-down';
        }
    }

    $url = build_query(['sort' => $kolom, 'dir' => $dir_baru, 'halaman' => 1]);

    echo '<a href="' . $url . '" class="th-sort' . ($is_aktif ? ' active' : '') . '">'
        . htmlspecialchars($label)
        . ' <i class="bi ' . $icon . '"></i>'
        . '</a>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi - Berkah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php if ($aksi_pesan): ?>
        <div class="app-shell-alert-wrap" style="max-width:1200px;margin:0.5rem auto 0;">
            <div class="alert <?php echo $aksi_status === 'sukses' ? 'alert-success' : 'alert-danger'; ?> py-2 px-3 mb-2">
                <i class="bi <?php echo $aksi_status === 'sukses' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> me-1"></i>
                <?php echo $aksi_pesan; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="app-shell">

        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/includes/mobile-topbar.php'; ?>
            <div class="dashboard-grid">

                <div class="custom-card">
                    <h6 class="card-title-custom">Presensi Tepat Waktu</h6>

                    <table class="table-ranking">
                        <thead>
                            <tr>
                                <th style="width: 15%;">No</th>
                                <th>Nama</th>
                                <th style="text-align: right;">Tepat Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($koneksi) {
                                $jam_masuk_standar_esc = mysqli_real_escape_string($koneksi, $jam_masuk_standar);
                                $date_from_esc_top = mysqli_real_escape_string($koneksi, $date_from);
                                $date_to_esc_top = mysqli_real_escape_string($koneksi, $date_to);
                                $q_top = mysqli_query($koneksi, "
                                    SELECT k.nama, COUNT(ab.id_absensi) as total_tepat
                                    FROM absensi ab
                                    JOIN karyawan k ON k.nik = ab.nik
                                    WHERE ab.absensi = 'H' 
                                      AND ab.masuk IS NOT NULL
                                      AND ab.masuk <= '$jam_masuk_standar_esc'
                                      AND DATE(ab.tanggal) BETWEEN '$date_from_esc_top' AND '$date_to_esc_top'
                                    GROUP BY ab.nik
                                    ORDER BY total_tepat DESC
                                    LIMIT 5
                                ");
                                $no_top = 1;
                                if ($q_top && mysqli_num_rows($q_top) > 0) {
                                    while ($top = mysqli_fetch_assoc($q_top)) {
                                        echo "<tr>
                                                <td>{$no_top}</td>
                                                <td>" . htmlspecialchars($top['nama']) . "</td>
                                                <td style='text-align: right;'>{$top['total_tepat']}</td>
                                              </tr>";
                                        $no_top++;
                                    }
                                } else {
                                    echo "<tr><td colspan='3' class='text-center text-muted py-3'>Belum ada data</td></tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>

                    <p class="text-muted mt-4 mb-0" style="font-size: 0.72rem; line-height: 1.4;">
                        -
                    </p>
                    <form method="GET" action="absensi.php" class="filter-panel mb-3">
                        <input type="hidden" name="menu" value="absensi">

                        <div class="filter-header">
                            <div>
                                <strong><i class="bi bi-funnel-fill me-1"></i> Filter Data</strong>
                                <small class="d-block text-muted">Default: 7 hari terakhir.</small>
                            </div>
                            
                            <div class="quick-filter-wrapper">
                                <span class="quick-label">Cepat:</span>
                                <div class="quick-filter">
                                    <button type="button" class="quick-btn" onclick="setRentang(6)">7 Hari</button>
                                    <button type="button" class="quick-btn" onclick="setRentang(29)">30 Hari</button>
                                    <button type="button" class="quick-btn" onclick="setBulanIni()">Bulan Ini</button>
                                    <button type="button" class="quick-btn" onclick="setRentang(0)">Hari Ini</button>
                                </div>
                            </div>
                        </div>

                        <div class="filter-grid">
                            <div>
                                <label class="filter-label">Tanggal Mulai</label>
                                <input type="date" name="tanggal_mulai" id="tanggal_mulai"
                                       class="form-control form-control-sm"
                                       value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>

                            <div>
                                <label class="filter-label">Tanggal Selesai</label>
                                <input type="date" name="tanggal_selesai" id="tanggal_selesai"
                                       class="form-control form-control-sm"
                                       value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>

                            <div>
                                <label class="filter-label">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">Semua Status</option>
                                    <option value="tepat_waktu" <?php echo $status_filter === 'tepat_waktu' ? 'selected' : ''; ?>>Tepat Waktu</option>
                                    <option value="terlambat" <?php echo $status_filter === 'terlambat' ? 'selected' : ''; ?>>Terlambat Masuk</option>
                                    <option value="pulang_cepat" <?php echo $status_filter === 'pulang_cepat' ? 'selected' : ''; ?>>Pulang Sebelum Waktunya</option>
                                    <option value="izin" <?php echo $status_filter === 'izin' ? 'selected' : ''; ?>>Izin</option>
                                    <option value="sakit" <?php echo $status_filter === 'sakit' ? 'selected' : ''; ?>>Sakit</option>
                                    <option value="cuti" <?php echo $status_filter === 'cuti' ? 'selected' : ''; ?>>Cuti</option>
                                    <option value="off" <?php echo $status_filter === 'off' ? 'selected' : ''; ?>>Libur</option>
                                    <option value="tidak_absen" <?php echo $status_filter === 'tidak_absen' ? 'selected' : ''; ?>>Tidak Absen</option>
                                </select>
                            </div>

                            <div>
                                <label class="filter-label">Jenis Presensi</label>
                                <select name="jenis" class="form-select form-select-sm">
                                    <option value="">Semua Jenis</option>
                                    <option value="masuk" <?php echo $jenis_filter === 'masuk' ? 'selected' : ''; ?>>Check In</option>
                                    <option value="keluar" <?php echo $jenis_filter === 'keluar' ? 'selected' : ''; ?>>Check Out</option>
                                </select>
                            </div>

                            <div>
                                <label class="filter-label">Jabatan</label>
                                <select name="jabatan" class="form-select form-select-sm">
                                    <option value="">Semua Jabatan</option>
                                    <?php foreach ($daftar_jabatan as $jab): ?>
                                        <option value="<?php echo htmlspecialchars($jab['id_jabatan']); ?>"
                                            <?php echo (string)$jabatan_filter === (string)$jab['id_jabatan'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($jab['nm_jabatan']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="filter-label">Unit</label>
                                <select name="unit" class="form-select form-select-sm">
                                    <option value="">Semua Unit</option>
                                    <?php foreach ($daftar_unit as $unit_item): ?>
                                        <option value="<?php echo htmlspecialchars($unit_item['id_unit']); ?>"
                                            <?php echo (string)$unit_filter === (string)$unit_item['id_unit'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($unit_item['nm_unit']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="filter-label">Bukti</label>
                                <select name="bukti" class="form-select form-select-sm">
                                    <option value="">Semua Bukti</option>
                                    <option value="foto" <?php echo $bukti_filter === 'foto' ? 'selected' : ''; ?>>Ada Foto</option>
                                    <option value="koordinat" <?php echo $bukti_filter === 'koordinat' ? 'selected' : ''; ?>>Ada Koordinat</option>
                                    <option value="lengkap" <?php echo $bukti_filter === 'lengkap' ? 'selected' : ''; ?>>Foto + Koordinat</option>
                                </select>
                            </div>

                            <div>
                                <label class="filter-label">Cari</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                    <input type="text" name="cari" class="form-control"
                                           value="<?php echo htmlspecialchars($cari); ?>"
                                           placeholder="Nama, NIK, atau keterangan...">
                                </div>
                            </div>

                            <div>
                                <label class="filter-label">Per Halaman</label>
                                <select name="per_halaman" class="form-select form-select-sm">
                                    <?php foreach ([5, 10, 25, 50, 100] as $jumlah): ?>
                                        <option value="<?php echo $jumlah; ?>" <?php echo $per_halaman === $jumlah ? 'selected' : ''; ?>>
                                            <?php echo $jumlah; ?> data
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-success btn-sm px-3">
                                <i class="bi bi-search me-1"></i> Terapkan Filter
                            </button>
                            <a href="absensi.php?menu=absensi" class="btn btn-light border btn-sm px-3">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <div class="custom-card">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="card-title-custom mb-1">Presensi Terbaru</h6>
                            <small class="text-muted">
                                <?php echo date('d/m/Y', strtotime($date_from)); ?> -
                                <?php echo date('d/m/Y', strtotime($date_to)); ?>
                            </small>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-success btn-sm" onclick="bukaModalTambahAbsensi()">
                                <i class="bi bi-plus-lg"></i> Tambah Absensi
                            </button>

                            <a href="export_excel.php<?php
                                $export_params = $_GET;
                                $export_params['menu'] = 'absensi';
                                unset($export_params['halaman']);
                                echo htmlspecialchars('?' . http_build_query($export_params));
                            ?>" class="btn-xlsx">
                                <i class="bi bi-file-earmark-excel"></i> XLSX
                            </a>
                        </div>
                    </div>

                    

                    <div class="filter-summary mb-3">
                        <div class="summary-item"><span class="summary-icon"><i class="bi bi-list-check"></i></span><div><small>Total</small><strong><?php echo number_format($statistik['total']); ?></strong></div></div>
                        <div class="summary-item"><span class="summary-icon tepat-icon"><i class="bi bi-check-circle-fill"></i></span><div><small>Tepat Waktu</small><strong><?php echo number_format($statistik['tepat_waktu']); ?></strong></div></div>
                        <div class="summary-item"><span class="summary-icon telat-icon"><i class="bi bi-clock-fill"></i></span><div><small>Terlambat</small><strong><?php echo number_format($statistik['terlambat']); ?></strong></div></div>
                        <div class="summary-item"><span class="summary-icon izin-icon"><i class="bi bi-info-circle-fill"></i></span><div><small>Izin</small><strong><?php echo number_format($statistik['izin']); ?></strong></div></div>
                        <div class="summary-item"><span class="summary-icon sakit-icon"><i class="bi bi-heart-pulse-fill"></i></span><div><small>Sakit</small><strong><?php echo number_format($statistik['sakit']); ?></strong></div></div>
                        <div class="summary-item"><span class="summary-icon tidak-icon"><i class="bi bi-x-circle-fill"></i></span><div><small>Tidak Absen</small><strong><?php echo number_format($statistik['tidak_absen']); ?></strong></div></div>
                    </div>

                    <div class="table-responsive">
                        <table class="tabel-absen">
                            <thead>
                                <tr>
                                    <th style="width: 5%;">No</th>
                                    <th><?php sort_link('nama', 'Nama Pegawai'); ?></th>
                                    <th><?php sort_link('tanggal', 'Tanggal'); ?></th>
                                    <th><?php sort_link('masuk', 'Waktu Masuk'); ?></th>
                                    <th><?php sort_link('keluar', 'Waktu Keluar'); ?></th>
                                    <th><?php sort_link('status', 'Status'); ?></th>
                                    <th>Lokasi</th>
                                    <th style="text-align: center;">Foto</th>
                                    <th style="text-align: center;">Approval</th>
                                    <th style="text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($data_tampil) === 0): ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-4 text-muted">
                                            Tidak ada data absensi untuk filter ini.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $no_row = $offset + 1;
                                    foreach ($data_tampil as $row): 
                                        $tgl_tampil = date('Y-m-d', strtotime($row['tanggal']));
                                        $jenis_absen = !empty($row['keluar']) && empty($row['masuk']) ? 'Check Out' : 'Check In';
                                    ?>
                                        <tr>
                                            <td><?php echo $no_row++; ?></td>
                                            <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                            <td><?php echo $tgl_tampil; ?></td>
                                            <td><?php echo $row['masuk']; ?></td>
                                            <td><?php echo $row['keluar']; ?></td>
                                            <td>
                                                <span class="badge-status <?php echo $row['status']['class']; ?>">
                                                    <?php echo $row['status']['label']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($row['latitude']) && !empty($row['longitude'])): ?>
                                                    <a id="modalFotoMaps" 
                                                    href="https://www.google.com/maps?q=<?php echo urlencode($row['latitude'] . ',' . $row['longitude']); ?>" 
                                                    target="_blank" 
                                                    rel="noopener" 
                                                    class="btn btn-sm btn-success"> 
                                                        <i class="bi bi-geo-alt-fill"></i> 
                                                        <span id="modalFotoKoordinat">Lat: <?php echo htmlspecialchars($row['latitude']); ?>, Lng: <?php echo htmlspecialchars($row['longitude']); ?></span> 
                                                    </a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if (!empty($row['foto_bukti'])): ?>
                                                    <?php
                                                        $foto_url = 'https://motion-hypnotize-tradition.ngrok-free.dev/api_presensi/' . $row['foto_bukti'];
                                                        $lat = $row['latitude'];
                                                        $lng = $row['longitude'];
                                                    ?>
                                                    <img src="<?php echo htmlspecialchars($foto_url); ?>"
                                                        alt="Foto Bukti"
                                                        class="foto-bukti-thumb"
                                                        style="width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid #e4ece4;"
                                                        data-foto="<?php echo htmlspecialchars($foto_url); ?>"
                                                        data-lat="<?php echo htmlspecialchars($lat ?? ''); ?>"
                                                        data-lng="<?php echo htmlspecialchars($lng ?? ''); ?>"
                                                        data-nama="<?php echo htmlspecialchars($row['nama']); ?>"
                                                        data-tanggal="<?php echo htmlspecialchars($tgl_tampil); ?>"
                                                        onclick="bukaModalFoto(this)">
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if ($row['status_approval'] === 'approved'): ?>
                                                    <span class="badge bg-success">Disetujui</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Menunggu</span>
                                                <?php endif; ?>
                                                <?php if (!empty($row['diluar_radius'])): ?>
                                                    <br>
                                                    <span class="badge bg-danger mt-1" title="Absen dilakukan di luar radius lokasi resmi">
                                                        <i class="bi bi-geo-alt-fill"></i> Luar Radius
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <div class="d-inline-flex gap-1">
                                                    <?php if ($row['status_approval'] !== 'approved'): ?>
                                                        <a href="absensi_approve.php?id=<?php echo (int) $row['id']; ?>"
                                                           class="btn-aksi" style="color:#198754;" title="Setujui"
                                                           onclick="return confirm('Setujui absensi ini?');">
                                                            <i class="bi bi-check-lg" style="font-size:0.8rem;"></i>
                                                        </a>
                                                        <button type="button" class="btn-aksi" style="color:#dc3545;" title="Tolak"
                                                                onclick="bukaModalTolak(<?php echo (int) $row['id']; ?>, '<?php echo htmlspecialchars($row['nama'], ENT_QUOTES); ?>')">
                                                            <i class="bi bi-x-lg" style="font-size:0.8rem;"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn-aksi edit" title="Edit"
                                                            data-id="<?php echo (int) $row['id']; ?>"
                                                            data-nik="<?php echo htmlspecialchars($row['nik'], ENT_QUOTES); ?>"
                                                            data-nama="<?php echo htmlspecialchars($row['nama'], ENT_QUOTES); ?>"
                                                            data-tanggal="<?php echo htmlspecialchars($tgl_tampil, ENT_QUOTES); ?>"
                                                            data-jenis="<?php echo htmlspecialchars($row['absensi'], ENT_QUOTES); ?>"
                                                            data-masuk="<?php echo htmlspecialchars(substr($row['masuk'] ?? '', 0, 5), ENT_QUOTES); ?>"
                                                            data-keluar="<?php echo htmlspecialchars(substr($row['keluar'] ?? '', 0, 5), ENT_QUOTES); ?>"
                                                            data-ket="<?php echo htmlspecialchars($row['ket'] ?? '', ENT_QUOTES); ?>"
                                                            onclick="bukaModalEditAbsensi(this)">
                                                        <i class="bi bi-pencil-fill" style="font-size:0.7rem;"></i>
                                                    </button>
                                                    <button type="button" class="btn-aksi hapus" title="Hapus"
                                                            onclick="hapusAbsensi(<?php echo (int) $row['id']; ?>, '<?php echo htmlspecialchars($row['nama'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tgl_tampil, ENT_QUOTES); ?>')">
                                                        <i class="bi bi-trash-fill" style="font-size:0.7rem;"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-footer">
                        <div>
                            Showing <?php echo $total_data > 0 ? ($offset + 1) : 0; ?> to <?php echo min($offset + $per_halaman, $total_data); ?> of <?php echo $total_data; ?> entries
                        </div>

                        <?php if ($total_halaman > 1): ?>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo $halaman <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo build_query(['halaman' => $halaman - 1]); ?>">Previous</a>
                                </li>
                                <?php for ($p = 1; $p <= $total_halaman; $p++): ?>
                                    <li class="page-item <?php echo $p === $halaman ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo build_query(['halaman' => $p]); ?>"><?php echo $p; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $halaman >= $total_halaman ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo build_query(['halaman' => $halaman + 1]); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </main>

    </div>
    <div class="modal fade" id="modalFoto" tabindex="-1" aria-labelledby="modalFotoLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title" id="modalFotoLabel">Foto Bukti Presensi</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-0">
            <div class="foto-zoom-wrap" id="fotoZoomWrap">
                <img id="modalFotoImg" src="" alt="Foto Bukti" draggable="false">
            </div>
          </div>
          <div class="modal-footer justify-content-between flex-wrap gap-2">
            <div class="zoom-controls d-flex gap-1">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="zoomFoto(-0.25)" title="Perkecil">
                    <i class="bi bi-dash-lg"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="zoomFoto(0.25)" title="Perbesar">
                    <i class="bi bi-plus-lg"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetZoomFoto()">
                    Reset
                </button>
            </div>
            <a id="modalFotoMaps" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-success">
                <i class="bi bi-geo-alt-fill"></i> <span id="modalFotoKoordinat">Lihat di Google Maps</span>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================== MODAL TAMBAH / EDIT ABSENSI ===================== -->
    <div class="modal fade" id="modalAbsensi" tabindex="-1" aria-labelledby="modalAbsensiLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form id="formAbsensi" novalidate>
            <div class="modal-header">
              <h6 class="modal-title" id="modalAbsensiLabel">Tambah Absensi Manual</h6>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">

                <div id="modalAbsensiAlert" class="alert alert-danger py-2 px-3 d-none"></div>

                <input type="hidden" name="id" id="inputAbsensiId" value="">

                <div class="mb-3">
                    <label class="form-label">Karyawan</label>
                    <select name="nik" id="inputAbsensiNik" class="form-select" required>
                        <option value="">-- Pilih Karyawan --</option>
                        <?php foreach ($daftar_karyawan as $kar): ?>
                            <option value="<?php echo htmlspecialchars($kar['nik']); ?>">
                                <?php echo htmlspecialchars($kar['nama']); ?> (<?php echo htmlspecialchars($kar['nik']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted d-none" id="hintNikTerkunci">
                        Karyawan tidak bisa diganti saat mengedit. Hapus data ini dan buat baru kalau salah pilih orang.
                    </small>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" id="inputAbsensiTanggal" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jenis Absensi</label>
                        <select name="absensi" id="inputAbsensiJenis" class="form-select" required onchange="toggleJamModal()">
                            <option value="H">Hadir</option>
                            <option value="I">Izin</option>
                            <option value="S">Sakit</option>
                            <option value="C">Cuti</option>
                            <option value="OFF">Libur</option>
                            <option value="TK">Tidak Ada Keterangan</option>
                        </select>
                    </div>
                </div>

                <div class="row" id="wrapJamModal">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jam Masuk</label>
                        <input type="time" name="masuk" id="inputAbsensiMasuk" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jam Keluar</label>
                        <input type="time" name="keluar" id="inputAbsensiKeluar" class="form-control">
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label">Keterangan</label>
                    <textarea name="ket" id="inputAbsensiKet" class="form-control" rows="3"
                              placeholder="Contoh: HP rusak, absen manual dicatat satpam, dll."></textarea>
                </div>

                <div class="alert alert-secondary py-2 px-3 mb-0" style="font-size:0.8rem;">
                    <i class="bi bi-info-circle me-1"></i>
                    Data manual otomatis berstatus <strong>Disetujui</strong> dan tidak punya foto/koordinat GPS.
                </div>

            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-success btn-sm" id="btnSimpanAbsensi">
                <i class="bi bi-check-lg me-1"></i> Simpan
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modalTolak" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST" action="absensi_reject.php">
            <div class="modal-header">
              <h6 class="modal-title">Tolak Absensi</h6>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="id" id="tolakId">
              <p class="mb-2">Tolak absensi milik <strong id="tolakNama"></strong>?</p>
              <label class="form-label">Alasan (opsional, akan dikirim ke karyawan)</label>
              <textarea name="catatan" class="form-control" rows="3" placeholder="Contoh: foto tidak jelas, lokasi tidak sesuai, dst."></textarea>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-danger btn-sm">
                <i class="bi bi-x-lg me-1"></i> Tolak & Minta Absen Ulang
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Form tersembunyi untuk hapus absensi. Pakai POST supaya tidak seperti
         link GET biasa (yang gampang ke-trigger prefetch/crawler & rawan CSRF). -->
    <form method="POST" action="absensi_hapus.php" id="formHapusAbsensi" style="display:none;">
        <input type="hidden" name="id" id="hapusAbsensiId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        function formatTanggalJS(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        }

        function setRentang(jumlahHariSebelumnya) {
            const hariIni = new Date();
            const mulai = new Date(hariIni);
            mulai.setDate(hariIni.getDate() - jumlahHariSebelumnya);
            document.getElementById('tanggal_mulai').value = formatTanggalJS(mulai);
            document.getElementById('tanggal_selesai').value = formatTanggalJS(hariIni);
        }

        function setBulanIni() {
            const hariIni = new Date();
            const mulai = new Date(hariIni.getFullYear(), hariIni.getMonth(), 1);
            document.getElementById('tanggal_mulai').value = formatTanggalJS(mulai);
            document.getElementById('tanggal_selesai').value = formatTanggalJS(hariIni);
        }

        let fotoZoomLevel = 1;
        const MIN_ZOOM = 1;
        const MAX_ZOOM = 4;

        function bukaModalFoto(el) {
            const foto     = el.getAttribute('data-foto');
            const lat      = el.getAttribute('data-lat');
            const lng      = el.getAttribute('data-lng');
            const nama     = el.getAttribute('data-nama');
            const tanggal  = el.getAttribute('data-tanggal');

            document.getElementById('modalFotoImg').src = foto;
            document.getElementById('modalFotoLabel').textContent =
                'Foto Bukti - ' + nama + ' (' + tanggal + ')';

            const linkMaps = document.getElementById('modalFotoMaps');
            const labelKoordinat = document.getElementById('modalFotoKoordinat');

            if (lat && lng) {
                linkMaps.href = 'https://www.google.com/maps?q=' + encodeURIComponent(lat) + ',' + encodeURIComponent(lng);
                linkMaps.classList.remove('disabled');
                labelKoordinat.textContent = lat + ', ' + lng;
            } else {
                linkMaps.href = '#';
                linkMaps.classList.add('disabled');
                labelKoordinat.textContent = 'Koordinat tidak tersedia';
            }

            resetZoomFoto();

            const modalEl = document.getElementById('modalFoto');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }

        function bukaModalTolak(id, nama) {
            document.getElementById('tolakId').value = id;
            document.getElementById('tolakNama').textContent = nama;

            const modalEl = document.getElementById('modalTolak');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }

        function toggleJamModal() {
            const jenis = document.getElementById('inputAbsensiJenis').value;
            document.getElementById('wrapJamModal').classList.toggle('d-none', jenis !== 'H');
        }

        function tampilTanggalHariIni() {
            const d = new Date();
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const t = String(d.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + t;
        }

        function resetAlertModalAbsensi() {
            const alertBox = document.getElementById('modalAbsensiAlert');
            alertBox.classList.add('d-none');
            alertBox.textContent = '';
        }

        function bukaModalTambahAbsensi() {
            document.getElementById('formAbsensi').reset();
            document.getElementById('inputAbsensiId').value = '';
            document.getElementById('inputAbsensiNik').disabled = false;
            document.getElementById('hintNikTerkunci').classList.add('d-none');
            document.getElementById('inputAbsensiTanggal').value = tampilTanggalHariIni();
            document.getElementById('modalAbsensiLabel').textContent = 'Tambah Absensi Manual';
            resetAlertModalAbsensi();
            toggleJamModal();

            const modalEl = document.getElementById('modalAbsensi');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        function bukaModalEditAbsensi(el) {
            document.getElementById('formAbsensi').reset();
            document.getElementById('inputAbsensiId').value = el.dataset.id;

            const selectNik = document.getElementById('inputAbsensiNik');
            selectNik.value = el.dataset.nik;
            selectNik.disabled = true;
            document.getElementById('hintNikTerkunci').classList.remove('d-none');

            document.getElementById('inputAbsensiTanggal').value = el.dataset.tanggal;
            document.getElementById('inputAbsensiJenis').value = el.dataset.jenis;
            document.getElementById('inputAbsensiMasuk').value = el.dataset.masuk || '';
            document.getElementById('inputAbsensiKeluar').value = el.dataset.keluar || '';
            document.getElementById('inputAbsensiKet').value = el.dataset.ket || '';

            document.getElementById('modalAbsensiLabel').textContent = 'Edit Absensi - ' + el.dataset.nama;
            resetAlertModalAbsensi();
            toggleJamModal();

            const modalEl = document.getElementById('modalAbsensi');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        document.getElementById('formAbsensi').addEventListener('submit', function (e) {
            e.preventDefault();

            const btn = document.getElementById('btnSimpanAbsensi');
            const alertBox = document.getElementById('modalAbsensiAlert');
            resetAlertModalAbsensi();

            const teksAsli = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

            const formData = new FormData(this);
            // select nik yang di-disable saat mode edit tidak ikut terkirim FormData,
            // jadi nilainya ditambahkan manual di sini.
            const selectNik = document.getElementById('inputAbsensiNik');
            if (selectNik.disabled) {
                formData.set('nik', selectNik.value);
            }

            const idEdit = document.getElementById('inputAbsensiId').value;
            const url = 'absensi_tambah.php' + (idEdit ? ('?id=' + encodeURIComponent(idEdit)) : '');

            fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    const tujuan = new URL(window.location.href);
                    tujuan.searchParams.set('aksi_status', 'sukses');
                    tujuan.searchParams.set('aksi_pesan', data.message);
                    window.location.href = tujuan.toString();
                } else {
                    alertBox.textContent = data.message || 'Gagal menyimpan data.';
                    alertBox.classList.remove('d-none');
                    btn.disabled = false;
                    btn.innerHTML = teksAsli;
                }
            })
            .catch(function () {
                alertBox.textContent = 'Terjadi kesalahan jaringan. Coba lagi.';
                alertBox.classList.remove('d-none');
                btn.disabled = false;
                btn.innerHTML = teksAsli;
            });
        });

        function hapusAbsensi(id, nama, tanggal) {
            const ok = confirm(
                'Hapus data absensi milik ' + nama + ' tanggal ' + tanggal + '?\n' +
                'Data yang sudah dihapus tidak bisa dikembalikan.'
            );
            if (!ok) return;

            document.getElementById('hapusAbsensiId').value = id;
            document.getElementById('formHapusAbsensi').submit();
        }

        function zoomFoto(delta) {
            fotoZoomLevel = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, fotoZoomLevel + delta));
            document.getElementById('modalFotoImg').style.transform = 'scale(' + fotoZoomLevel + ')';
        }

        function resetZoomFoto() {
            fotoZoomLevel = 1;
            document.getElementById('modalFotoImg').style.transform = 'scale(1)';
        }

        document.addEventListener('DOMContentLoaded', function () {
            const wrap = document.getElementById('fotoZoomWrap');
            if (wrap) {
                // Zoom pakai scroll wheel / trackpad
                wrap.addEventListener('wheel', function (e) {
                    e.preventDefault();
                    zoomFoto(e.deltaY < 0 ? 0.25 : -0.25);
                }, { passive: false });
            }

            // Reset zoom setiap modal ditutup, biar foto berikutnya mulai dari normal
            const modalEl = document.getElementById('modalFoto');
            if (modalEl) {
                modalEl.addEventListener('hidden.bs.modal', resetZoomFoto);
            }
        });
    </script>

    
</body>
</html>
