<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin','verifikator']);
$user = currentUser();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { flash('error','Token tidak valid.'); header('Location: verifikasi.php'); exit; }
    $id = (int)$_POST['id'];
    $aksi = $_POST['aksi']; // approve / reject
    $catatan = trim($_POST['catatan'] ?? '');
    $status = $aksi === 'approve' ? 'disetujui' : 'ditolak';

    $pdo->prepare("UPDATE risiko SET status_verifikasi=?, verifikator_id=?, tanggal_verifikasi=NOW(), catatan_verifikasi=? WHERE id=?")
        ->execute([$status, $user['id'], $catatan, $id]);

    // Get owner risiko
    $st = $pdo->prepare("SELECT r.kode_risiko, r.nama_risiko, u.id AS owner_id FROM risiko r LEFT JOIN users u ON u.unit_id = r.unit_id AND u.role='unit_kerja' WHERE r.id=? LIMIT 1");
    $st->execute([$id]); $r = $st->fetch();
    if ($r && $r['owner_id']) {
        buatNotifikasi($r['owner_id'], "Verifikasi $status", "Risiko {$r['kode_risiko']} telah $status oleh verifikator.", $aksi==='approve'?'success':'warning', "pages/profil_risiko.php?id=$id");
    }
    logAudit('VERIFY','risiko',$id,"Risiko $status oleh ".$user['nama_lengkap']);
    flash('success', "Risiko berhasil $status.");
    header('Location: verifikasi.php'); exit;
}

// Filter
$f_status = $_GET['status'] ?? 'menunggu';
$where = ['1=1']; $params = [];
if ($f_status !== 'all') { $where[]='r.status_verifikasi=?'; $params[]=$f_status; }

$sql = "SELECT r.*, uk.nama_unit, v.nama_lengkap AS verifikator_nama
        FROM risiko r
        LEFT JOIN unit_kerja uk ON uk.id=r.unit_id
        LEFT JOIN users v ON v.id=r.verifikator_id
        WHERE ".implode(' AND ',$where)."
        ORDER BY r.created_at DESC";
$st = $pdo->prepare($sql); $st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Verifikasi Risiko';
include __DIR__.'/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-clipboard-check"></i> Verifikasi Risiko</h1>
        <p class="page-subtitle">Validasi dan persetujuan data risiko yang diinput unit kerja</p>
    </div>
</div>

<div class="filter-tabs">
    <a href="?status=menunggu" class="filter-tab <?= $f_status==='menunggu'?'active':'' ?>">
        <i class="fas fa-clock"></i> Menunggu
    </a>
    <a href="?status=disetujui" class="filter-tab <?= $f_status==='disetujui'?'active':'' ?>">
        <i class="fas fa-check-circle"></i> Disetujui
    </a>
    <a href="?status=ditolak" class="filter-tab <?= $f_status==='ditolak'?'active':'' ?>">
        <i class="fas fa-times-circle"></i> Ditolak
    </a>
    <a href="?status=all" class="filter-tab <?= $f_status==='all'?'active':'' ?>">
        <i class="fas fa-list"></i> Semua
    </a>
</div>

<div class="card">
    <div class="table-responsive">
    <table class="data-table">
        <thead>
        <tr>
            <th>Kode</th>
            <th>Risiko</th>
            <th>Unit</th>
            <th>P × D</th>
            <th>Nilai</th>
            <th>Tingkat</th>
            <th>Status</th>
            <th>Verifikator</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="text-center text-muted">Tidak ada data untuk status ini.</td></tr>
        <?php else: foreach ($rows as $r): ?>
        <tr>
            <td><strong><?= e($r['kode_risiko']) ?></strong></td>
            <td><?= e($r['nama_risiko']) ?></td>
            <td><?= e($r['nama_unit']) ?></td>
            <td><?= $r['likelihood'] ?> × <?= $r['impact'] ?></td>
            <td><strong><?= $r['nilai_risiko'] ?></strong></td>
            <td><?= badgeTingkatRisiko($r['tingkat_risiko']) ?></td>
            <td><?= badgeStatusVerifikasi($r['status_verifikasi']) ?></td>
            <td><?= e($r['verifikator_nama'] ?? '-') ?></td>
            <td>
                <a href="profil_risiko.php?id=<?= $r['id'] ?>" class="btn-icon" title="Detail"><i class="fas fa-eye"></i></a>
                <?php if ($r['status_verifikasi']==='menunggu'): ?>
                <button class="btn-icon btn-icon-success" title="Setujui"
                    onclick="openVerif(<?= $r['id'] ?>,'approve','<?= e($r['kode_risiko']) ?>')">
                    <i class="fas fa-check"></i>
                </button>
                <button class="btn-icon btn-icon-danger" title="Tolak"
                    onclick="openVerif(<?= $r['id'] ?>,'reject','<?= e($r['kode_risiko']) ?>')">
                    <i class="fas fa-times"></i>
                </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="modal-overlay" id="modalVerif">
    <div class="modal" style="max-width:500px">
        <div class="modal-header">
            <h3 id="vTitle">Verifikasi Risiko</h3>
            <button class="modal-close" onclick="closeModal('modalVerif')">&times;</button>
        </div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="id" id="vId">
            <input type="hidden" name="aksi" id="vAksi">
            <div class="modal-body">
                <p>Anda akan memberikan keputusan untuk risiko <strong id="vKode"></strong></p>
                <div class="form-group">
                    <label>Catatan Verifikasi</label>
                    <textarea name="catatan" class="form-control" rows="4" placeholder="Tulis catatan, alasan, atau rekomendasi..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalVerif')">Batal</button>
                <button class="btn btn-primary" id="vSubmit">Konfirmasi</button>
            </div>
        </form>
    </div>
</div>

<script>
function openVerif(id, aksi, kode){
    document.getElementById('vId').value = id;
    document.getElementById('vAksi').value = aksi;
    document.getElementById('vKode').textContent = kode;
    document.getElementById('vTitle').textContent = (aksi==='approve'?'Setujui':'Tolak')+' Risiko';
    const btn = document.getElementById('vSubmit');
    btn.className = 'btn '+(aksi==='approve'?'btn-success':'btn-danger');
    btn.innerHTML = '<i class="fas fa-'+(aksi==='approve'?'check':'times')+'"></i> '+(aksi==='approve'?'Setujui':'Tolak');
    openModal('modalVerif');
}
</script>

<?php include __DIR__.'/../includes/footer.php'; ?>
