<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();

$menu_aktif = 'absensi';
require_once 'koneksi.php';

$id_edit = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$mode_edit = $id_edit > 0;

// Request AJAX (dari modal di absensi.php) dijawab dengan JSON, bukan redirect,
// supaya bisa ditampilkan sebagai popup tanpa pindah halaman.
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$error = '';
$data = [
    'nik'      => '',
    'tanggal'  => date('Y-m-d'),
    'absensi'  => 'H',
    'masuk'    => '',
    'keluar'   => '',
    'ket'      => '',
];

// ================= AMBIL DAFTAR KARYAWAN AKTIF (untuk dropdown) =================
$daftar_karyawan = [];
if ($koneksi) {
    $q_kar = mysqli_query(
        $koneksi,
        "SELECT nik, nama FROM karyawan WHERE status_aktif = 'Aktif' ORDER BY nama ASC"
    );
    if ($q_kar) {
        while ($r = mysqli_fetch_assoc($q_kar)) {
            $daftar_karyawan[] = $r;
        }
    }
}

// ================= MODE EDIT: AMBIL DATA LAMA =================
if ($mode_edit && $koneksi) {
    $stmt = mysqli_prepare($koneksi, "SELECT nik, tanggal, absensi, masuk, keluar, ket FROM absensi WHERE id_absensi = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_edit);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row_lama = $res ? mysqli_fetch_assoc($res) : null;

    if (!$row_lama) {
        header("Location: absensi.php?menu=absensi&aksi_status=gagal&aksi_pesan=" . urlencode("Data absensi tidak ditemukan"));
        exit();
    }

    $data['nik']     = $row_lama['nik'];
    $data['tanggal'] = $row_lama['tanggal'];
    $data['absensi'] = $row_lama['absensi'];
    $data['masuk']   = $row_lama['masuk'] ?? '';
    $data['keluar']  = $row_lama['keluar'] ?? '';
    $data['ket']     = $row_lama['ket'] ?? '';
}

// ================= PROSES SIMPAN (POST) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $koneksi) {
    $nik      = trim($_POST['nik'] ?? '');
    $tanggal  = trim($_POST['tanggal'] ?? '');
    $absensi  = trim($_POST['absensi'] ?? '');
    $masuk    = trim($_POST['masuk'] ?? '');
    $keluar   = trim($_POST['keluar'] ?? '');
    $ket      = trim($_POST['ket'] ?? '');

    // simpan lagi ke $data supaya kalau ada error, form tidak kosong lagi
    $data = compact('nik', 'tanggal', 'absensi', 'masuk', 'keluar', 'ket');

    $jenis_valid = ['H', 'I', 'S', 'C', 'TK', 'OFF'];

    // ---- Validasi ----
    if ($nik === '') {
        $error = 'Karyawan wajib dipilih.';
    } elseif (!in_array($absensi, $jenis_valid, true)) {
        $error = 'Jenis absensi tidak valid.';
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $tanggal);
        if (!$d || $d->format('Y-m-d') !== $tanggal) {
            $error = 'Tanggal tidak valid.';
        }
    }

    if (!$error && $absensi !== 'H') {
        // Untuk izin/sakit/cuti/off/tidak-absen, jam masuk & keluar tidak relevan.
        $masuk  = '';
        $keluar = '';
        if (in_array($absensi, ['I', 'S', 'C'], true) && $ket === '') {
            $error = 'Keterangan wajib diisi untuk Izin/Sakit/Cuti.';
        }
    }

    if (!$error && $masuk !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $masuk)) {
        $error = 'Format jam masuk tidak valid.';
    }
    if (!$error && $keluar !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $keluar)) {
        $error = 'Format jam keluar tidak valid.';
    }

    // ---- Ambil id_unit & id_jabatan terkini dari data karyawan ----
    $id_unit = null;
    $id_jabatan = null;
    if (!$error) {
        $stmt = mysqli_prepare($koneksi, "SELECT id_unit, id_jabatan FROM karyawan WHERE nik = ?");
        mysqli_stmt_bind_param($stmt, "s", $nik);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $kar = $res ? mysqli_fetch_assoc($res) : null;

        if (!$kar) {
            $error = 'Karyawan dengan NIK tersebut tidak ditemukan.';
        } else {
            $id_unit    = $kar['id_unit'];
            $id_jabatan = $kar['id_jabatan'];
        }
    }

    // ---- Simpan ----
    if (!$error) {
        $masuk_param  = $masuk === '' ? null : $masuk;
        $keluar_param = $keluar === '' ? null : $keluar;
        $ket_param    = $ket === '' ? null : $ket;

        if ($mode_edit) {
            $stmt = mysqli_prepare($koneksi, "
                UPDATE absensi
                SET tanggal = ?, absensi = ?, masuk = ?, keluar = ?, ket = ?, id_unit = ?, id_jabatan = ?
                WHERE id_absensi = ?
            ");
            mysqli_stmt_bind_param(
                $stmt,
                "sssssssi",
                $tanggal, $absensi, $masuk_param, $keluar_param, $ket_param, $id_unit, $id_jabatan, $id_edit
            );
        } else {
            $stmt = mysqli_prepare($koneksi, "
                INSERT INTO absensi
                    (tanggal, nik, id_unit, id_jabatan, masuk, keluar, absensi, ket,
                     status_approval, diluar_radius)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', 0)
            ");
            mysqli_stmt_bind_param(
                $stmt,
                "ssssssss",
                $tanggal, $nik, $id_unit, $id_jabatan, $masuk_param, $keluar_param, $absensi, $ket_param
            );
        }

        if (mysqli_stmt_execute($stmt)) {
            $pesan = $mode_edit ? 'Data absensi berhasil diperbarui' : 'Absensi manual berhasil ditambahkan';

            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $pesan]);
                exit();
            }

            header("Location: absensi.php?menu=absensi&aksi_status=sukses&aksi_pesan=" . urlencode($pesan));
            exit();
        } else {
            // Error 1062 = duplikat (nik + tanggal sudah ada, sesuai UNIQUE KEY di tabel)
            if (mysqli_errno($koneksi) === 1062) {
                $error = 'Karyawan ini sudah punya data absensi di tanggal tersebut. Silakan edit data yang sudah ada, bukan menambah baru.';
            } else {
                $error = 'Gagal menyimpan data: ' . mysqli_error($koneksi);
            }
        }
    }

    // Kalau ada error di atas (validasi gagal / duplikat / dll) dan request-nya AJAX,
    // langsung balas JSON di sini juga, jangan lanjut render HTML halaman penuh.
    if ($error && $is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mode_edit ? 'Edit Absensi' : 'Tambah Absensi'; ?> - Berkah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .form-shell {
            max-width: 720px;
            margin: 2.5rem auto;
        }
        .form-card {
            background: #fff;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
            border: 1px solid #eef4f3;
        }
        .form-card h5 { font-weight: 700; margin-bottom: 1.5rem; }
        .field-jam { display: none; }
        .field-jam.show { display: block; }
    </style>
</head>
<body style="background:#f4f7f6;">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="form-shell">
        <div class="form-card">
            <h5>
                <i class="bi <?php echo $mode_edit ? 'bi-pencil-square' : 'bi-plus-circle'; ?> me-1"></i>
                <?php echo $mode_edit ? 'Edit Data Absensi' : 'Tambah Absensi Manual'; ?>
            </h5>

            <?php if (!$mode_edit): ?>
                <p class="text-muted" style="font-size: 0.85rem; margin-top:-1rem;">
                    Gunakan ini untuk mencatat kehadiran, izin, sakit, cuti, atau libur karyawan
                    yang tidak tercatat lewat aplikasi (misalnya HP rusak, lupa absen, atau
                    kejadian di luar sistem).
                </p>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 px-3">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="absensi_tambah.php<?php echo $mode_edit ? '?id=' . $id_edit : ''; ?>">

                <div class="mb-3">
                    <label class="form-label">Karyawan</label>
                    <select name="nik" class="form-select" <?php echo $mode_edit ? 'disabled' : 'required'; ?>>
                        <option value="">-- Pilih Karyawan --</option>
                        <?php foreach ($daftar_karyawan as $kar): ?>
                            <option value="<?php echo htmlspecialchars($kar['nik']); ?>"
                                <?php echo $data['nik'] === $kar['nik'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kar['nama']); ?> (<?php echo htmlspecialchars($kar['nik']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($mode_edit): ?>
                        <!-- select di-disable supaya tidak bisa diganti; kirim tetap nilainya lewat hidden input -->
                        <input type="hidden" name="nik" value="<?php echo htmlspecialchars($data['nik']); ?>">
                        <small class="text-muted">Karyawan tidak bisa diganti saat mengedit. Hapus data ini dan buat baru kalau salah pilih orang.</small>
                    <?php endif; ?>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" required
                               value="<?php echo htmlspecialchars($data['tanggal']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jenis Absensi</label>
                        <select name="absensi" id="jenisAbsensi" class="form-select" required onchange="toggleJam()">
                            <option value="H"   <?php echo $data['absensi'] === 'H' ? 'selected' : ''; ?>>Hadir</option>
                            <option value="I"   <?php echo $data['absensi'] === 'I' ? 'selected' : ''; ?>>Izin</option>
                            <option value="S"   <?php echo $data['absensi'] === 'S' ? 'selected' : ''; ?>>Sakit</option>
                            <option value="C"   <?php echo $data['absensi'] === 'C' ? 'selected' : ''; ?>>Cuti</option>
                            <option value="OFF" <?php echo $data['absensi'] === 'OFF' ? 'selected' : ''; ?>>Libur</option>
                            <option value="TK"  <?php echo $data['absensi'] === 'TK' ? 'selected' : ''; ?>>Tidak Ada Keterangan</option>
                        </select>
                    </div>
                </div>

                <div class="row field-jam" id="wrapJam">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jam Masuk</label>
                        <input type="time" name="masuk" class="form-control"
                               value="<?php echo htmlspecialchars(substr($data['masuk'], 0, 5)); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jam Keluar</label>
                        <input type="time" name="keluar" class="form-control"
                               value="<?php echo htmlspecialchars(substr($data['keluar'], 0, 5)); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Keterangan</label>
                    <textarea name="ket" class="form-control" rows="3"
                              placeholder="Contoh: HP rusak, absen manual dicatat satpam, dll."><?php echo htmlspecialchars($data['ket']); ?></textarea>
                </div>

                <div class="alert alert-secondary py-2 px-3" style="font-size:0.82rem;">
                    <i class="bi bi-info-circle me-1"></i>
                    Data yang ditambahkan/diedit di sini otomatis berstatus <strong>Disetujui</strong>
                    dan tidak punya foto/koordinat GPS, karena dicatat manual oleh admin.
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-check-lg me-1"></i> Simpan
                    </button>
                    <a href="absensi.php?menu=absensi" class="btn btn-light border px-4">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleJam() {
            const jenis = document.getElementById('jenisAbsensi').value;
            const wrap = document.getElementById('wrapJam');
            wrap.classList.toggle('show', jenis === 'H');
        }
        toggleJam();
    </script>
</body>
</html>
