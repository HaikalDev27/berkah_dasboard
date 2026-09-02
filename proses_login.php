<?php
session_start();
include 'koneksi.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$sqlCariUser = "SELECT * FROM login WHERE username = ?";
$stmt = mysqli_prepare($koneksi, $sqlCariUser);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$hasilCariUser = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($hasilCariUser) == 1) {
    $dataUser = mysqli_fetch_array($hasilCariUser, MYSQLI_ASSOC);

    if (password_verify($password, $dataUser['password'])) {
        session_regenerate_id(true);

        $_SESSION['idUser'] = $dataUser['id_user'];
        $_SESSION['roleUser'] = $dataUser['hak_akses'];
        $_SESSION['petugasLogin'] = $dataUser['username'];

        header('Location: dashboard.php');
        exit();
    }
}
header('Location: index.php?pesan=' . urlencode('Gagal login, periksa username dan password'));
exit();
