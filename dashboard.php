<?php
$page_title = 'Dashboard';
$page_subtitle = 'Monitoring Real-Time Manajemen Risiko';
$current_page = 'dashboard';
require_once __DIR__ . '/includes/header.php';

// ======= STATISTIK =======
$total       = (int)$pdo->query("SELECT COUNT(*) FROM risiko")->fetchColumn();
$aktif       = (int)$pdo->query("SELECT COUNT(*) FROM risiko WHERE status_mitigasi != 'selesai'")->fetchColumn();
$selesai     = (int)$pdo->query("SELECT COUNT(*) FROM risiko WHERE status_mitigasi = 'selesai'")->fetchColumn();
$prioritas_t = (int)$pdo->query("SELECT COUNT(*) FROM risiko WHERE tingkat_risiko IN ('tinggi','sangat_tinggi')")->fetchColumn();
$overdue     = (int)$pdo->query("SELECT COUNT(*) FROM risiko WHERE jadwal_selesai < CURDATE() AND status_mitigasi != 'selesai'")->fetchColumn();

// Status mitigasi distribusi
$stmt = $pdo->query("SELECT status_mitigasi, COUNT(*) AS jml FROM risiko GROUP BY status_mitigasi");
$status_data = ['belum_dimulai'=>0,'dalam_proses'=>0,'selesai'=>0];
foreach ($stmt as $row) $status_data[$row['status_mitigasi']] = (int)$row['jml'];

// Heatmap 5x5
$stmt = $pdo->query("SELECT likelihood, impact, COUNT(*) AS jml FROM risiko GROUP BY likelihood, impact");
$heat = [];
foreach ($stmt as $row) $heat["{$row['likelihood']}-{$row['impact']}"] = (int)$row['jml'];

// Trend bulanan (12 bulan terakhir)
$stmt = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS bln, COUNT(*) AS jml
    FROM risiko
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY bln ORDER BY bln");
$trend_labels = []; $trend_data = [];
foreach ($stmt as $row) {
    $trend_labels[] = date('M Y', strtotime($row['bln'].'-01'));
    $trend_data[]   = (int)$row['jml'];
}
if (empty($trend_labels)) {
    $trend_labels = [date('M Y')];
    $trend_data   = [$total];
}

// Risiko terbaru
$recent = $pdo->query("
    SELECT r.*, u.nama_unit FROM risiko r
    LEFT JOIN unit_kerja u ON u.id = r.unit_id
    ORDER BY r.created_at DESC LIMIT 5")->fetchAll();

// Notifikasi
$notif = getNotifikasi($user['id'], 4);

// Persentase mitigasi total
$total_progress = $total > 0
    ? round(($pdo->query("SELECT COALESCE(SUM(progress),0) FROM risiko")->fetchColumn() / $total))
    : 0;

// Berdasarkan tingkat risiko untuk pie
$stmt = $pdo->query("SELECT tingkat_risiko, COUNT(*) AS jml FROM risiko GROUP BY tingkat_risiko");
$tingkat_pie = ['sangat_rendah'=>0,'rendah'=>0,'sedang'=>0,'tinggi'=>0,'sangat_tinggi'=>0];
foreach ($stmt as $row) $tingkat_pie[$row['tingkat_risiko']] = (int)$row['jml'];
?>

<!-- ===== STATISTIK CARDS ===== -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-top">
            <div>
                <div class="stat-label">Total Risiko</div>
                <div class="stat-value"><?= $total ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
        <div class="stat-trend">Teridentifikasi seluruh unit</div>
    </div>

    <div class="stat-card warning">
        <div class="stat-top">
            <div>
                <div class="stat-label">Risiko Aktif</div>
                <div class="stat-value"><?= $aktif ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-spinner"></i></div>
        </div>
        <div class="stat-trend">Dalam proses mitigasi</div>
    </div>

    <div class="stat-card success">
        <div class="stat-top">
            <div>
                <div class="stat-label">Selesai Dimitigasi</div>
                <div class="stat-value"><?= $selesai ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </div>
        <div class="stat-trend"><span class="up">↑ <?= $total>0?round($selesai/$total*100):0 ?>%</span> dari total</div>
    </div>

    <div class="stat-card orange">
        <div class="stat-top">
            <div>
                <div class="stat-label">Prioritas Tinggi</div>
                <div class="stat-value"><?= $prioritas_t ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-fire"></i></div>
        </div>
        <div class="stat-trend">Membutuhkan perhatian segera</div>
    </div>

    <div class="stat-card danger">
        <div class="stat-top">
            <div>
                <div class="stat-label">Overdue</div>
                <div class="stat-value"><?= $overdue ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
        <div class="stat-trend">Melebihi deadline mitigasi</div>
    </div>
</div>

<!-- ===== HEATMAP & STATUS MITIGASI ===== -->
<div class="row-2">
    <!-- Risk Matrix 5x5 -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3><i class="fas fa-th"></i> Risk Matrix Digital (Heatmap 5x5)</h3>
                <p>Likelihood × Impact</p>
            </div>
        </div>

        <div class="risk-matrix">
            <div></div>
            <?php for($i=1;$i<=5;$i++): ?>
                <div class="matrix-label">D<?= $i ?></div>
            <?php endfor; ?>

            <?php for($lh=5;$lh>=1;$lh--): ?>
                <div class="matrix-label">P<?= $lh ?></div>
                <?php for($im=1;$im<=5;$im++):
                    // Tentukan warna cell berdasarkan tingkat risiko (P*D*Bobot)
                    $matriks = [
                        '1-1'=>1,'1-2'=>2,'1-3'=>3,'1-4'=>4,'1-5'=>5,
                        '2-1'=>2,'2-2'=>3,'2-3'=>4,'2-4'=>5,'2-5'=>6,
                        '3-1'=>3,'3-2'=>4,'3-3'=>5,'3-4'=>6,'3-5'=>7,
                        '4-1'=>4,'4-2'=>5,'4-3'=>6,'4-4'=>7,'4-5'=>8,
                        '5-1'=>5,'5-2'=>6,'5-3'=>7,'5-4'=>8,'5-5'=>9
                    ];
                    $bb = $matriks["$lh-$im"];
                    $nl = $lh * $im * $bb;
                    if($nl<=15)      $cls='cell-rendah';
                    elseif($nl<=40)  $cls='cell-rendah';
                    elseif($nl<=80)  $cls='cell-sedang';
                    elseif($nl<=140) $cls='cell-tinggi';
                    else             $cls='cell-sangat-tinggi';
                    $jml = $heat["$lh-$im"] ?? 0;
                ?>
                    <div class="matrix-cell <?= $cls ?>" title="P<?= $lh ?> × D<?= $im ?> = <?= $nl ?>">
                        <span class="count"><?= $jml ?></span>
                        <span class="lvl">P<?= $lh ?>D<?= $im ?></span>
                    </div>
                <?php endfor; ?>
            <?php endfor; ?>
        </div>

        <div style="display:flex;justify-content:center;gap:16px;margin-top:20px;flex-wrap:wrap;font-size:11px">
            <span><span style="display:inline-block;width:12px;height:12px;background:#10b981;border-radius:3px;vertical-align:middle"></span> Rendah</span>
            <span><span style="display:inline-block;width:12px;height:12px;background:#f59e0b;border-radius:3px;vertical-align:middle"></span> Sedang</span>
            <span><span style="display:inline-block;width:12px;height:12px;background:#fb923c;border-radius:3px;vertical-align:middle"></span> Tinggi</span>
            <span><span style="display:inline-block;width:12px;height:12px;background:#ef4444;border-radius:3px;vertical-align:middle"></span> Sangat Tinggi</span>
        </div>
    </div>

    <!-- Status Mitigasi -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3><i class="fas fa-chart-pie"></i> Status Mitigasi</h3>
                <p>Distribusi tindak lanjut</p>
            </div>
        </div>

        <div style="text-align:center;margin-bottom:16px">
            <div style="font-size:36px;font-weight:800;color:var(--primary)"><?= $total_progress ?>%</div>
            <div style="font-size:12px;color:var(--text-mute)">Total progress mitigasi</div>
            <div class="progress mt-2" style="height:10px">
                <div class="progress-bar" style="width:<?= $total_progress ?>%"></div>
            </div>
        </div>

        <canvas id="chartStatus" height="180"></canvas>

        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:16px;text-align:center;font-size:11px">
            <div><div style="color:var(--text-mute)">Belum</div><strong style="font-size:18px;color:var(--text-dark)"><?= $status_data['belum_dimulai'] ?></strong></div>
            <div><div style="color:var(--text-mute)">Proses</div><strong style="font-size:18px;color:var(--info)"><?= $status_data['dalam_proses'] ?></strong></div>
            <div><div style="color:var(--text-mute)">Selesai</div><strong style="font-size:18px;color:var(--success)"><?= $status_data['selesai'] ?></strong></div>
        </div>
    </div>
</div>

<!-- ===== TREND & DISTRIBUSI ===== -->
<div class="row-2">

    <!-- Trend Risiko -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3><i class="fas fa-chart-line"></i> Trend Risiko Bulanan</h3>
                <p>Identifikasi risiko 12 bulan terakhir</p>
            </div>
        </div>

        <div style="height:300px; position:relative;">
            <canvas id="chartTrend"></canvas>
        </div>
    </div>

    <!-- Distribusi Tingkat Risiko -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3><i class="fas fa-layer-group"></i> Distribusi Tingkat Risiko</h3>
                <p>Pembagian level risiko</p>
            </div>
        </div>

        <div style="height:300px; position:relative;">
            <canvas id="chartTingkat"></canvas>
        </div>
    </div>

</div>>

<!-- ===== RISIKO TERBARU & NOTIFIKASI ===== -->
<div class="row-2">
    <div class="card">
        <div class="card-header">
            <div>
                <h3><i class="fas fa-list"></i> Risiko Terbaru</h3>
                <p>5 risiko terakhir diinput</p>
            </div>
            <a href="<?= APP_URL ?>/pages/risiko.php" class="btn btn-secondary btn-sm">Lihat Semua</a>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Risiko</th>
                        <th>Tingkat</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent)): ?>
                        <tr><td colspan="4" class="text-center text-mute" style="padding:24px">Belum ada data risiko</td></tr>
                    <?php else: foreach ($recent as $r): ?>
                        <tr>
                            <td><strong><?= e($r['kode_risiko']) ?></strong></td>
                            <td>
                                <?= e($r['nama_risiko']) ?>
                                <br><small class="text-mute"><?= e($r['nama_unit']) ?></small>
                            </td>
                            <td><?= badgeTingkatRisiko($r['tingkat_risiko']) ?></td>
                            <td><?= badgeStatusMitigasi($r['status_mitigasi']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3><i class="fas fa-bell"></i> Notifikasi</h3>
                <p>Reminder & alert terbaru</p>
            </div>
        </div>

        <?php if (empty($notif)): ?>
            <div class="text-center text-mute" style="padding:24px">Tidak ada notifikasi</div>
        <?php else: foreach ($notif as $n):
            $icon = match($n['tipe']) {
                'warning'=>'fa-exclamation-triangle',
                'danger' =>'fa-exclamation-circle',
                'success'=>'fa-check-circle',
                default  =>'fa-info-circle',
            };
            $color = match($n['tipe']) {
                'warning'=>'#f59e0b',
                'danger' =>'#ef4444',
                'success'=>'#10b981',
                default  =>'#3b82f6',
            };
        ?>
            <div style="display:flex;gap:12px;padding:12px;border-bottom:1px solid var(--border-light)">
                <div style="width:36px;height:36px;border-radius:10px;background:<?= $color ?>15;color:<?= $color ?>;
                            display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="fas <?= $icon ?>"></i>
                </div>
                <div style="flex:1;min-width:0">
                    <strong style="font-size:13px"><?= e($n['judul']) ?></strong>
                    <p style="font-size:12px;color:var(--text-mute);margin:2px 0"><?= e($n['pesan']) ?></p>
                    <small class="text-mute" style="font-size:11px"><?= formatTanggal($n['created_at'], true) ?></small>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script>
// ===== CHART STATUS MITIGASI (Donut) =====
new Chart(document.getElementById('chartStatus'), {
    type: 'doughnut',
    data: {
        labels: ['Belum Dimulai', 'Dalam Proses', 'Selesai'],
        datasets: [{
            data: [<?= $status_data['belum_dimulai'] ?>, <?= $status_data['dalam_proses'] ?>, <?= $status_data['selesai'] ?>],
            backgroundColor: ['#94a3b8', '#3b82f6', '#10b981'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: true, cutout: '65%',
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
    }
});

// ===== CHART TREND =====
new Chart(document.getElementById('chartTrend'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trend_labels) ?>,
        datasets: [{
            label: 'Risiko Baru',
            data: <?= json_encode($trend_data) ?>,
            borderColor: '#0070C0', backgroundColor: 'rgba(0,112,192,.1)',
            borderWidth: 2.5, tension: 0.4, fill: true,
            pointBackgroundColor: '#002060', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 4
        }]
    },
    options: {
        responsive: true,maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// ===== CHART TINGKAT RISIKO (Bar) =====
new Chart(document.getElementById('chartTingkat'), {
    type: 'bar',
    data: {
        labels: ['Sangat Rendah', 'Rendah', 'Sedang', 'Tinggi', 'Sangat Tinggi'],
        datasets: [{
            data: [<?= $tingkat_pie['sangat_rendah'] ?>, <?= $tingkat_pie['rendah'] ?>, <?= $tingkat_pie['sedang'] ?>, <?= $tingkat_pie['tinggi'] ?>, <?= $tingkat_pie['sangat_tinggi'] ?>],
            backgroundColor: ['#10b981','#22c55e','#f59e0b','#fb923c','#ef4444'],
            borderRadius: 8, borderSkipped: false
        }]
    },
    options: {
        responsive: true,maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>