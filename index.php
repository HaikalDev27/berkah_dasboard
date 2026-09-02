<?php
session_start();

/* Kalau sudah login, tidak perlu lihat halaman login lagi. */
if (!empty($_SESSION['idUser'])) {
    header('Location: dashboard.php');
    exit();
}

$pesan = isset($_GET['pesan']) ? htmlspecialchars($_GET['pesan']) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absen Berkah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="login-page-body">

    <div class="login-wrapper">
        <div class="login-card">

            <div class="side-panel">
                <div class="tri tri-1"></div>
                <div class="tri tri-2"></div>
                <div class="tri tri-3"></div>

                <div class="tab-group">
                    <span class="tab-item active"><strong>BERKAH</strong></span>
                </div>
            </div>

            <div class="form-panel">
                <img src="bg-login/logo-berkah.png" alt="Logo Berkah Chicken" class="form-logo">

                <?php if ($pesan): ?>
                    <div class="text-danger alert-error"><?php echo $pesan; ?></div>
                <?php endif; ?>

                <form action="proses_login.php" method="POST">
                    <div class="input-underline">
                        <i class="bi bi-person-circle"></i>
                        <input type="text" name="username" placeholder="Username" required>
                    </div>

                    <div class="input-underline">
                        <i class="bi bi-lock-fill"></i>
                        <input type="password" name="password" placeholder="Password" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-login-pill">LOGIN</button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
