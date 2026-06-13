<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!verifyCsrf($_POST['csrf']??'')) { flash('error','Token tidak valid.'); header('Location: unit.php'); exit; }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id']??0);
        $kode = trim($_POST['kode_unit']);
        $nama = trim($_POST['nama_unit']);
        $desk = trim($_POST['deskripsi'] ?? '');
        if ($id) {
            $pdo->prepare("UPDATE unit_kerja SET kode_unit=?, nama_unit=?, deskripsi=? WHERE id=?")
                ->execute([$kode,$nama,$desk,$id]);
            logAudit('UPDATE','unit_kerja',$id,"Update unit $nama");
            flash('success','Unit kerja berhasil diperbarui.');
        } else {
            $pdo->prepare("INSERT INTO unit_kerja (kode_unit,nama_unit,deskripsi) VALUES (?,?,?)")
                ->execute([$kode,$nama,$desk]);
            logAudit('CREATE','unit_kerja',$pdo->lastInsertId(),"Tambah unit $nama");
            flash('success','Unit kerja baru berhasil ditambahkan.');
        }
        header('Location: unit.php'); exit;
    }
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $pdo->prepare("DELETE FROM unit_kerja WHERE id=?")->execute([$id]);
            logAudit('DELETE','unit_kerja',$id,"Hapus unit #$id");
            flash('success','Unit kerja berhasil dihapus.');
        } catch (Exception $e) {
            flash('error','Tidak bisa menghapus: masih ada user/risiko yang terkait dengan unit ini.');
        }
        header('Location: unit.php'); exit;
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM unit_kerja WHERE id=?"); $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}

$rows = $pdo->query("
    SELECT uk.*,
        (SELECT COUNT(*) FROM users WHERE unit_id=uk.id) AS jml_user,
        (SELECT COUNT(*) FROM risiko WHERE unit_id=uk.id) AS jml_risiko
    FROM unit_kerja uk ORDER BY uk.kode_unit")->fetchAll();

$pageTitle = 'Unit Kerja';
include __DIR__.'/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-building"></i> Manajemen Unit Kerja</h1>
        <p class="page-subtitle">Pengelolaan struktur unit kerja organisasi</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalUnit')"><i class="fas fa-plus"></i> Tambah Unit</button>
</div>

<div class="card">
    <div class="table-responsive">
    <table class="data-table">
        <thead>
        <tr>
            <th>No</th><th>Kode</th><th>Nama Unit</th><th>Deskripsi</th>
            <th class="text-center">Jml User</th><th class="text-center">Jml Risiko</th><th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php $no=1; foreach ($rows as $r): ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><strong><?= e($r['kode_unit']) ?></strong></td>
            <td><?= e($r['nama_unit']) ?></td>
            <td><?= e($r['deskripsi']) ?></td>
            <td class="text-center"><?= $r['jml_user'] ?></td>
            <td class="text-center"><?= $r['jml_risiko'] ?></td>
            <td>
                <a href="?edit=<?= $r['id'] ?>" class="btn-icon" title="Edit"><i class="fas fa-edit"></i></a>
                <form method="post" style="display:inline" onsubmit="return confirm('Hapus unit ini?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button class="btn-icon btn-icon-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="modal-overlay <?= $edit?'active':'' ?>" id="modalUnit">
    <div class="modal" style="max-width:500px">
        <div class="modal-header">
            <h3><i class="fas fa-building"></i> <?= $edit?'Edit':'Tambah' ?> Unit Kerja</h3>
            <button class="modal-close" onclick="location.href='unit.php'">&times;</button>
        </div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $edit['id']??'' ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Kode Unit *</label>
                    <input type="text" name="kode_unit" class="form-control" required value="<?= e($edit['kode_unit']??'') ?>" placeholder="Contoh: BAG-KEU">
                </div>
                <div class="form-group">
                    <label>Nama Unit *</label>
                    <input type="text" name="nama_unit" class="form-control" required value="<?= e($edit['nama_unit']??'') ?>">
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="3"><?= e($edit['deskripsi']??'') ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="location.href='unit.php'">Batal</button>
                <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
