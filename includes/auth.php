<?php

function require_login()
{
    if (empty($_SESSION['idUser'])) {
        header('Location: index.php?pesan=' . urlencode('Silakan login terlebih dahulu'));
        exit();
    }
}

function require_admin()
{
    require_login();

    $role = strtolower((string) ($_SESSION['roleUser'] ?? ''));
    if ($role !== 'admin') {
        header('Location: index.php?pesan=' . urlencode('Anda tidak memiliki akses ke halaman ini'));
        exit();
    }
}
