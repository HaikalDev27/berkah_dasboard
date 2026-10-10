<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_admin();

$menu_aktif = $_GET['menu'] ?? 'setting';
require_once 'koneksi.php';

$pesan = isset($_GET['pesan']) ? htmlspecialchars($_GET['pesan']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'simpan_batas_waktu') {
    $id_batas  = (int) ($_POST['id_batas'] ?? 0);
    $jam_batas = trim($_POST['jam_batas'] ?? '');

    if ($koneksi && $id_batas > 0 && $jam_batas !== '') {
        $jam_esc = mysqli_real_escape_string($koneksi, $jam_batas);
        $query_update = "UPDATE batas_waktu_hadir SET jam_batas = '$jam_esc' WHERE id = $id_batas";

        if (mysqli_query($koneksi, $query_update)) {
            header('Location: setting.php?menu=setting&status=sukses&pesan=' . urlencode('Batas waktu hadir (semua karyawan) berhasil diperbarui.'));
            exit;
        } else {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Gagal memperbarui batas waktu: ' . mysqli_error($koneksi)));
            exit;
        }
    } else {
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Data batas waktu tidak lengkap.'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah_batas_unit') {
    $id_unit   = trim($_POST['id_unit'] ?? '');
    $jam_batas = trim($_POST['jam_batas'] ?? '');

    if ($koneksi && $id_unit !== '' && $jam_batas !== '') {
         $cek_stmt = mysqli_prepare($koneksi, "SELECT id FROM batas_waktu_hadir WHERE scope_type = 'unit' AND scope_value = ? LIMIT 1");
        mysqli_stmt_bind_param($cek_stmt, 's', $id_unit);
        mysqli_stmt_execute($cek_stmt);
        $cek_hasil = mysqli_stmt_get_result($cek_stmt);

        if ($cek_hasil && mysqli_num_rows($cek_hasil) > 0) {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Unit ini sudah memiliki aturan batas waktu. Silakan edit aturan yang sudah ada.'));
            exit;
        }

        $stmt = mysqli_prepare($koneksi, "INSERT INTO batas_waktu_hadir (jam_batas, scope_type, scope_value, updated_at) VALUES (?, 'unit', ?, NOW())");
        mysqli_stmt_bind_param($stmt, 'ss', $jam_batas, $id_unit);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: setting.php?menu=setting&status=sukses&pesan=' . urlencode('Aturan batas waktu khusus unit berhasil ditambahkan.'));
            exit;
        } else {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Gagal menambahkan aturan unit: ' . mysqli_error($koneksi)));
            exit;
        }
    } else {
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Pilih unit dan isi jam batas terlebih dahulu.'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'update_batas_unit') {
    $id_batas  = (int) ($_POST['id_batas'] ?? 0);
    $jam_batas = trim($_POST['jam_batas'] ?? '');

    if ($koneksi && $id_batas > 0 && $jam_batas !== '') {
        $stmt = mysqli_prepare($koneksi, "UPDATE batas_waktu_hadir SET jam_batas = ? WHERE id = ? AND scope_type = 'unit'");
        mysqli_stmt_bind_param($stmt, 'si', $jam_batas, $id_batas);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: setting.php?menu=setting&status=sukses&pesan=' . urlencode('Aturan batas waktu unit berhasil diperbarui.'));
            exit;
        } else {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Gagal memperbarui aturan unit: ' . mysqli_error($koneksi)));
            exit;
        }
    } else {
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Data aturan unit tidak lengkap.'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'hapus_batas_unit') {
    $id_batas = (int) ($_POST['id_batas'] ?? 0);

    if ($koneksi && $id_batas > 0) {
        $stmt = mysqli_prepare($koneksi, "DELETE FROM batas_waktu_hadir WHERE id = ? AND scope_type = 'unit'");
        mysqli_stmt_bind_param($stmt, 'i', $id_batas);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: setting.php?menu=setting&status=sukses&pesan=' . urlencode('Aturan batas waktu unit berhasil dihapus, unit ini kembali mengikuti aturan semua karyawan.'));
            exit;
        } else {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Gagal menghapus aturan unit: ' . mysqli_error($koneksi)));
            exit;
        }
    } else {
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Aturan unit tidak ditemukan.'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah_versi') {
    $version_code = (int) ($_POST['version_code'] ?? 0);
    $version_name = trim($_POST['version_name'] ?? '');
    $apk_url      = trim($_POST['apk_url'] ?? '');
    $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
    $changelog    = trim($_POST['changelog'] ?? '');

    // Contoh penyiapan isi judul & pesan untuk notifikasi manual
    $judul_notif = "Update Versi " . $version_name;
    $pesan_notif = "Versi baru telah tersedia. Silakan perbarui aplikasi Anda.";

    if ($koneksi && $version_code > 0 && $version_name !== '' && $apk_url !== '') {
        
        // 1. Insert ke tabel app_version
        $query_app = "INSERT INTO app_version (version_code, version_name, apk_url, is_mandatory, changelog, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt_app = mysqli_prepare($koneksi, $query_app);
        
        if ($stmt_app) {
            mysqli_stmt_bind_param($stmt_app, 'issis', $version_code, $version_name, $apk_url, $is_mandatory, $changelog);
            $exec_app = mysqli_stmt_execute($stmt_app);
            mysqli_stmt_close($stmt_app);

            if ($exec_app) {
                // 2. Insert ke tabel notifikasi_manual jika query pertama berhasil
                $query_notif = "INSERT INTO notifikasi_manual (judul, pesan, target_type, target_value, status) VALUES (?, ?, 'semua', '', 'pending')";
                $stmt_notif = mysqli_prepare($koneksi, $query_notif);

                if ($stmt_notif) {
                    // 'ss' untuk 2 parameter string (judul & pesan)
                    mysqli_stmt_bind_param($stmt_notif, 'ss', $judul_notif, $pesan_notif);
                    mysqli_stmt_execute($stmt_notif);
                    mysqli_stmt_close($stmt_notif);
                }

                header('Location: setting.php?menu=setting&status=sukses&pesan=' . urlencode('Versi aplikasi baru berhasil ditambahkan.'));
                exit;
            }
        }

        // Jika gagal execute atau prepare query pertama
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Gagal menambahkan versi: ' . mysqli_error($koneksi)));
        exit;

    } else {
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Data versi aplikasi tidak lengkap.'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah_lokasi') {
    $nama_lokasi  = trim($_POST['nama_lokasi'] ?? '');
    $latitude     = trim($_POST['latitude'] ?? '');
    $longitude    = trim($_POST['longitude'] ?? '');
    $radius_meter = (int) ($_POST['radius_meter'] ?? 0);

    if ($koneksi && $nama_lokasi !== '' && $latitude !== '' && $longitude !== '' && $radius_meter > 0) {
        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO lokasi_absensi (nama_lokasi, latitude, longitude, radius_meter, aktif) VALUES (?, ?, ?, ?, 1)"
        );
        mysqli_stmt_bind_param($stmt, 'sddi', $nama_lokasi, $latitude, $longitude, $radius_meter);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: setting.php?menu=setting&status=sukses&pesan=' . urlencode('Titik lokasi baru berhasil ditambahkan.'));
            exit;
        } else {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Gagal menambahkan titik lokasi: ' . mysqli_error($koneksi)));
            exit;
        }
    } else {
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Data titik lokasi tidak lengkap.'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'update_lokasi') {
    $id_lokasi    = (int) ($_POST['id_lokasi'] ?? 0);
    $nama_lokasi  = trim($_POST['nama_lokasi'] ?? '');
    $latitude     = trim($_POST['latitude'] ?? '');
    $longitude    = trim($_POST['longitude'] ?? '');
    $radius_meter = (int) ($_POST['radius_meter'] ?? 0);
    $aktif        = isset($_POST['aktif']) ? 1 : 0;

    if ($koneksi && $id_lokasi > 0 && $nama_lokasi !== '' && $latitude !== '' && $longitude !== '' && $radius_meter > 0) {
        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE lokasi_absensi SET nama_lokasi = ?, latitude = ?, longitude = ?, radius_meter = ?, aktif = ? WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'sddiii', $nama_lokasi, $latitude, $longitude, $radius_meter, $aktif, $id_lokasi);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: setting.php?menu=setting&status=sukses&pesan=' . urlencode('Titik lokasi berhasil diperbarui.'));
            exit;
        } else {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Gagal memperbarui titik lokasi: ' . mysqli_error($koneksi)));
            exit;
        }
    } else {
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Data titik lokasi tidak lengkap.'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'hapus_lokasi') {
    $id_lokasi = (int) ($_POST['id_lokasi'] ?? 0);

    if ($koneksi && $id_lokasi > 0) {
        $stmt = mysqli_prepare($koneksi, "DELETE FROM lokasi_absensi WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id_lokasi);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: setting.php?menu=setting&status=sukses&pesan=' . urlencode('Titik lokasi berhasil dihapus.'));
            exit;
        } else {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Gagal menghapus titik lokasi: ' . mysqli_error($koneksi)));
            exit;
        }
    } else {
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Titik lokasi tidak ditemukan.'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'kirim_notifikasi') {
    $judul_notif  = trim($_POST['judul_notif'] ?? '');
    $pesan_notif  = trim($_POST['pesan_notif'] ?? '');
    $target_type  = trim($_POST['target_type'] ?? '');
    $target_value = trim($_POST['target_value'] ?? '');

    $target_type_valid = in_array($target_type, ['semua', 'unit', 'karyawan'], true);

    if ($target_type === 'semua') {
        $target_value = null;
    }

    if (
        $koneksi && $judul_notif !== '' && $pesan_notif !== '' && $target_type_valid
        && ($target_type === 'semua' || $target_value !== '')
    ) {
        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO notifikasi_manual (judul, pesan, target_type, target_value, status) VALUES (?, ?, ?, ?, 'pending')"
        );
        mysqli_stmt_bind_param($stmt, 'ssss', $judul_notif, $pesan_notif, $target_type, $target_value);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: setting.php?menu=setting&status=sukses&pesan=' . urlencode('Notifikasi berhasil dimasukkan ke antrian dan akan segera dikirim.'));
            exit;
        } else {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Gagal mengirim notifikasi: ' . mysqli_error($koneksi)));
            exit;
        }
    } else {
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Data notifikasi tidak lengkap. Pastikan judul, pesan, dan target sudah diisi.'));
        exit;
    }
}

$data_batas_waktu = null;

if ($koneksi) {
    $query_batas = "SELECT * FROM batas_waktu_hadir WHERE scope_type = 'semua' ORDER BY id ASC LIMIT 1";
    $result_batas = mysqli_query($koneksi, $query_batas);
    if ($result_batas && mysqli_num_rows($result_batas) > 0) {
        $data_batas_waktu = mysqli_fetch_assoc($result_batas);
    }
}

$data_batas_unit = [];

if ($koneksi) {
    $query_batas_unit = "SELECT b.*, u.nm_unit
                          FROM batas_waktu_hadir b
                          LEFT JOIN unit u ON b.scope_value = u.id_unit COLLATE utf8mb4_unicode_ci
                          WHERE b.scope_type = 'unit'
                          ORDER BY u.nm_unit ASC";
    $result_batas_unit = mysqli_query($koneksi, $query_batas_unit);
    if ($result_batas_unit) {
        while ($row = mysqli_fetch_assoc($result_batas_unit)) {
            $data_batas_unit[] = $row;
        }
    }
}

$daftar_unit_tersedia = [];

if ($koneksi) {
    $query_unit_tersedia = "SELECT id_unit, nm_unit FROM unit
                             WHERE id_unit COLLATE utf8mb4_unicode_ci NOT IN (
                                 SELECT scope_value FROM batas_waktu_hadir
                                 WHERE scope_type = 'unit' AND scope_value IS NOT NULL
                             )
                             ORDER BY nm_unit ASC";
    $result_unit_tersedia = mysqli_query($koneksi, $query_unit_tersedia);
    if ($result_unit_tersedia) {
        while ($row = mysqli_fetch_assoc($result_unit_tersedia)) {
            $daftar_unit_tersedia[] = $row;
        }
    }
}

$data_versi = [];
$versi_terbaru = null;

if ($koneksi) {
    $query_versi = "SELECT * FROM app_version ORDER BY version_code DESC";
    $result_versi = mysqli_query($koneksi, $query_versi);
    if ($result_versi) {
        while ($row = mysqli_fetch_assoc($result_versi)) {
            $data_versi[] = $row;
        }
    }
    if (count($data_versi) > 0) {
        $versi_terbaru = $data_versi[0];
    }
}

$data_lokasi = [];

if ($koneksi) {
    $query_lokasi = "SELECT * FROM lokasi_absensi ORDER BY nama_lokasi ASC";
    $result_lokasi = mysqli_query($koneksi, $query_lokasi);
    if ($result_lokasi) {
        while ($row = mysqli_fetch_assoc($result_lokasi)) {
            $data_lokasi[] = $row;
        }
    }
}

$semua_unit = [];

if ($koneksi) {
    $query_semua_unit = "SELECT id_unit, nm_unit FROM unit ORDER BY nm_unit ASC";
    $result_semua_unit = mysqli_query($koneksi, $query_semua_unit);
    if ($result_semua_unit) {
        while ($row = mysqli_fetch_assoc($result_semua_unit)) {
            $semua_unit[] = $row;
        }
    }
}

$semua_karyawan = [];

if ($koneksi) {
    $query_semua_karyawan = "SELECT nik, nama FROM karyawan WHERE status_aktif = 'Aktif' ORDER BY nama ASC";
    $result_semua_karyawan = mysqli_query($koneksi, $query_semua_karyawan);
    if ($result_semua_karyawan) {
        while ($row = mysqli_fetch_assoc($result_semua_karyawan)) {
            $semua_karyawan[] = $row;
        }
    }
}

$riwayat_notifikasi = [];

if ($koneksi) {
    $query_notifikasi = "SELECT n.*, u.nm_unit, k.nama AS nama_karyawan
                          FROM notifikasi_manual n
                          LEFT JOIN unit u ON n.target_type = 'unit' AND n.target_value = u.id_unit COLLATE utf8mb4_unicode_ci
                          LEFT JOIN karyawan k ON n.target_type = 'karyawan' AND n.target_value = k.nik COLLATE utf8mb4_unicode_ci
                          ORDER BY n.created_at DESC
                          LIMIT 20";
    $result_notifikasi = mysqli_query($koneksi, $query_notifikasi);
    if ($result_notifikasi) {
        while ($row = mysqli_fetch_assoc($result_notifikasi)) {
            $riwayat_notifikasi[] = $row;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setting - Berkah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

    <div class="app-shell">

        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/includes/mobile-topbar.php'; ?>
            <div class="mb-3">
                <h5 class="page-title">Pengaturan Sistem</h5>
                <p class="page-sub">Kelola batas waktu kehadiran & versi aplikasi Berkah Presensi</p>
            </div>

            <?php if ($pesan): ?>
                <div class="alert-info-custom <?php echo $status === 'sukses' ? 'alert alert-success' : 'alert alert-danger'; ?>">
                    <i class="bi <?php echo $status === 'sukses' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> me-1"></i>
                    <?php echo $pesan; ?>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <div class="setting-card h-100">
                        <div class="setting-card-title">
                            <i class="bi bi-clock-fill"></i>
                            Batas Waktu Hadir - Semua Karyawan
                        </div>
                        <div class="setting-card-sub">
                            Jam batas keterlambatan default untuk seluruh karyawan. Unit yang punya aturan khusus di bawah akan memakai jamnya sendiri, bukan jam ini.
                        </div>

                        <?php if ($data_batas_waktu): ?>
                            <form method="POST" action="setting.php">
                                <input type="hidden" name="aksi" value="simpan_batas_waktu">
                                <input type="hidden" name="id_batas" value="<?php echo (int) $data_batas_waktu['id']; ?>">

                                <div class="mb-3">
                                    <label class="form-label">Jam Batas Hadir</label>
                                    <input type="time" name="jam_batas" class="form-control" step="1"
                                           value="<?php echo htmlspecialchars($data_batas_waktu['jam_batas']); ?>" required>
                                </div>

                                <div class="mb-3 text-muted" style="font-size:0.78rem;">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Terakhir diperbarui: <?php echo htmlspecialchars($data_batas_waktu['updated_at']); ?>
                                </div>

                                <button type="submit" class="btn btn-simpan">
                                    <i class="bi bi-save2 me-1"></i> Simpan Perubahan
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-clock-history" style="font-size:2rem;"></i>
                                <p class="mt-2 mb-0">Data batas waktu (scope: semua) belum tersedia di database.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="setting-card h-100">
                        <div class="setting-card-title">
                            <i class="bi bi-diagram-3-fill"></i>
                            Batas Waktu Hadir - Per Unit
                        </div>
                        <div class="setting-card-sub">
                            Buat aturan jam batas khusus untuk unit tertentu, di luar jam default di atas.
                        </div>

                        <?php if (count($data_batas_unit) > 0): ?>
                            <div class="table-responsive mb-3">
                                <table class="table table-versi align-middle">
                                    <thead>
                                        <tr>
                                            <th>Unit</th>
                                            <th>Jam Batas</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data_batas_unit as $bu): ?>
                                            <tr>
                                                <td class="fw-semibold">
                                                    <?php echo htmlspecialchars($bu['nm_unit'] ?? $bu['scope_value']); ?>
                                                </td>
                                                <td style="min-width:130px;">
                                                    <form method="POST" action="setting.php" class="d-flex gap-1">
                                                        <input type="hidden" name="aksi" value="update_batas_unit">
                                                        <input type="hidden" name="id_batas" value="<?php echo (int) $bu['id']; ?>">
                                                        <input type="time" name="jam_batas" class="form-control form-control-sm" step="1"
                                                               value="<?php echo htmlspecialchars($bu['jam_batas']); ?>" required>
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Simpan">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td style="width:36px;">
                                                    <form method="POST" action="setting.php" onsubmit="return confirm('Hapus aturan khusus unit ini? Unit akan kembali memakai jam default.');">
                                                        <input type="hidden" name="aksi" value="hapus_batas_unit">
                                                        <input type="hidden" name="id_batas" value="<?php echo (int) $bu['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3 text-muted">
                                <i class="bi bi-diagram-3" style="font-size:1.8rem;"></i>
                                <p class="mt-2 mb-0" style="font-size:0.85rem;">Belum ada aturan khusus per unit. Semua unit memakai jam default.</p>
                            </div>
                        <?php endif; ?>

                        <?php if (count($daftar_unit_tersedia) > 0): ?>
                            <hr>
                            <form method="POST" action="setting.php">
                                <input type="hidden" name="aksi" value="tambah_batas_unit">

                                <div class="row g-2 align-items-end">
                                    <div class="col-6">
                                        <label class="form-label">Pilih Unit</label>
                                        <select name="id_unit" class="form-select" required>
                                            <option value="" disabled selected>-- Pilih Unit --</option>
                                            <?php foreach ($daftar_unit_tersedia as $u): ?>
                                                <option value="<?php echo htmlspecialchars($u['id_unit']); ?>">
                                                    <?php echo htmlspecialchars($u['nm_unit']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label">Jam Batas</label>
                                        <input type="time" name="jam_batas" class="form-control" step="1" required>
                                    </div>
                                    <div class="col-2 d-grid">
                                        <button type="submit" class="btn btn-simpan" title="Tambah Aturan">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <hr>
                            <p class="text-muted mb-0" style="font-size:0.78rem;">
                                <i class="bi bi-check-circle me-1"></i>
                                Semua unit sudah memiliki aturan batas waktu masing-masing.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="row g-3">
                <div class="col-12">
                    <div class="setting-card">
                        <div class="setting-card-title">
                            <i class="bi bi-geo-alt-fill"></i>
                            Titik Lokasi Absensi (Radius)
                        </div>
                        <div class="setting-card-sub">
                            Kelola titik lokasi resmi (kantor, kandang, dll) beserta radius
                            yang diizinkan untuk absen tanpa perlu foto bukti tambahan.
                        </div>

                        <?php if (count($data_lokasi) > 0): ?>
                            <div class="table-responsive mb-3">
                                <table class="table table-versi align-middle">
                                    <thead>
                                        <tr>
                                            <th>Nama Lokasi</th>
                                            <th>Latitude</th>
                                            <th>Longitude</th>
                                            <th>Radius (m)</th>
                                            <th>Aktif</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data_lokasi as $lok): ?>
                                            <tr>
                                                <form method="POST" action="setting.php">
                                                <input type="hidden" name="aksi" value="update_lokasi">
                                                <input type="hidden" name="id_lokasi" value="<?php echo (int) $lok['id']; ?>">
                                                <td style="min-width:140px;">
                                                    <input type="text" name="nama_lokasi" class="form-control form-control-sm"
                                                           value="<?php echo htmlspecialchars($lok['nama_lokasi']); ?>" required>
                                                </td>
                                                <td style="min-width:110px;">
                                                    <input type="text" name="latitude" class="form-control form-control-sm"
                                                           value="<?php echo htmlspecialchars($lok['latitude']); ?>" required>
                                                </td>
                                                <td style="min-width:110px;">
                                                    <input type="text" name="longitude" class="form-control form-control-sm"
                                                           value="<?php echo htmlspecialchars($lok['longitude']); ?>" required>
                                                </td>
                                                <td style="min-width:90px;">
                                                    <input type="number" name="radius_meter" class="form-control form-control-sm"
                                                           value="<?php echo (int) $lok['radius_meter']; ?>" min="1" required>
                                                </td>
                                                <td style="text-align:center;">
                                                    <input type="checkbox" name="aktif" class="form-check-input"
                                                           <?php echo ((int) $lok['aktif'] === 1) ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="white-space:nowrap;">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Simpan">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                </td>
                                                </form>
                                                <td style="width:36px;">
                                                    <form method="POST" action="setting.php" onsubmit="return confirm('Hapus titik lokasi ini?');">
                                                        <input type="hidden" name="aksi" value="hapus_lokasi">
                                                        <input type="hidden" name="id_lokasi" value="<?php echo (int) $lok['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3 text-muted">
                                <i class="bi bi-geo-alt" style="font-size:1.8rem;"></i>
                                <p class="mt-2 mb-0" style="font-size:0.85rem;">Belum ada titik lokasi. Tambahkan minimal 1 supaya karyawan bisa absen tanpa foto tambahan.</p>
                            </div>
                        <?php endif; ?>

                        <hr>
                        <form method="POST" action="setting.php">
                            <input type="hidden" name="aksi" value="tambah_lokasi">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-3">
                                    <label class="form-label">Nama Lokasi</label>
                                    <input type="text" name="nama_lokasi" class="form-control" placeholder="cth: Kantor Pusat" required>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label">Latitude</label>
                                    <input type="text" name="latitude" class="form-control" placeholder="-6.9753200" required>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label">Longitude</label>
                                    <input type="text" name="longitude" class="form-control" placeholder="108.4832100" required>
                                </div>
                                <div class="col-8 col-md-2">
                                    <label class="form-label">Radius (m)</label>
                                    <input type="number" name="radius_meter" class="form-control" min="1" placeholder="100" required>
                                </div>
                                <div class="col-4 col-md-1 d-grid">
                                    <button type="submit" class="btn btn-simpan" title="Tambah">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="text-muted mt-2 mb-0" style="font-size:0.75rem;">
                                <i class="bi bi-info-circle me-1"></i>
                                Tips: cari koordinat lewat Google Maps — klik kanan di titik lokasi, salin angka yang muncul.
                            </p>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <div class="setting-card h-100">
                        <div class="setting-card-title">
                            <i class="bi bi-bell-fill"></i>
                            Kirim Notifikasi Manual
                        </div>
                        <div class="setting-card-sub">
                            Kirim notifikasi push ke semua karyawan, satu unit tertentu, atau satu karyawan tertentu.
                            Notifikasi masuk ke antrian dan dikirim otomatis lewat FCM.
                        </div>

                        <form method="POST" action="setting.php" id="form-notifikasi">
                            <input type="hidden" name="aksi" value="kirim_notifikasi">

                            <div class="mb-3">
                                <label class="form-label">Judul Notifikasi</label>
                                <input type="text" name="judul_notif" class="form-control" maxlength="100"
                                       placeholder="cth: Pengumuman Libur" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Isi Pesan</label>
                                <textarea name="pesan_notif" class="form-control" rows="3"
                                          placeholder="Tulis isi pesan notifikasi..." required></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Kirim Ke</label>
                                <select name="target_type" id="target_type" class="form-select" required>
                                    <option value="semua">Semua Karyawan</option>
                                    <option value="unit">Unit Tertentu</option>
                                    <option value="karyawan">Karyawan Tertentu</option>
                                </select>
                            </div>

                            <div class="mb-3" id="wrap-target-unit" style="display:none;">
                                <label class="form-label">Pilih Unit</label>
                                <select name="target_value_unit" class="form-select">
                                    <option value="" disabled selected>-- Pilih Unit --</option>
                                    <?php foreach ($semua_unit as $u): ?>
                                        <option value="<?php echo htmlspecialchars($u['id_unit']); ?>">
                                            <?php echo htmlspecialchars($u['nm_unit']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3" id="wrap-target-karyawan" style="display:none;">
                                <label class="form-label">Pilih Karyawan</label>
                                <select name="target_value_karyawan" class="form-select">
                                    <option value="" disabled selected>-- Pilih Karyawan --</option>
                                    <?php foreach ($semua_karyawan as $k): ?>
                                        <option value="<?php echo htmlspecialchars($k['nik']); ?>">
                                            <?php echo htmlspecialchars($k['nama']); ?> (<?php echo htmlspecialchars($k['nik']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <input type="hidden" name="target_value" id="target_value_final">

                            <button type="submit" class="btn btn-simpan">
                                <i class="bi bi-send-fill me-1"></i> Kirim Notifikasi
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="setting-card h-100">
                        <div class="setting-card-title">
                            <i class="bi bi-clock-history"></i>
                            Riwayat Notifikasi Manual
                        </div>
                        <div class="setting-card-sub">20 notifikasi terakhir yang dikirim dari panel ini</div>

                        <?php if (count($riwayat_notifikasi) === 0): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-bell-slash" style="font-size:2rem;"></i>
                                <p class="mt-2 mb-0">Belum ada notifikasi yang dikirim.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive" style="max-height:420px; overflow-y:auto;">
                                <table class="table table-versi align-middle">
                                    <thead>
                                        <tr>
                                            <th>Judul</th>
                                            <th>Target</th>
                                            <th>Status</th>
                                            <th>Dibuat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($riwayat_notifikasi as $n): ?>
                                            <?php
                                                if ($n['target_type'] === 'semua') {
                                                    $target_tampil = 'Semua Karyawan';
                                                } elseif ($n['target_type'] === 'unit') {
                                                    $target_tampil = $n['nm_unit'] ?? $n['target_value'];
                                                } else {
                                                    $target_tampil = $n['nama_karyawan'] ?? $n['target_value'];
                                                }

                                                $badge_class = 'bg-secondary';
                                                if ($n['status'] === 'sent') {
                                                    $badge_class = 'bg-success';
                                                } elseif ($n['status'] === 'failed') {
                                                    $badge_class = 'bg-danger';
                                                } elseif ($n['status'] === 'pending') {
                                                    $badge_class = 'bg-warning text-dark';
                                                }
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($n['judul']); ?></div>
                                                    <div class="text-muted" style="font-size:0.75rem;">
                                                        <?php echo htmlspecialchars(mb_strimwidth($n['pesan'], 0, 60, '...')); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($target_tampil); ?></td>
                                                <td><span class="badge <?php echo $badge_class; ?> badge-wajib"><?php echo htmlspecialchars(ucfirst($n['status'])); ?></span></td>
                                                <td style="white-space:nowrap; font-size:0.8rem;"><?php echo htmlspecialchars($n['created_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <div class="setting-card h-100">
                        <div class="setting-card-title">
                            <i class="bi bi-phone-fill"></i>
                            Versi Aplikasi
                        </div>
                        <div class="setting-card-sub">
                            Tambahkan rilis versi baru aplikasi mobile Berkah Presensi
                        </div>

                        <?php if ($versi_terbaru): ?>
                            <div class="versi-terbaru-box">
                                <div>
                                    <div class="label-kecil">Versi Aktif Saat Ini</div>
                                    <div class="versi-angka">
                                        v<?php echo htmlspecialchars($versi_terbaru['version_name']); ?>
                                        <span class="text-muted" style="font-weight:600; font-size:0.8rem;">
                                            (code <?php echo (int) $versi_terbaru['version_code']; ?>)
                                        </span>
                                    </div>
                                </div>
                                <?php if ((int) $versi_terbaru['is_mandatory'] === 1): ?>
                                    <span class="badge bg-danger badge-wajib">Update Wajib</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary badge-wajib">Opsional</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="setting.php">
                            <input type="hidden" name="aksi" value="tambah_versi">

                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label">Version Code</label>
                                    <input type="number" name="version_code" class="form-control"
                                           min="1" placeholder="cth: 3" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Version Name</label>
                                    <input type="text" name="version_name" class="form-control"
                                           placeholder="cth: 1.0.2" required>
                                </div>
                            </div>

                            <div class="mb-3 mt-2">
                                <label class="form-label">URL File APK</label>
                                <input type="url" name="apk_url" class="form-control"
                                       placeholder="https://..." required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Changelog</label>
                                <textarea name="changelog" class="form-control" rows="2" placeholder="Catatan perubahan pada versi ini..."></textarea>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_mandatory" id="is_mandatory" value="1">
                                <label class="form-check-label" for="is_mandatory" style="font-size:0.85rem;">
                                    Jadikan update wajib (mandatory)
                                </label>
                            </div>

                            <button type="submit" class="btn btn-simpan">
                                <i class="bi bi-plus-lg me-1"></i> Tambah Versi
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="setting-card">
                <div class="setting-card-title">
                    <i class="bi bi-clock-history"></i>
                    Riwayat Versi Aplikasi
                </div>
                <div class="setting-card-sub">Daftar seluruh rilis versi aplikasi, terbaru di atas</div>

                <?php if (count($data_versi) === 0): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-box-seam" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">Belum ada data versi aplikasi.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-versi align-middle">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Versi</th>
                                    <th>Status</th>
                                    <th>Changelog</th>
                                    <th>Dirilis</th>
                                    <th>APK</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data_versi as $v): ?>
                                    <tr>
                                        <td><?php echo (int) $v['version_code']; ?></td>
                                        <td class="fw-semibold">v<?php echo htmlspecialchars($v['version_name']); ?></td>
                                        <td>
                                            <?php if ((int) $v['is_mandatory'] === 1): ?>
                                                <span class="badge bg-danger badge-wajib">Wajib</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary badge-wajib">Opsional</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($v['changelog'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($v['created_at']); ?></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($v['apk_url']); ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        (function () {
            const form        = document.getElementById('form-notifikasi');
            if (!form) return;

            const targetType    = document.getElementById('target_type');
            const wrapUnit      = document.getElementById('wrap-target-unit');
            const wrapKaryawan  = document.getElementById('wrap-target-karyawan');
            const selectUnit    = form.querySelector('select[name="target_value_unit"]');
            const selectKaryawan = form.querySelector('select[name="target_value_karyawan"]');
            const hiddenTarget  = document.getElementById('target_value_final');

            function toggleTarget() {
                const val = targetType.value;
                wrapUnit.style.display = (val === 'unit') ? '' : 'none';
                wrapKaryawan.style.display = (val === 'karyawan') ? '' : 'none';
                selectUnit.required = (val === 'unit');
                selectKaryawan.required = (val === 'karyawan');
            }

            targetType.addEventListener('change', toggleTarget);
            toggleTarget();

            form.addEventListener('submit', function () {
                const val = targetType.value;
                if (val === 'unit') {
                    hiddenTarget.value = selectUnit.value;
                } else if (val === 'karyawan') {
                    hiddenTarget.value = selectKaryawan.value;
                } else {
                    hiddenTarget.value = '';
                }
            });
        })();
    </script>
</body>
</html>
