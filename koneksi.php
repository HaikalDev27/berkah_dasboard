<?php
    require_once __DIR__ . '/vendor/autoload.php';

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $login = $_ENV['DB_USN'] ?? '';
    $password = $_ENV['DB_PWD'] ?? '';
    $database = $_ENV['DB_NAME'] ?? '';

        $koneksi = mysqli_connect($host, $login, $password, $database);

        if (!$koneksi) {
            die('gagal konek: ' . mysqli_connect_error());
        }
?>