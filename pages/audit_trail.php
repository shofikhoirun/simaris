<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);
$pdo = db();

// Filter
$f_user  = $_GET['user']  ?? '';
$f_tabel = $_GET['tabel'] ?? '';
$f_aksi  = $_GET['aksi']  ?? '';
$f_dari  = $_GET['dari']  ?? date('Y-m-d', strtotime('-30 days'));
$f_sampai= $_GET['sampai']?? date('Y-m-d');

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page-1) * $per_page;

$where = ['DATE(at.created_at) BETWEEN ? AND ?']; $params = [$f_dari, $f_sampai];
if ($f_user!=='') { $where[]='at.user_id=?'; $params[]=$f_user; }
if ($f_tabel!=='') { $where[]='at.tabel=?'; $params[]=$f_tabel; }
if ($f_aksi!=='') { $where[]='at.aksi=?'; $params[]=$f_aksi; }
$wsql = implode(' AND ', $where);

$tot = $pdo->prepare("SELECT COUNT(*) FROM audit_trail at WHERE $wsql");
$tot->execute($params);
$total = $tot->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));

$st = $pdo->prepare("SELECT at.*, u.username, u.nama_lengkap FROM audit_trail at
    LEFT JOIN users u ON u.id=at.user_id
    WHERE $wsql
    ORDER BY at.created_at DESC LIMIT $per_page OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll();

$users = $pdo->query("SELECT id, username, nama_lengkap FROM users ORDER BY nama_lengkap")->fetchAll();
$tabels = $pdo->query("SELECT DISTINCT tabel FROM audit_trail ORDER BY tabel")->fetchAll(PDO::FETCH_COLUMN);
$aksis  = $pdo->query("SELECT DISTINCT aksi FROM audit_trail ORDER BY aksi")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Audit Trail';
include __DIR__.'/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-history"></i> Audit Trail</h1>
        <p class="page-subtitle">Catatan seluruh aktivitas pengguna dalam sistem</p>
    </div>
</div>

<div class="card">
    <form method="get" class="filter-bar">
        <div class="filter-item">
            <label>Dari</label>
            <input type="date" name="dari" class="form-control" value="<?= e($f_dari) ?>">
        </div>
        <div class="filter-item">
            <label>Sampai</label>
            <input type="date" name="sampai" class="form-control" value="<?= e($f_sampai) ?>">
        </div>
        <div class="filter-item">
            <label>User</label>
            <select name="user" class="form-control">
                <option value="">— Semua —</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $f_user==$u['id']?'selected':'' ?>><?= e($u['nama_lengkap']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <label>Tabel</label>
            <select name="tabel" class="form-control">
                <option value="">— Semua —</option>
                <?php foreach ($tabels as $t): ?>
                <option value="<?= e($t) ?>" <?= $f_tabel===$t?'selected':'' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <label>Aksi</label>
            <select name="aksi" class="form-control">
                <option value="">— Semua —</option>
                <?php foreach ($aksis as $a): ?>
                <option value="<?= e($a) ?>" <?= $f_aksi===$a?'selected':'' ?>><?= e($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <label>&nbsp;</label>
            <button class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
            <a href="audit_trail.php" class="btn btn-outline">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div style="margin-bottom:8px;color:var(--text-muted)">Menampilkan <?= count($rows) ?> dari <?= $total ?> entri</div>
    <div class="table-responsive">
    <table class="data-table">
        <thead>
        <tr>
            <th>Waktu</th><th>User</th><th>Aksi</th><th>Tabel</th><th>Record ID</th>
            <th>Deskripsi</th><th>IP Address</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="text-center text-muted">Tidak ada data dalam rentang waktu ini.</td></tr>
        <?php else: foreach ($rows as $r): ?>
        <tr>
            <td><small><?= formatTanggal($r['created_at'],true) ?></small></td>
            <td><?= e($r['nama_lengkap'] ?? 'System') ?></td>
            <td>
                <?php
                $map = ['CREATE'=>'badge-success','UPDATE'=>'badge-info','DELETE'=>'badge-danger','LOGIN'=>'badge-secondary','LOGOUT'=>'badge-secondary','VERIFY'=>'badge-warning','RESET'=>'badge-warning'];
                $cls = $map[$r['aksi']] ?? 'badge-info';
                echo "<span class='badge $cls'>".e($r['aksi'])."</span>";
                ?>
            </td>
            <td><code><?= e($r['tabel']) ?></code></td>
            <td><?= $r['record_id'] ?: '-' ?></td>
            <td><?= e($r['deskripsi']) ?></td>
            <td><small><?= e($r['ip_address']) ?></small></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php
        $qs = $_GET; unset($qs['page']);
        $base = '?'.http_build_query($qs).'&page=';
        ?>
        <?php if ($page > 1): ?>
            <a href="<?= $base.($page-1) ?>" class="btn btn-outline">&laquo; Sebelumnya</a>
        <?php endif; ?>
        <span style="padding:8px 12px">Halaman <?= $page ?> / <?= $total_pages ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="<?= $base.($page+1) ?>" class="btn btn-outline">Selanjutnya &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
