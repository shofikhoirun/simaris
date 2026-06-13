<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$user = currentUser();
$pdo = db();

// Filter
$f_unit     = $_GET['unit']     ?? '';
$f_level    = $_GET['level']    ?? '';
$f_periode  = $_GET['periode']  ?? 'semester1_' . date('Y');
$f_status   = $_GET['status']   ?? '';
$export     = $_GET['export']   ?? '';

// Parse periode
$tahun = date('Y'); $semester = 1;
if (preg_match('/semester(\d)_(\d{4})/', $f_periode, $m)) {
    $semester = (int)$m[1]; $tahun = (int)$m[2];
}
$tgl_awal  = $semester === 1 ? "$tahun-01-01" : "$tahun-07-01";
$tgl_akhir = $semester === 1 ? "$tahun-06-30" : "$tahun-12-31";

$where = ["r.created_at BETWEEN ? AND ?"]; $params = ["$tgl_awal 00:00:00","$tgl_akhir 23:59:59"];
if ($user['role']==='unit_kerja'){ $where[]='r.unit_id=?'; $params[]=$user['unit_id']; }
if ($f_unit!==''){ $where[]='r.unit_id=?'; $params[]=$f_unit; }
if ($f_level!==''){ $where[]='r.tingkat_risiko=?'; $params[]=$f_level; }
if ($f_status!==''){ $where[]='r.status_mitigasi=?'; $params[]=$f_status; }

$sql = "SELECT r.*, uk.nama_unit FROM risiko r
        LEFT JOIN unit_kerja uk ON uk.id=r.unit_id
        WHERE ".implode(' AND ',$where)."
        ORDER BY r.nilai_risiko DESC, r.kode_risiko";
$st = $pdo->prepare($sql); $st->execute($params);
$rows = $st->fetchAll();

$units = $pdo->query("SELECT id, nama_unit FROM unit_kerja ORDER BY nama_unit")->fetchAll();

// EXPORT EXCEL (HTML table dengan header Excel)
if ($export === 'excel') {
    $filename = "Laporan_Risiko_Semester{$semester}_{$tahun}.xls";
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: max-age=0');
    echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>";
    echo "<head><meta charset='UTF-8'><style>table{border-collapse:collapse;} td,th{border:1px solid #333;padding:6px;font-family:Arial;font-size:11px;} th{background:#002060;color:#fff;}</style></head><body>";
    echo "<h2 style='color:#002060;'>LAPORAN MANAJEMEN RISIKO</h2>";
    echo "<p><b>Periode:</b> Semester $semester Tahun $tahun ($tgl_awal s.d. $tgl_akhir)<br>";
    echo "<b>Dicetak:</b> " . date('d F Y H:i') . " oleh " . e($user['nama_lengkap']) . "</p>";
    echo "<table><thead><tr>
        <th>No</th><th>Kode</th><th>Unit Kerja</th><th>Nama Risiko</th><th>Penyebab</th>
        <th>Sumber</th><th>C/UC</th><th>Pengendalian</th><th>P</th><th>D</th><th>Bobot</th><th>Nilai</th>
        <th>Tingkat</th><th>Prioritas</th><th>Selera Risiko</th><th>Pilihan Penanganan</th>
        <th>Uraian RPR</th><th>Target P</th><th>Target D</th><th>Target Nilai</th>
        <th>Status Mitigasi</th><th>Progress</th><th>Status Verifikasi</th>
        </tr></thead><tbody>";
    foreach ($rows as $i => $r) {
        echo "<tr>";
        echo "<td>".($i+1)."</td>";
        echo "<td>".e($r['kode_risiko'])."</td>";
        echo "<td>".e($r['nama_unit'])."</td>";
        echo "<td>".e($r['nama_risiko'])."</td>";
        echo "<td>".e($r['penyebab'])."</td>";
        echo "<td>".e($r['sumber_risiko'])."</td>";
        echo "<td>".e($r['kategori_cuc'])."</td>";
        echo "<td>".e($r['pengendalian'])."</td>";
        echo "<td>".$r['likelihood']."</td>";
        echo "<td>".$r['impact']."</td>";
        echo "<td>".$r['bobot']."</td>";
        echo "<td>".$r['nilai_risiko']."</td>";
        echo "<td>".formatTingkatRisiko($r['tingkat_risiko'])."</td>";
        echo "<td>".$r['prioritas']."</td>";
        echo "<td>".e($r['selera_risiko'])."</td>";
        echo "<td>".e($r['pilihan_penanganan'])."</td>";
        echo "<td>".e($r['rpr_uraian'])."</td>";
        echo "<td>".$r['target_likelihood']."</td>";
        echo "<td>".$r['target_impact']."</td>";
        echo "<td>".$r['target_nilai']."</td>";
        echo "<td>".e($r['status_mitigasi'])."</td>";
        echo "<td>".$r['progress']."%</td>";
        echo "<td>".e($r['status_verifikasi'])."</td>";
        echo "</tr>";
    }
    echo "</tbody></table></body></html>";
    exit;
}

// EXPORT PDF (Print-friendly view)
if ($export === 'pdf') {
    ?><!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Laporan Risiko Cetak</title>
    <style>
        @page { size: A4 landscape; margin: 1.5cm; }
        body { font-family: 'Times New Roman', serif; font-size: 10px; color: #000; }
        h2 { color: #002060; margin: 0 0 4px; }
        .hdr { text-align: center; margin-bottom: 16px; }
        .hdr p { margin: 2px 0; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 4px; vertical-align: top; }
        th { background: #002060; color: #fff; font-size: 10px; }
        .level-sangat_tinggi { background: #dc2626; color: #fff; }
        .level-tinggi { background: #ea580c; color: #fff; }
        .level-sedang { background: #facc15; }
        .level-rendah { background: #84cc16; }
        .level-sangat_rendah { background: #22c55e; color: #fff; }
        .text-c { text-align: center; }
        .print-btn { position: fixed; top: 10px; right: 10px; padding: 8px 16px; background: #002060; color: #fff; border: 0; cursor: pointer; }
        @media print { .print-btn { display: none; } }
    </style></head><body>
    <button class="print-btn" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
    <div class="hdr">
        <h2>LAPORAN MANAJEMEN RISIKO</h2>
        <p>SIMARIS - Sistem Informasi Manajemen Risiko</p>
        <p>Periode: <b>Semester <?= $semester ?> Tahun <?= $tahun ?></b> (<?= formatTanggal($tgl_awal) ?> s.d. <?= formatTanggal($tgl_akhir) ?>)</p>
    </div>
    <table>
        <thead><tr>
            <th>No</th><th>Kode</th><th>Unit</th><th>Risiko</th><th>P</th><th>D</th><th>Nilai</th><th>Tingkat</th>
            <th>Penanganan</th><th>RPR</th><th>Progress</th><th>Status</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $i => $r): ?>
        <tr>
            <td class="text-c"><?= $i+1 ?></td>
            <td><?= e($r['kode_risiko']) ?></td>
            <td><?= e($r['nama_unit']) ?></td>
            <td><?= e($r['nama_risiko']) ?></td>
            <td class="text-c"><?= $r['likelihood'] ?></td>
            <td class="text-c"><?= $r['impact'] ?></td>
            <td class="text-c"><b><?= $r['nilai_risiko'] ?></b></td>
            <td class="text-c level-<?= $r['tingkat_risiko'] ?>"><?= formatTingkatRisiko($r['tingkat_risiko']) ?></td>
            <td><?= e($r['pilihan_penanganan']) ?></td>
            <td><?= e(mb_substr($r['rpr_uraian'],0,100)) ?></td>
            <td class="text-c"><?= $r['progress'] ?>%</td>
            <td><?= e($r['status_mitigasi']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top:30px;text-align:right;">
        Dicetak: <?= date('d F Y H:i') ?><br>
        Oleh: <?= e($user['nama_lengkap']) ?>
    </p>
    <script>window.onload = () => setTimeout(() => window.print(), 500);</script>
    </body></html><?php
    exit;
}

$pageTitle = 'Laporan';
include __DIR__.'/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-file-chart-column"></i> Laporan Manajemen Risiko</h1>
        <p class="page-subtitle">Laporan periodik sesuai KMK HK.01.07/MENKES/1354/2024</p>
    </div>
    <div>
        <a href="?<?= http_build_query(array_merge($_GET,['export'=>'excel'])) ?>" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Export Excel
        </a>
        <a href="?<?= http_build_query(array_merge($_GET,['export'=>'pdf'])) ?>" target="_blank" class="btn btn-danger">
            <i class="fas fa-file-pdf"></i> Export PDF
        </a>
    </div>
</div>

<div class="card">
    <form method="get" class="filter-bar">
        <div class="filter-item">
            <label>Periode</label>
            <select name="periode" class="form-control">
                <?php for ($y=date('Y'); $y>=date('Y')-2; $y--): ?>
                <option value="semester1_<?= $y ?>" <?= $f_periode==="semester1_$y"?'selected':'' ?>>Semester I - <?= $y ?></option>
                <option value="semester2_<?= $y ?>" <?= $f_periode==="semester2_$y"?'selected':'' ?>>Semester II - <?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="filter-item">
            <label>Unit Kerja</label>
            <select name="unit" class="form-control" <?= $user['role']==='unit_kerja'?'disabled':'' ?>>
                <option value="">— Semua —</option>
                <?php foreach ($units as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $f_unit==$u['id']?'selected':'' ?>><?= e($u['nama_unit']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <label>Tingkat Risiko</label>
            <select name="level" class="form-control">
                <option value="">— Semua —</option>
                <option value="sangat_tinggi" <?= $f_level==='sangat_tinggi'?'selected':'' ?>>Sangat Tinggi</option>
                <option value="tinggi" <?= $f_level==='tinggi'?'selected':'' ?>>Tinggi</option>
                <option value="sedang" <?= $f_level==='sedang'?'selected':'' ?>>Sedang</option>
                <option value="rendah" <?= $f_level==='rendah'?'selected':'' ?>>Rendah</option>
                <option value="sangat_rendah" <?= $f_level==='sangat_rendah'?'selected':'' ?>>Sangat Rendah</option>
            </select>
        </div>
        <div class="filter-item">
            <label>Status Mitigasi</label>
            <select name="status" class="form-control">
                <option value="">— Semua —</option>
                <option value="belum" <?= $f_status==='belum'?'selected':'' ?>>Belum</option>
                <option value="on_progress" <?= $f_status==='on_progress'?'selected':'' ?>>On Progress</option>
                <option value="selesai" <?= $f_status==='selesai'?'selected':'' ?>>Selesai</option>
            </select>
        </div>
        <div class="filter-item">
            <label>&nbsp;</label>
            <button class="btn btn-primary"><i class="fas fa-filter"></i> Tampilkan</button>
        </div>
    </form>
</div>

<!-- Summary -->
<div class="stats-grid">
    <?php
    $tot = count($rows);
    $st_count = ['sangat_tinggi'=>0,'tinggi'=>0,'sedang'=>0,'rendah'=>0,'sangat_rendah'=>0];
    $selesai = 0;
    foreach ($rows as $r) {
        $st_count[$r['tingkat_risiko']]++;
        if ($r['status_mitigasi']==='selesai') $selesai++;
    }
    ?>
    <div class="stat-card stat-blue">
        <div><div class="stat-label">Total Risiko</div><div class="stat-value"><?= $tot ?></div></div>
        <div class="stat-icon"><i class="fas fa-list"></i></div>
    </div>
    <div class="stat-card stat-red">
        <div><div class="stat-label">Sangat Tinggi</div><div class="stat-value"><?= $st_count['sangat_tinggi'] ?></div></div>
        <div class="stat-icon"><i class="fas fa-fire"></i></div>
    </div>
    <div class="stat-card stat-orange">
        <div><div class="stat-label">Tinggi</div><div class="stat-value"><?= $st_count['tinggi'] ?></div></div>
        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
    </div>
    <div class="stat-card stat-green">
        <div><div class="stat-label">Mitigasi Selesai</div><div class="stat-value"><?= $selesai ?></div></div>
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
    </div>
</div>

<div class="card">
    <h3 style="margin-bottom:12px"><i class="fas fa-table"></i> Tabel Laporan Lengkap</h3>
    <div class="table-responsive">
    <table class="data-table">
        <thead>
        <tr>
            <th>No</th><th>Kode</th><th>Unit</th><th>Nama Risiko</th><th>P</th><th>D</th><th>Nilai</th>
            <th>Tingkat</th><th>Penanganan</th><th>Progress</th><th>Status</th><th>Verifikasi</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="12" class="text-center text-muted">Tidak ada data dalam periode ini.</td></tr>
        <?php else: foreach ($rows as $i => $r): ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= e($r['kode_risiko']) ?></strong></td>
            <td><?= e($r['nama_unit']) ?></td>
            <td><?= e($r['nama_risiko']) ?></td>
            <td class="text-center"><?= $r['likelihood'] ?></td>
            <td class="text-center"><?= $r['impact'] ?></td>
            <td class="text-center"><strong><?= $r['nilai_risiko'] ?></strong></td>
            <td><?= badgeTingkatRisiko($r['tingkat_risiko']) ?></td>
            <td><?= e($r['pilihan_penanganan']) ?></td>
            <td>
                <div class="progress-bar"><div class="progress-fill" style="width:<?= $r['progress'] ?>%"></div></div>
                <small><?= $r['progress'] ?>%</small>
            </td>
            <td><?= badgeStatusMitigasi($r['status_mitigasi']) ?></td>
            <td><?= badgeStatusVerifikasi($r['status_verifikasi']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
