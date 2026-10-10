<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once 'koneksi.php';

$id = (int) ($_GET['id'] ?? 0);

if ($koneksi && $id > 0) {
    $stmt = mysqli_prepare($koneksi, "UPDATE absensi SET status_approval = 'approved' WHERE id_absensi = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);

    if (mysqli_stmt_execute($stmt)) {
        header('Location: absensi.php?menu=absensi&aksi_status=sukses&aksi_pesan=' . urlencode('Absensi berhasil disetujui.'));
        exit;
    }
}

header('Location: absensi.php?menu=absensi&aksi_status=gagal&aksi_pesan=' . urlencode('Gagal menyetujui absensi.'));
exit;
