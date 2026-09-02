<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_admin();

$menu_aktif = $_GET['menu'] ?? 'jabatan';
require_once 'koneksi.php';

/* =====================================================
   KONFIGURASI NAMA KOLOM TABEL 'jabatan'
   id_jabatan varchar(10) PK (diisi manual, mis. J001)
   nm_jabatan varchar(35)
===================================================== */
const KOLOM_ID   = 'id_jabatan';
const KOLOM_NAMA = 'nm_jabatan';

/* =====================================================
   FLASH MESSAGE (notifikasi sukses/gagal setelah redirect)
===================================================== */
function set_flash($tipe, $pesan)
{
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function ambil_flash()
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/* Bangun ulang query string GET saat ini (untuk redirect balik ke filter/halaman yang sama) */
function redirect_kembali()
{
    $qs = $_GET;
    unset($qs['action']);
    $url = 'jabatan.php' . (count($qs) ? '?' . http_build_query($qs) : '');
    header('Location: ' . $url);
    exit;
}

/* =====================================================
   PROSES AKSI: TAMBAH / EDIT / HAPUS (via POST)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $koneksi) {

    $aksi = $_POST['aksi'] ?? '';

    // ---------------- TAMBAH JABATAN ----------------
    if ($aksi === 'tambah') {
        $id_jabatan = trim($_POST['id_jabatan'] ?? '');
        $nm_jabatan = trim($_POST['nm_jabatan'] ?? '');

        if ($id_jabatan === '' || $nm_jabatan === '') {
            set_flash('danger', 'ID Jabatan dan Nama Jabatan wajib diisi.');
            redirect_kembali();
        }

        // Cek ID Jabatan sudah dipakai atau belum (karena ini primary key manual)
        $cek = mysqli_prepare($koneksi, "SELECT " . KOLOM_ID . " FROM jabatan WHERE " . KOLOM_ID . " = ?");
        mysqli_stmt_bind_param($cek, 's', $id_jabatan);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            set_flash('danger', 'ID Jabatan "' . $id_jabatan . '" sudah digunakan, silakan pakai ID lain.');
            mysqli_stmt_close($cek);
            redirect_kembali();
        }
        mysqli_stmt_close($cek);

        $stmt = mysqli_prepare($koneksi, "INSERT INTO jabatan (" . KOLOM_ID . ", " . KOLOM_NAMA . ") VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, 'ss', $id_jabatan, $nm_jabatan);

        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', 'Jabatan baru berhasil ditambahkan.');
        } else {
            set_flash('danger', 'Gagal menambahkan jabatan: ' . mysqli_error($koneksi));
        }
        mysqli_stmt_close($stmt);
        redirect_kembali();
    }

    // ---------------- EDIT JABATAN ----------------
    if ($aksi === 'edit') {
        $id_lama    = $_POST['id_lama'] ?? '';
        $id_jabatan = trim($_POST['id_jabatan'] ?? '');
        $nm_jabatan = trim($_POST['nm_jabatan'] ?? '');

        if ($id_lama === '' || $id_jabatan === '' || $nm_jabatan === '') {
            set_flash('danger', 'Data tidak lengkap untuk mengubah jabatan.');
            redirect_kembali();
        }

        // Kalau ID Jabatan diganti, cek dulu ID baru belum dipakai jabatan lain
        if ($id_jabatan !== $id_lama) {
            $cek = mysqli_prepare($koneksi, "SELECT " . KOLOM_ID . " FROM jabatan WHERE " . KOLOM_ID . " = ?");
            mysqli_stmt_bind_param($cek, 's', $id_jabatan);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);

            if (mysqli_stmt_num_rows($cek) > 0) {
                set_flash('danger', 'ID Jabatan "' . $id_jabatan . '" sudah dipakai jabatan lain.');
                mysqli_stmt_close($cek);
                redirect_kembali();
            }
            mysqli_stmt_close($cek);
        }

        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE jabatan SET " . KOLOM_ID . " = ?, " . KOLOM_NAMA . " = ? WHERE " . KOLOM_ID . " = ?"
        );
        mysqli_stmt_bind_param($stmt, 'sss', $id_jabatan, $nm_jabatan, $id_lama);

        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', 'Data jabatan berhasil diperbarui.');
        } else {
            // Kemungkinan gagal karena id_jabatan lama masih dipakai di tabel karyawan (FK)
            set_flash('danger', 'Gagal memperbarui jabatan. Pastikan ID baru tidak bentrok, atau jabatan ini sedang dipakai karyawan. (' . mysqli_error($koneksi) . ')');
        }
        mysqli_stmt_close($stmt);
        redirect_kembali();
    }

    // ---------------- HAPUS JABATAN ----------------
    if ($aksi === 'hapus') {
        $id_jabatan = $_POST['id_jabatan'] ?? '';

        if ($id_jabatan === '') {
            set_flash('danger', 'ID Jabatan tidak valid.');
            redirect_kembali();
        }

        // Cek dulu apakah jabatan ini masih dipakai oleh karyawan
        $cek_pakai = mysqli_prepare($koneksi, "SELECT COUNT(*) AS jumlah FROM karyawan WHERE id_jabatan = ?");
        mysqli_stmt_bind_param($cek_pakai, 's', $id_jabatan);
        mysqli_stmt_execute($cek_pakai);
        $hasil_cek = mysqli_stmt_get_result($cek_pakai);
        $jumlah_pakai = $hasil_cek ? (int) mysqli_fetch_assoc($hasil_cek)['jumlah'] : 0;
        mysqli_stmt_close($cek_pakai);

        if ($jumlah_pakai > 0) {
            set_flash('danger', 'Jabatan ini tidak bisa dihapus karena masih digunakan oleh ' . $jumlah_pakai . ' karyawan.');
            redirect_kembali();
        }

        $stmt = mysqli_prepare($koneksi, "DELETE FROM jabatan WHERE " . KOLOM_ID . " = ?");
        mysqli_stmt_bind_param($stmt, 's', $id_jabatan);

        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', 'Jabatan berhasil dihapus.');
        } else {
            set_flash('danger', 'Gagal menghapus jabatan: ' . mysqli_error($koneksi));
        }
        mysqli_stmt_close($stmt);
        redirect_kembali();
    }
}

$flash = ambil_flash();

// ================= FILTER & SEARCH =================
$cari        = trim($_GET['cari'] ?? '');
$halaman     = max(1, (int) ($_GET['halaman'] ?? 1));
$per_halaman = 8; // Jumlah card per halaman

// ================= AMBIL DATA JABATAN DARI DATABASE =================
$data_jabatan = [];

if ($koneksi) {
    $where  = [];
    $tipe   = '';
    $params = [];

    if (!empty($cari)) {
        $where[] = "(" . KOLOM_NAMA . " LIKE CONCAT('%', ?, '%') OR " . KOLOM_ID . " LIKE CONCAT('%', ?, '%'))";
        $tipe .= 'ss';
        $params[] = &$cari;
        $params[] = &$cari;
    }

    $where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query_jabatan = "SELECT * FROM jabatan $where_sql ORDER BY " . KOLOM_NAMA . " ASC";
    $stmt_jabatan  = mysqli_prepare($koneksi, $query_jabatan);

    if ($stmt_jabatan) {
        if ($tipe !== '') {
            array_unshift($params, $tipe);
            call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_jabatan], $params));
        }
        mysqli_stmt_execute($stmt_jabatan);
        $result_jabatan = mysqli_stmt_get_result($stmt_jabatan);

        if ($result_jabatan) {
            while ($row = mysqli_fetch_assoc($result_jabatan)) {
                $data_jabatan[] = $row;
            }
        }
        mysqli_stmt_close($stmt_jabatan);
    }
}

// ================= PAGINATION =================
$total_data    = count($data_jabatan);
$total_halaman = max(1, (int) ceil($total_data / $per_halaman));
$halaman       = min($halaman, $total_halaman);
$offset        = ($halaman - 1) * $per_halaman;
$data_tampil   = array_slice($data_jabatan, $offset, $per_halaman);

function build_query($override = [])
{
    $params = array_merge($_GET, $override);
    return htmlspecialchars('?' . http_build_query($params));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Jabatan - Berkah</title>

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
                    <h5 class="page-title">Manajemen Jabatan</h5>
                    <p class="page-sub">Kelola struktur posisi & jabatan pegawai Berkah Global Business</p>
                </div>
                <button type="button" class="btn btn-sm btn-tambah" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="bi bi-plus-lg"></i> Tambah Jabatan
                </button>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash['tipe']); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash['pesan']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- FILTER FORM -->
            <form class="row g-2 align-items-end filter-form mb-4" method="GET" action="jabatan.php">
                <input type="hidden" name="menu" value="jabatan">

                <div class="col-12 col-md-10">
                    <label class="form-label">Cari Jabatan</label>
                    <input type="text" name="cari" class="form-control" placeholder="Nama atau ID Jabatan..." value="<?php echo htmlspecialchars($cari); ?>">
                </div>

                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn btn-success" style="background:var(--teal-mid); border:none; border-radius:10px;">
                        <i class="bi bi-search"></i> Cari
                    </button>
                </div>
            </form>

            <!-- CARD JABATAN GRID -->
            <?php if (count($data_tampil) === 0): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-briefcase" style="font-size:2.5rem;"></i>
                    <p class="mt-2">Tidak ada data jabatan yang ditemukan.</p>
                </div>
            <?php else: ?>
                <div class="row g-3 mb-4">
                    <?php foreach ($data_tampil as $row):
                        $id_jabatan = $row[KOLOM_ID] ?? '';
                        $nm_jabatan = $row[KOLOM_NAMA] ?? '';
                    ?>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                            <div class="card-jabatan">
                                <div class="icon-jabatan">
                                    <i class="bi bi-briefcase-fill"></i>
                                </div>

                                <div class="jabatan-nama">
                                    <?php echo htmlspecialchars($nm_jabatan); ?>
                                </div>

                                <span class="badge-id">
                                    ID: <?php echo htmlspecialchars($id_jabatan); ?>
                                </span>

                                <div class="card-actions">
                                    <button type="button"
                                        class="btn btn-outline-success btn-edit-jabatan"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEdit"
                                        data-id="<?php echo htmlspecialchars($id_jabatan); ?>"
                                        data-nama="<?php echo htmlspecialchars($nm_jabatan); ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>

                                    <form method="POST" action="jabatan.php" onsubmit="return confirm('Hapus jabatan &quot;<?php echo htmlspecialchars($nm_jabatan); ?>&quot; ?');">
                                        <input type="hidden" name="aksi" value="hapus">
                                        <input type="hidden" name="id_jabatan" value="<?php echo htmlspecialchars($id_jabatan); ?>">
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
                        Menampilkan <?php echo $offset + 1; ?>–<?php echo min($offset + $per_halaman, $total_data); ?> dari <?php echo $total_data; ?> jabatan
                    <?php else: ?>
                        0 jabatan
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

    <!-- ===================== MODAL TAMBAH JABATAN ===================== -->
    <div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:16px; overflow:hidden;">
                <form method="POST" action="jabatan.php">
                    <input type="hidden" name="aksi" value="tambah">
                    <div class="modal-header" style="background:var(--teal-mid); color:#fff;">
                        <h5 class="modal-title"><i class="bi bi-briefcase-fill me-2"></i>Tambah Jabatan</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ID Jabatan</label>
                            <input type="text" name="id_jabatan" class="form-control" placeholder="Contoh: J007" maxlength="10" required>
                            <div class="form-text">Kode unik jabatan, maksimal 10 karakter.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Jabatan</label>
                            <input type="text" name="nm_jabatan" class="form-control" placeholder="Contoh: Kepala Toko" maxlength="35" required>
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

    <!-- ===================== MODAL EDIT JABATAN ===================== -->
    <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:16px; overflow:hidden;">
                <form method="POST" action="jabatan.php" id="formEdit">
                    <input type="hidden" name="aksi" value="edit">
                    <input type="hidden" name="id_lama" id="edit_id_lama">
                    <div class="modal-header" style="background:var(--teal-mid); color:#fff;">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Jabatan</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ID Jabatan</label>
                            <input type="text" name="id_jabatan" id="edit_id_jabatan" class="form-control" maxlength="10" required>
                            <div class="form-text">Hati-hati mengubah ID jika sudah dipakai di data karyawan.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Jabatan</label>
                            <input type="text" name="nm_jabatan" id="edit_nm_jabatan" class="form-control" maxlength="35" required>
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
        // Isi otomatis modal Edit dengan data jabatan yang diklik
        document.querySelectorAll('.btn-edit-jabatan').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('edit_id_lama').value    = btn.dataset.id;
                document.getElementById('edit_id_jabatan').value = btn.dataset.id;
                document.getElementById('edit_nm_jabatan').value = btn.dataset.nama;
            });
        });
    </script>
</body>
</html>