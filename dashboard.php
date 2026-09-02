<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_admin();

$menu_aktif = $_GET['menu'] ?? 'dashboard';
require_once 'koneksi.php';
$jumlah_hadir = 0;
$jumlah_izin  = 0;
$jumlah_sakit = 0;

if ($koneksi) {
    $query_stat = "SELECT absensi, COUNT(*) as jumlah 
                   FROM absensi 
                   WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                   GROUP BY absensi";
    $result_stat = mysqli_query($koneksi, $query_stat);

    if ($result_stat) {
        while ($row = mysqli_fetch_assoc($result_stat)) {
            if ($row['absensi'] === 'H') $jumlah_hadir = (int) $row['jumlah'];
            if ($row['absensi'] === 'I') $jumlah_izin  = (int) $row['jumlah'];
            if ($row['absensi'] === 'S') $jumlah_sakit = (int) $row['jumlah'];
        }
    }   
}
$jam_masuk_standar  = '08:00:00';
$jam_pulang_standar = '16:00:00';
$tahun_dipilih      = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');

$label_bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$kategori_list = ['tepat_waktu', 'terlambat', 'pulang_cepat', 'tidak_absen'];

$data_bulanan = [];
for ($b = 1; $b <= 12; $b++) {
    $data_bulanan[$b] = array_fill_keys($kategori_list, 0);
}
$data_tahunan = array_fill_keys($kategori_list, 0);

if ($koneksi) {
    $query_chart = "SELECT tanggal, masuk, keluar, absensi 
                     FROM absensi 
                     WHERE YEAR(tanggal) = " . (int) $tahun_dipilih;
    $result_chart = mysqli_query($koneksi, $query_chart);

    if ($result_chart) {
        while ($row = mysqli_fetch_assoc($result_chart)) {
            $bulan = (int) date('n', strtotime($row['tanggal']));

            if ($row['absensi'] === 'TK') {
                $kategori = 'tidak_absen';
            } elseif ($row['absensi'] === 'H') {
                if (!empty($row['masuk']) && $row['masuk'] > $jam_masuk_standar) {
                    $kategori = 'terlambat';
                } elseif (!empty($row['keluar']) && $row['keluar'] < $jam_pulang_standar) {
                    $kategori = 'pulang_cepat';
                } else {
                    $kategori = 'tepat_waktu';
                }
            } else {
                continue;
            }

            $data_bulanan[$bulan][$kategori]++;
            $data_tahunan[$kategori]++;
        }
    }
}

$chart_tepat_waktu   = [];
$chart_terlambat     = [];
$chart_pulang_cepat  = [];
$chart_tidak_absen   = [];
for ($b = 1; $b <= 12; $b++) {
    $chart_tepat_waktu[]  = $data_bulanan[$b]['tepat_waktu'];
    $chart_terlambat[]    = $data_bulanan[$b]['terlambat'];
    $chart_pulang_cepat[] = $data_bulanan[$b]['pulang_cepat'];
    $chart_tidak_absen[]  = $data_bulanan[$b]['tidak_absen'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Absen Berkah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

    <div class="app-shell">

        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/includes/mobile-topbar.php'; ?>
            <div class="topbar">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" placeholder="Search">
                </div>
                <button class="btn-notif">
                    <i class="bi bi-bell-fill"></i>
                    <span class="dot"></span>
                </button>
            </div>

            <h5 class="page-title">Selamat datang kembali 👋</h5>

            <?php if ($menu_aktif === 'dashboard'): ?>
                <div class="stat-cards">
                    <div class="stat-card hadir">
                        <div class="stat-icon"><i class="bi bi-person-check-fill"></i></div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $jumlah_hadir; ?></div>
                            <div class="stat-label">Hadir (7 hari)</div>
                        </div>
                    </div>
                    <div class="stat-card izin">
                        <div class="stat-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $jumlah_izin; ?></div>
                            <div class="stat-label">Izin (7 hari)</div>
                        </div>
                    </div>
                    <div class="stat-card sakit">
                        <div class="stat-icon"><i class="bi bi-thermometer-half"></i></div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $jumlah_sakit; ?></div>
                            <div class="stat-label">Sakit (7 hari)</div>
                        </div>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-card bulanan">
                        <h6>Presensi Perbulan</h6>
                        <canvas id="chartBulanan"></canvas>
                    </div>
                    <div class="chart-card tahunan">
                        <h6>Presensi Pertahun</h6>
                        <canvas id="chartTahunan"></canvas>
                    </div>
                </div>
            <?php else: ?>
                <div class="placeholder-card">
                    Konten halaman <strong><?php echo ucfirst($menu_aktif); ?></strong> ditampilkan di sini.
                </div>
            <?php endif; ?>
        </main>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>

    <?php if ($menu_aktif === 'dashboard'): ?>
    <script>
        const labelBulan = <?php echo json_encode($label_bulan); ?>;

        const dataTepatWaktu  = <?php echo json_encode($chart_tepat_waktu); ?>;
        const dataTerlambat   = <?php echo json_encode($chart_terlambat); ?>;
        const dataPulangCepat = <?php echo json_encode($chart_pulang_cepat); ?>;
        const dataTidakAbsen  = <?php echo json_encode($chart_tidak_absen); ?>;

        const dataTahunan = <?php echo json_encode(array_values($data_tahunan)); ?>;

        new Chart(document.getElementById('chartBulanan'), {
            type: 'bar',
            data: {
                labels: labelBulan,
                datasets: [
                    {
                        label: 'Tepat Waktu',
                        data: dataTepatWaktu,
                        backgroundColor: '#8de5b0',
                        stack: 'presensi'
                    },
                    {
                        label: 'Terlambat Masuk',
                        data: dataTerlambat,
                        backgroundColor: '#f5d491',
                        stack: 'presensi'
                    },
                    {
                        label: 'Pulang Sebelum Waktunya',
                        data: dataPulangCepat,
                        backgroundColor: '#f5a15e',
                        stack: 'presensi'
                    },
                    {
                        label: 'Tidak Absen',
                        data: dataTidakAbsen,
                        backgroundColor: '#f16a6a',
                        stack: 'presensi'
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true }
                }
            }
        });

        new Chart(document.getElementById('chartTahunan'), {
            type: 'pie',
            data: {
                labels: ['Tepat Waktu', 'Terlambat', 'Pulang Sebelum Waktunya', 'Tidak Absen'],
                datasets: [{
                    data: dataTahunan,
                    backgroundColor: ['#a78bfa', '#fde68a', '#fb923c', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>