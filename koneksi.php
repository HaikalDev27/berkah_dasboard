<?php
    require_once __DIR__ . '/vendor/autoload.php';

    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
    } catch (Exception $e) {
        error_log('Failed to load .env file: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Gagal memuat konfigurasi. Silakan coba lagi nanti.',
            'data'    => null,
        ]);
        exit;
    }

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $login = $_ENV['DB_USN'] ?? '';
    $password = $_ENV['DB_PWD'] ?? '';
    $database = $_ENV['DB_NAME'] ?? '';

        $koneksi = mysqli_connect($host, $login, $password, $database);

        if (!$koneksi) {
            die('gagal konek: ' . mysqli_connect_error());
        }
?>