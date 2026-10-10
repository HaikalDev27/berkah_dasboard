<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once 'koneksi.php';

// Hapus HANYA boleh lewat POST (dipicu dari tombol + confirm() di absensi.php),
// bukan lewat link GET biasa - supaya tidak gampang ke-trigger tanpa sengaja
// (mis. oleh crawler/prefetch browser) dan lebih tahan CSRF sederhana.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: absensi.php?menu=absensi&aksi_status=gagal&aksi_pesan=" . urlencode("Permintaan hapus tidak valid"));
    exit();
}

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    header("Location: absensi.php?menu=absensi&aksi_status=gagal&aksi_pesan=" . urlencode("ID data tidak valid"));
    exit();
}

if (!$koneksi) {
    header("Location: absensi.php?menu=absensi&aksi_status=gagal&aksi_pesan=" . urlencode("Koneksi database gagal"));
    exit();
}

// Ambil dulu nama foto (kalau ada) supaya bisa ikut dibersihkan di server upload.
$foto_lama = null;
$stmt = mysqli_prepare($koneksi, "SELECT foto_bukti FROM absensi WHERE id_absensi = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = $res ? mysqli_fetch_assoc($res) : null;

if (!$row) {
    header("Location: absensi.php?menu=absensi&aksi_status=gagal&aksi_pesan=" . urlencode("Data absensi tidak ditemukan (mungkin sudah dihapus sebelumnya)"));
    exit();
}

$foto_lama = $row['foto_bukti'];

$stmt = mysqli_prepare($koneksi, "DELETE FROM absensi WHERE id_absensi = ?");
mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
    // Catatan: foto_bukti disimpan di server terpisah (lewat ngrok/API presensi),
    // bukan di server aplikasi ini, jadi file fisiknya TIDAK ikut terhapus otomatis
    // di sini. Kalau server foto itu bisa diakses dari sini juga, tambahkan proses
    // hapus filenya di titik ini.
    header("Location: absensi.php?menu=absensi&aksi_status=sukses&aksi_pesan=" . urlencode("Data absensi berhasil dihapus"));
} else {
    header("Location: absensi.php?menu=absensi&aksi_status=gagal&aksi_pesan=" . urlencode("Gagal menghapus data: " . mysqli_error($koneksi)));
}
exit();
