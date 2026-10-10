<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: absensi.php?menu=absensi');
    exit;
}

$id      = (int) ($_POST['id'] ?? 0);
$catatan = trim($_POST['catatan'] ?? '');

if (!$koneksi || $id <= 0) {
    header('Location: absensi.php?menu=absensi&aksi_status=gagal&aksi_pesan=' . urlencode('Data tidak valid.'));
    exit;
}

// 1. Ambil dulu NIK + tanggal SEBELUM dihapus, dibutuhkan untuk notifikasi
$stmt = mysqli_prepare($koneksi, "SELECT nik, tanggal FROM absensi WHERE id_absensi = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);
$data  = mysqli_fetch_assoc($hasil);

if (!$data) {
    header('Location: absensi.php?menu=absensi&aksi_status=gagal&aksi_pesan=' . urlencode('Data absensi tidak ditemukan.'));
    exit;
}

$nik           = $data['nik'];
$tanggalTampil = date('d/m/Y', strtotime($data['tanggal']));

// 2. Hapus baris absensi — supaya karyawan bisa absen ulang hari itu
//    (constraint UNIQUE nik+tanggal akan menghalangi kalau baris lama masih ada)
$stmtDelete = mysqli_prepare($koneksi, "DELETE FROM absensi WHERE id_absensi = ?");
mysqli_stmt_bind_param($stmtDelete, 'i', $id);

if (!mysqli_stmt_execute($stmtDelete)) {
    header('Location: absensi.php?menu=absensi&aksi_status=gagal&aksi_pesan=' . urlencode('Gagal menolak absensi: ' . mysqli_error($koneksi)));
    exit;
}

// 3. Masukkan ke antrian notifikasi_manual — otomatis diproses & dikirim
//    lewat FCM oleh cron proses_notifikasi_manual.php yang sudah jalan.
$judul = 'Absensi Ditolak';
$pesanNotif = "Absensi Anda tanggal {$tanggalTampil} ditolak"
    . ($catatan !== '' ? ": {$catatan}" : '.')
    . ' Silakan lakukan absensi ulang.';

$stmtNotif = mysqli_prepare(
    $koneksi,
    "INSERT INTO notifikasi_manual (judul, pesan, target_type, target_value, status)
     VALUES (?, ?, 'karyawan', ?, 'pending')"
);
mysqli_stmt_bind_param($stmtNotif, 'sss', $judul, $pesanNotif, $nik);
mysqli_stmt_execute($stmtNotif);

header('Location: absensi.php?menu=absensi&aksi_status=sukses&aksi_pesan=' . urlencode('Absensi ditolak. Karyawan akan menerima notifikasi untuk absen ulang.'));
exit;
