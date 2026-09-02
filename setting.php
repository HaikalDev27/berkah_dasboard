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

    if ($koneksi && $version_code > 0 && $version_name !== '' && $apk_url !== '') {
        $query_insert = "INSERT INTO app_version (version_code, version_name, apk_url, is_mandatory, changelog, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($koneksi, $query_insert);
        mysqli_stmt_bind_param($stmt, 'issis', $version_code, $version_name, $apk_url, $is_mandatory, $changelog);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: setting.php?menu=setting&status=sukses&pesan=' . urlencode('Versi aplikasi baru berhasil ditambahkan.'));
            exit;
        } else {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Gagal menambahkan versi: ' . mysqli_error($koneksi)));
            exit;
        }
    } else {
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Data versi aplikasi tidak lengkap.'));
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
</body>
</html>
