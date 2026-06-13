<?php
$page_title = 'Profil Risiko';
$page_subtitle = 'Detail Informasi Risiko & Riwayat';
$current_page = 'profil';
require_once __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    // Detail satu risiko
    $stmt = $pdo->prepare("
        SELECT r.*, u.nama_unit, usr.nama_lengkap AS pj_nama, ver.nama_lengkap AS verif_nama
        FROM risiko r
        LEFT JOIN unit_kerja u ON u.id = r.unit_id
        LEFT JOIN users usr   ON usr.id = r.pj_id
        LEFT JOIN users ver   ON ver.id = r.verifikator_id
        WHERE r.id = ?");
    $stmt->execute([$id]);
    $r = $stmt->fetch();

    if (!$r) {
        flash('danger', 'Data risiko tidak ditemukan');
        header('Location: profil_risiko.php');
        exit;
    }

    // Riwayat tindak lanjut
    $tl = $pdo->prepare("SELECT t.*, u.nama_lengkap FROM tindak_lanjut t
        LEFT JOIN users u ON u.id = t.user_id
        WHERE t.risiko_id=? ORDER BY t.tanggal DESC");
    $tl->execute([$id]);
    $tindak = $tl->fetchAll();

    // Riwayat pemantauan
    $pm = $pdo->prepare("SELECT p.*, u.nama_lengkap FROM pemantauan p
        LEFT JOIN users u ON u.id = p.user_id
        WHERE p.risiko_id=? ORDER BY p.tanggal DESC");
    $pm->execute([$id]);
    $pemantauan = $pm->fetchAll();
?>

<div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <i class="fas fa-chevron-right"></i>
    <a href="profil_risiko.php">Profil Risiko</a>
    <i class="fas fa-chevron-right"></i>
    <span><?= e($r['kode_risiko']) ?></span>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:24px">
        <div>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                <span class="badge badge-info" style="font-size:13px;padding:6px 12px"><?= e($r['kode_risiko']) ?></span>
                <?= badgeTingkatRisiko($r['tingkat_risiko']) ?>
                <?= badgeStatusMitigasi($r['status_mitigasi']) ?>
                <?= badgeStatusVerifikasi($r['status_verifikasi']) ?>
            </div>
            <h2 style="color:var(--primary);margin-bottom:6px"><?= e($r['nama_risiko']) ?></h2>
            <p class="text-mute"><i class="fas fa-building"></i> <?= e($r['nama_unit']) ?></p>
        </div>
        <div style="display:flex;gap:8px">
            <a href="risiko.php?edit=<?= $r['id'] ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit</a>
            <a href="tindak_lanjut.php?risiko=<?= $r['id'] ?>" class="btn btn-success"><i class="fas fa-plus"></i> Tindak Lanjut</a>
        </div>
    </div>

    <!-- INFO PANEL -->
    <div class="row-2">
        <div>
            <h4 style="color:var(--primary-light);margin-bottom:12px"><i class="fas fa-info-circle"></i> Identifikasi</h4>
            <table style="width:100%;font-size:13px">
                <tr><td style="padding:6px 0;color:var(--text-mute);width:40%">Penyebab</td>
                    <td style="padding:6px 0"><?= nl2br(e($r['penyebab'])) ?></td></tr>
                <tr><td style="padding:6px 0;color:var(--text-mute)">Sumber</td>
                    <td style="padding:6px 0"><?= ucfirst($r['sumber_risiko']) ?> (<?= $r['kategori_cuc'] ?>)</td></tr>
                <tr><td style="padding:6px 0;color:var(--text-mute)">Dampak</td>
                    <td style="padding:6px 0"><?= nl2br(e($r['dampak_uraian'])) ?></td></tr>
            </table>
        </div>
        <div>
            <h4 style="color:var(--primary-light);margin-bottom:12px"><i class="fas fa-chart-bar"></i> Analisis</h4>
            <table style="width:100%;font-size:13px">
                <tr><td style="padding:6px 0;color:var(--text-mute);width:40%">Pengendalian</td>
                    <td style="padding:6px 0"><?= nl2br(e($r['pengendalian'] ?: '-')) ?></td></tr>
                <tr><td style="padding:6px 0;color:var(--text-mute)">Efektivitas</td>
                    <td style="padding:6px 0"><?= $r['efektivitas']=='efektif'?'<span class="badge badge-success">Efektif</span>':'<span class="badge badge-warning">Tidak Efektif</span>' ?></td></tr>
                <tr><td style="padding:6px 0;color:var(--text-mute)">P × D × Bobot</td>
                    <td style="padding:6px 0"><strong><?= $r['likelihood'] ?> × <?= $r['impact'] ?> × <?= $r['bobot'] ?> = <?= $r['nilai_risiko'] ?></strong></td></tr>
                <tr><td style="padding:6px 0;color:var(--text-mute)">Prioritas</td>
                    <td style="padding:6px 0">#<?= $r['prioritas'] ?></td></tr>
            </table>
        </div>
    </div>

    <hr style="margin:20px 0;border:none;border-top:1px solid var(--border-light)">

    <div class="row-2">
        <div>
            <h4 style="color:var(--primary-light);margin-bottom:12px"><i class="fas fa-balance-scale"></i> Evaluasi & Penanganan</h4>
            <table style="width:100%;font-size:13px">
                <tr><td style="padding:6px 0;color:var(--text-mute);width:40%">Selera Risiko</td>
                    <td style="padding:6px 0"><?= $r['selera_risiko']=='dalam_batas'?'<span class="badge badge-success">Dalam Batas</span>':'<span class="badge badge-warning">Di Atas Batas</span>' ?></td></tr>
                <tr><td style="padding:6px 0;color:var(--text-mute)">Pilihan Penanganan</td>
                    <td style="padding:6px 0"><?= ucfirst(str_replace('_',' ',$r['pilihan_penanganan'])) ?> Risiko</td></tr>
                <tr><td style="padding:6px 0;color:var(--text-mute)">Rencana</td>
                    <td style="padding:6px 0"><?= nl2br(e($r['rpr_uraian'] ?: '-')) ?></td></tr>
                <tr><td style="padding:6px 0;color:var(--text-mute)">Jadwal</td>
                    <td style="padding:6px 0"><?= formatTanggal($r['jadwal_mulai']) ?> s/d <?= formatTanggal($r['jadwal_selesai']) ?></td></tr>
            </table>
        </div>
        <div>
            <h4 style="color:var(--primary-light);margin-bottom:12px"><i class="fas fa-bullseye"></i> Target Penurunan</h4>
            <table style="width:100%;font-size:13px">
                <?php if ($r['target_nilai_risiko']): ?>
                <tr><td style="padding:6px 0;color:var(--text-mute);width:40%">Target P × D × Bobot</td>
                    <td style="padding:6px 0"><strong><?= $r['target_likelihood'] ?> × <?= $r['target_impact'] ?> × <?= $r['target_bobot'] ?> = <?= $r['target_nilai_risiko'] ?></strong></td></tr>
                <tr><td style="padding:6px 0;color:var(--text-mute)">Target Tingkat</td>
                    <td style="padding:6px 0"><?= badgeTingkatRisiko($r['target_tingkat_risiko']) ?></td></tr>
                <tr><td style="padding:6px 0;color:var(--text-mute)">Penurunan</td>
                    <td style="padding:6px 0;color:var(--success)">
                        <i class="fas fa-arrow-down"></i> <?= round((($r['nilai_risiko']-$r['target_nilai_risiko'])/$r['nilai_risiko'])*100) ?>%
                    </td></tr>
                <?php else: ?>
                <tr><td colspan="2" class="text-mute" style="padding:6px 0">Target penurunan belum ditetapkan</td></tr>
                <?php endif; ?>
                <tr><td style="padding:6px 0;color:var(--text-mute)">Progress Mitigasi</td>
                    <td style="padding:6px 0">
                        <div class="progress"><div class="progress-bar" style="width:<?= $r['progress'] ?>%"><?= $r['progress'] ?>%</div></div>
                    </td></tr>
            </table>
        </div>
    </div>
</div>

<!-- RIWAYAT TINDAK LANJUT -->
<div class="card">
    <div class="card-header">
        <div>
            <h3><i class="fas fa-tasks"></i> Riwayat Tindak Lanjut</h3>
            <p><?= count($tindak) ?> update tindak lanjut</p>
        </div>
    </div>

    <?php if (empty($tindak)): ?>
        <div class="text-center text-mute" style="padding:24px">Belum ada tindak lanjut</div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tanggal</th><th>Uraian</th><th>Progress</th><th>Status</th><th>Oleh</th></tr></thead>
            <tbody>
            <?php foreach ($tindak as $t): ?>
                <tr>
                    <td><?= formatTanggal($t['tanggal']) ?></td>
                    <td><?= e($t['uraian']) ?></td>
                    <td><strong><?= $t['progress'] ?>%</strong></td>
                    <td><?= badgeStatusMitigasi($t['status']) ?></td>
                    <td><?= e($t['nama_lengkap']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- RIWAYAT PEMANTAUAN -->
<div class="card">
    <div class="card-header">
        <div>
            <h3><i class="fas fa-eye"></i> Riwayat Pemantauan & Reviu</h3>
            <p><?= count($pemantauan) ?> hasil pemantauan</p>
        </div>
        <a href="pemantauan.php?risiko=<?= $r['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Pemantauan Baru</a>
    </div>

    <?php if (empty($pemantauan)): ?>
        <div class="text-center text-mute" style="padding:24px">Belum ada pemantauan</div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tanggal</th><th>P</th><th>D</th><th>Bobot</th><th>Nilai</th><th>Tingkat</th><th>Simpulan</th><th>Efektivitas</th></tr></thead>
            <tbody>
            <?php foreach ($pemantauan as $p): ?>
                <tr>
                    <td><?= formatTanggal($p['tanggal']) ?></td>
                    <td><?= $p['likelihood_post'] ?></td>
                    <td><?= $p['impact_post'] ?></td>
                    <td><?= $p['bobot_post'] ?></td>
                    <td><strong><?= $p['nilai_post'] ?></strong></td>
                    <td><?= badgeTingkatRisiko($p['tingkat_post']) ?></td>
                    <td><?= str_replace('_',' ',ucfirst($p['simpulan'])) ?></td>
                    <td><?= $p['efektivitas']=='efektif' ? '<span class="badge badge-success">Efektif</span>' : '<span class="badge badge-warning">Tidak Efektif</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
} else {
    // List semua profil risiko
    $where = $user['role'] === 'unit_kerja' ? "WHERE r.unit_id = {$user['unit_id']}" : '';
    $list = $pdo->query("
        SELECT r.*, u.nama_unit FROM risiko r
        LEFT JOIN unit_kerja u ON u.id = r.unit_id
        $where ORDER BY r.prioritas ASC")->fetchAll();
?>
<div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <i class="fas fa-chevron-right"></i>
    <span>Profil Risiko</span>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h3><i class="fas fa-file-alt"></i> Profil Risiko Organisasi</h3>
            <p>Tabel profil risiko sesuai format kertas kerja</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Unit Kerja</th>
                    <th>Risiko</th>
                    <th>Kode</th>
                    <th>P</th><th>D</th><th>Bobot</th><th>Nilai</th>
                    <th>Tingkat</th><th>Prioritas</th>
                    <th>Pengendalian</th>
                    <th>Jadwal</th>
                    <th>Target P</th><th>Target D</th><th>Target Nilai</th><th>Target Tingkat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($list as $i => $r): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= e($r['nama_unit']) ?></td>
                    <td><strong><?= e($r['nama_risiko']) ?></strong></td>
                    <td><?= e($r['kode_risiko']) ?></td>
                    <td><?= $r['likelihood'] ?></td>
                    <td><?= $r['impact'] ?></td>
                    <td><?= $r['bobot'] ?></td>
                    <td><strong><?= $r['nilai_risiko'] ?></strong></td>
                    <td><?= badgeTingkatRisiko($r['tingkat_risiko']) ?></td>
                    <td>#<?= $r['prioritas'] ?></td>
                    <td style="max-width:200px;font-size:12px"><?= e(mb_substr($r['pengendalian']??'-',0,60)) ?></td>
                    <td style="font-size:12px"><?= formatTanggal($r['jadwal_mulai']) ?> - <?= formatTanggal($r['jadwal_selesai']) ?></td>
                    <td><?= $r['target_likelihood'] ?: '-' ?></td>
                    <td><?= $r['target_impact'] ?: '-' ?></td>
                    <td><?= $r['target_nilai_risiko'] ?: '-' ?></td>
                    <td><?= $r['target_tingkat_risiko'] ? badgeTingkatRisiko($r['target_tingkat_risiko']) : '-' ?></td>
                    <td><a href="?id=<?= $r['id'] ?>" class="btn btn-primary btn-icon"><i class="fas fa-eye"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php } ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
