<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_admin();

$menu_aktif = $_GET['menu'] ?? 'login_unit';
require_once 'koneksi.php';

if (!defined('KOLOM_ID_UNIT')) {
    define('KOLOM_ID_UNIT', 'id_unit');
}
if (!defined('KOLOM_NAMA_UNIT')) {
    define('KOLOM_NAMA_UNIT', 'nm_unit');
}

if (!function_exists('set_flash')) {
    function set_flash($tipe, $pesan)
    {
        $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
    }
}

if (!function_exists('ambil_flash')) {
    function ambil_flash()
    {
        if (!empty($_SESSION['flash'])) {
            $f = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $f;
        }
        return null;
    }
}

if (!function_exists('redirect_kembali_unit')) {
    function redirect_kembali_unit()
    {
        $qs = $_GET;
        unset($qs['action']);
        $url = 'login_unit.php' . (count($qs) ? '?' . http_build_query($qs) : '');
        header('Location: ' . $url);
        exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $koneksi) {

    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $id_unit = trim($_POST['id_unit'] ?? '');
        $nm_unit = trim($_POST['nm_unit'] ?? '');

        if ($id_unit === '' || $nm_unit === '') {
            set_flash('danger', 'ID Unit dan Nama Unit wajib diisi.');
            redirect_kembali_unit();
        }

        $cek = mysqli_prepare($koneksi, "SELECT " . KOLOM_ID_UNIT . " FROM unit WHERE " . KOLOM_ID_UNIT . " = ?");
        mysqli_stmt_bind_param($cek, 's', $id_unit);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            set_flash('danger', 'ID Unit "' . $id_unit . '" sudah digunakan, silakan pakai ID lain.');
            mysqli_stmt_close($cek);
            redirect_kembali_unit();
        }
        mysqli_stmt_close($cek);

        $stmt = mysqli_prepare($koneksi, "INSERT INTO unit (" . KOLOM_ID_UNIT . ", " . KOLOM_NAMA_UNIT . ") VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, 'ss', $id_unit, $nm_unit);

        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', 'Unit baru berhasil ditambahkan.');
        } else {
            set_flash('danger', 'Gagal menambahkan unit: ' . mysqli_error($koneksi));
        }
        mysqli_stmt_close($stmt);
        redirect_kembali_unit();
    }

    if ($aksi === 'edit') {
        $id_lama = $_POST['id_lama'] ?? '';
        $id_unit = trim($_POST['id_unit'] ?? '');
        $nm_unit = trim($_POST['nm_unit'] ?? '');

        if ($id_lama === '' || $id_unit === '' || $nm_unit === '') {
            set_flash('danger', 'Data tidak lengkap untuk mengubah unit.');
            redirect_kembali_unit();
        }
        if ($id_unit !== $id_lama) {
            $cek = mysqli_prepare($koneksi, "SELECT " . KOLOM_ID_UNIT . " FROM unit WHERE " . KOLOM_ID_UNIT . " = ?");
            mysqli_stmt_bind_param($cek, 's', $id_unit);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);

            if (mysqli_stmt_num_rows($cek) > 0) {
                set_flash('danger', 'ID Unit "' . $id_unit . '" sudah dipakai unit lain.');
                mysqli_stmt_close($cek);
                redirect_kembali_unit();
            }
            mysqli_stmt_close($cek);
        }

        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE unit SET " . KOLOM_ID_UNIT . " = ?, " . KOLOM_NAMA_UNIT . " = ? WHERE " . KOLOM_ID_UNIT . " = ?"
        );
        mysqli_stmt_bind_param($stmt, 'sss', $id_unit, $nm_unit, $id_lama);

        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', 'Data unit berhasil diperbarui.');
        } else {
            set_flash('danger', 'Gagal memperbarui unit. Pastikan ID baru tidak bentrok, atau unit ini sedang dipakai karyawan. (' . mysqli_error($koneksi) . ')');
        }
        mysqli_stmt_close($stmt);
        redirect_kembali_unit();
    }

    if ($aksi === 'hapus') {
        $id_unit = $_POST['id_unit'] ?? '';

        if ($id_unit === '') {
            set_flash('danger', 'ID Unit tidak valid.');
            redirect_kembali_unit();
        }

        $cek_pakai = mysqli_prepare($koneksi, "SELECT COUNT(*) AS jumlah FROM karyawan WHERE id_unit = ?");
        mysqli_stmt_bind_param($cek_pakai, 's', $id_unit);
        mysqli_stmt_execute($cek_pakai);
        $hasil_cek = mysqli_stmt_get_result($cek_pakai);
        $jumlah_pakai = $hasil_cek ? (int) mysqli_fetch_assoc($hasil_cek)['jumlah'] : 0;
        mysqli_stmt_close($cek_pakai);

        if ($jumlah_pakai > 0) {
            set_flash('danger', 'Unit ini tidak bisa dihapus karena masih digunakan oleh ' . $jumlah_pakai . ' karyawan.');
            redirect_kembali_unit();
        }

        $stmt = mysqli_prepare($koneksi, "DELETE FROM unit WHERE " . KOLOM_ID_UNIT . " = ?");
        mysqli_stmt_bind_param($stmt, 's', $id_unit);

        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', 'Unit berhasil dihapus.');
        } else {
            set_flash('danger', 'Gagal menghapus unit: ' . mysqli_error($koneksi));
        }
        mysqli_stmt_close($stmt);
        redirect_kembali_unit();
    }
}

$flash = ambil_flash();

$cari        = trim($_GET['cari'] ?? '');
$halaman     = max(1, (int) ($_GET['halaman'] ?? 1));
$per_halaman = 8; 

$data_unit = [];

if ($koneksi) {
    $where  = [];
    $tipe   = '';
    $params = [];

    if (!empty($cari)) {
        $where[] = "(" . KOLOM_NAMA_UNIT . " LIKE CONCAT('%', ?, '%') OR " . KOLOM_ID_UNIT . " LIKE CONCAT('%', ?, '%'))";
        $tipe .= 'ss';
        $params[] = &$cari;
        $params[] = &$cari;
    }

    $where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query_unit = "SELECT * FROM unit $where_sql ORDER BY " . KOLOM_NAMA_UNIT . " ASC";
    $stmt_unit  = mysqli_prepare($koneksi, $query_unit);

    if ($stmt_unit) {
        if ($tipe !== '') {
            array_unshift($params, $tipe);
            call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_unit], $params));
        }
        mysqli_stmt_execute($stmt_unit);
        $result_unit = mysqli_stmt_get_result($stmt_unit);

        if ($result_unit) {
            while ($row = mysqli_fetch_assoc($result_unit)) {
                $data_unit[] = $row;
            }
        }
        mysqli_stmt_close($stmt_unit);
    }
}

$total_data    = count($data_unit);
$total_halaman = max(1, (int) ceil($total_data / $per_halaman));
$halaman       = min($halaman, $total_halaman);
$offset        = ($halaman - 1) * $per_halaman;
$data_tampil   = array_slice($data_unit, $offset, $per_halaman);

if (!function_exists('build_query')) {
    function build_query($override = [])
    {
        $params = array_merge($_GET, $override);
        return htmlspecialchars('?' . http_build_query($params));
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Login Unit - Berkah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

    <div class="app-shell">

        <!-- SIDEBAR -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <?php include __DIR__ . '/includes/mobile-topbar.php'; ?>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h5 class="page-title">Manajemen Login Unit</h5>
                    <p class="page-sub">Kelola daftar unit & lokasi kerja Chicken Berkah</p>
                </div>
                <button type="button" class="btn btn-sm btn-tambah" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="bi bi-plus-lg"></i> Tambah Unit
                </button>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash['tipe']); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash['pesan']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- FILTER FORM -->
            <form class="row g-2 align-items-end filter-form mb-4" method="GET" action="login_unit.php">
                <input type="hidden" name="menu" value="login_unit">

                <div class="col-12 col-md-10">
                    <label class="form-label">Cari Unit</label>
                    <input type="text" name="cari" class="form-control" placeholder="Nama atau ID Unit..." value="<?php echo htmlspecialchars($cari); ?>">
                </div>

                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn btn-success" style="background:var(--teal-mid); border:none; border-radius:10px;">
                        <i class="bi bi-search"></i> Cari
                    </button>
                </div>
            </form>

            <!-- CARD UNIT GRID -->
            <?php if (count($data_tampil) === 0): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-building" style="font-size:2.5rem;"></i>
                    <p class="mt-2">Tidak ada data unit yang ditemukan.</p>
                </div>
            <?php else: ?>
                <div class="row g-3 mb-4">
                    <?php foreach ($data_tampil as $row):
                        $id_unit = $row[KOLOM_ID_UNIT] ?? '';
                        $nm_unit = $row[KOLOM_NAMA_UNIT] ?? '';
                    ?>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                            <div class="card-unit">
                                <div class="icon-unit">
                                    <i class="bi bi-building"></i>
                                </div>

                                <div class="unit-nama">
                                    <?php echo htmlspecialchars($nm_unit); ?>
                                </div>

                                <span class="badge-id">
                                    ID: <?php echo htmlspecialchars($id_unit); ?>
                                </span>

                                <div class="card-actions">
                                    <button type="button"
                                        class="btn btn-outline-success btn-edit-unit"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEdit"
                                        data-id="<?php echo htmlspecialchars($id_unit); ?>"
                                        data-nama="<?php echo htmlspecialchars($nm_unit); ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>

                                    <form method="POST" action="login_unit.php" onsubmit="return confirm('Hapus unit &quot;<?php echo htmlspecialchars($nm_unit); ?>&quot; ?');">
                                        <input type="hidden" name="aksi" value="hapus">
                                        <input type="hidden" name="id_unit" value="<?php echo htmlspecialchars($id_unit); ?>">
                                        <button type="submit" class="btn btn-outline-danger">
                                            <i class="bi bi-trash"></i> Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- FOOTER & PAGINATION -->
            <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top">
                <div class="text-muted" style="font-size: 0.8rem;">
                    <?php if ($total_data > 0): ?>
                        Menampilkan <?php echo $offset + 1; ?>–<?php echo min($offset + $per_halaman, $total_data); ?> dari <?php echo $total_data; ?> unit
                    <?php else: ?>
                        0 unit
                    <?php endif; ?>
                </div>

                <?php if ($total_halaman > 1): ?>
                <nav>
                    <ul class="pagination mb-0">
                        <li class="page-item <?php echo $halaman <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo build_query(['halaman' => $halaman - 1]); ?>">‹</a>
                        </li>
                        <?php for ($p = 1; $p <= $total_halaman; $p++): ?>
                            <li class="page-item <?php echo $p === $halaman ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo build_query(['halaman' => $p]); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $halaman >= $total_halaman ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo build_query(['halaman' => $halaman + 1]); ?>">›</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </main>

    </div>

    <!-- ===================== MODAL TAMBAH UNIT ===================== -->
    <div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:16px; overflow:hidden;">
                <form method="POST" action="login_unit.php">
                    <input type="hidden" name="aksi" value="tambah">
                    <div class="modal-header" style="background:var(--teal-mid); color:#fff;">
                        <h5 class="modal-title"><i class="bi bi-building me-2"></i>Tambah Unit</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ID Unit</label>
                            <input type="text" name="id_unit" class="form-control" placeholder="Contoh: U006" maxlength="4" required>
                            <div class="form-text">Kode unik unit, maksimal 4 karakter.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Unit</label>
                            <input type="text" name="nm_unit" class="form-control" placeholder="Contoh: Marketing" maxlength="50" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success" style="background:var(--teal-mid); border:none;">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===================== MODAL EDIT UNIT ===================== -->
    <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:16px; overflow:hidden;">
                <form method="POST" action="login_unit.php" id="formEdit">
                    <input type="hidden" name="aksi" value="edit">
                    <input type="hidden" name="id_lama" id="edit_id_lama">
                    <div class="modal-header" style="background:var(--teal-mid); color:#fff;">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Unit</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ID Unit</label>
                            <input type="text" name="id_unit" id="edit_id_unit" class="form-control" maxlength="4" required>
                            <div class="form-text">Hati-hati mengubah ID jika sudah dipakai di data karyawan.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Unit</label>
                            <input type="text" name="nm_unit" id="edit_nm_unit" class="form-control" maxlength="50" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success" style="background:var(--teal-mid); border:none;">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        // Isi otomatis modal Edit dengan data unit yang diklik
        document.querySelectorAll('.btn-edit-unit').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('edit_id_lama').value  = btn.dataset.id;
                document.getElementById('edit_id_unit').value  = btn.dataset.id;
                document.getElementById('edit_nm_unit').value  = btn.dataset.nama;
            });
        });
    </script>
</body>
</html>