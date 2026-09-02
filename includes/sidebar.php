<?php

$menu_aktif = $menu_aktif ?? '';

$menu_items = [
    'dashboard'  => ['label' => 'Dashboard',  'icon' => 'bi-grid-fill',            'href' => 'dashboard.php?menu=dashboard'],
    'absensi'    => ['label' => 'Absensi',    'icon' => 'bi-person-check-fill',    'href' => 'absensi.php?menu=absensi'],
    'karyawan'   => ['label' => 'Karyawan',   'icon' => 'bi-people-fill',          'href' => 'karyawan.php?menu=karyawan'],
    'user'       => ['label' => 'User',       'icon' => 'bi-person-badge-fill',    'href' => 'user.php?menu=user'],
    'jabatan'    => ['label' => 'Jabatan',    'icon' => 'bi-briefcase-fill',       'href' => 'jabatan.php?menu=jabatan'],
    'login_unit' => ['label' => 'Login Unit', 'icon' => 'bi-building',             'href' => 'login_unit.php?menu=login_unit'],
    'setting'    => ['label' => 'Setting',    'icon' => 'bi-gear-fill',            'href' => 'setting.php?menu=setting'],
];
?>
<div class="sidebar-overlay"></div>
<aside class="sidebar">
    <div class="brand">
        <img src="bg-login/logo-berkah.png" alt="Logo Berkah" class="brand-logo">
        <span><span class="accent">B</span>erkah</span>
    </div>

    <div class="menu-label">Main Menu</div>

    <ul class="menu-nav">
        <?php foreach ($menu_items as $key => $item): ?>
        <li class="menu-item <?php echo $menu_aktif === $key ? 'active' : ''; ?>">
            <a href="<?php echo htmlspecialchars($item['href']); ?>">
                <i class="bi <?php echo htmlspecialchars($item['icon']); ?>"></i> <?php echo htmlspecialchars($item['label']); ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="sidebar-footer">
        <form action="logout.php" method="POST">
            <button type="submit" class="btn-logout">
                <i class="bi bi-box-arrow-right"></i> Go Out
            </button>
        </form>
    </div>
</aside>
