<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);
$user = currentUser();
$pdo = db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!verifyCsrf($_POST['csrf']??'')) { flash('error','Token tidak valid.'); header('Location: users.php'); exit; }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id   = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username']);
        $email    = trim($_POST['email']);
        $nama     = trim($_POST['nama_lengkap']);
        $role     = $_POST['role'];
        $unit_id  = $_POST['unit_id'] ?: null;
        $status   = $_POST['status'];
        $password = $_POST['password'] ?? '';

        if ($id) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET username=?, email=?, nama_lengkap=?, role=?, unit_id=?, status=?, password=?, updated_at=NOW() WHERE id=?")
                    ->execute([$username,$email,$nama,$role,$unit_id,$status,$hash,$id]);
            } else {
                $pdo->prepare("UPDATE users SET username=?, email=?, nama_lengkap=?, role=?, unit_id=?, status=?, updated_at=NOW() WHERE id=?")
                    ->execute([$username,$email,$nama,$role,$unit_id,$status,$id]);
            }
            logAudit('UPDATE','users',$id,"Update user $username");
            flash('success','User berhasil diperbarui.');
        } else {
            $hash = password_hash($password ?: 'password123', PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (username,email,nama_lengkap,role,unit_id,status,password,created_at) VALUES (?,?,?,?,?,?,?,NOW())")
                ->execute([$username,$email,$nama,$role,$unit_id,$status,$hash]);
            $newId = $pdo->lastInsertId();
            logAudit('CREATE','users',$newId,"Tambah user $username");
            flash('success','User baru berhasil ditambahkan.');
        }
        header('Location: users.php'); exit;
    }
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === $user['id']) { flash('error','Anda tidak bisa menghapus akun sendiri.'); }
        else {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            logAudit('DELETE','users',$id,"Hapus user #$id");
            flash('success','User berhasil dihapus.');
        }
        header('Location: users.php'); exit;
    }
    if ($action === 'reset') {
        $id = (int)$_POST['id'];
        $hash = password_hash('password123', PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash,$id]);
        logAudit('RESET','users',$id,"Reset password user #$id");
        flash('success','Password berhasil di-reset ke <code>password123</code>');
        header('Location: users.php'); exit;
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}

$rows = $pdo->query("SELECT u.*, uk.nama_unit FROM users u LEFT JOIN unit_kerja uk ON uk.id=u.unit_id ORDER BY u.role, u.nama_lengkap")->fetchAll();
$units = $pdo->query("SELECT id, nama_unit FROM unit_kerja ORDER BY nama_unit")->fetchAll();

$pageTitle = 'Manajemen User';
include __DIR__.'/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-users-cog"></i> Manajemen User</h1>
        <p class="page-subtitle">Pengelolaan akun pengguna dan hak akses sistem</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalUser')"><i class="fas fa-user-plus"></i> Tambah User</button>
</div>

<div class="card">
    <div class="table-responsive">
    <table class="data-table">
        <thead>
        <tr>
            <th>No</th><th>Username</th><th>Nama Lengkap</th><th>Email</th><th>Role</th>
            <th>Unit Kerja</th><th>Status</th><th>Login Terakhir</th><th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php $no=1; foreach ($rows as $r): ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><strong><?= e($r['username']) ?></strong></td>
            <td><?= e($r['nama_lengkap']) ?></td>
            <td><?= e($r['email']) ?></td>
            <td><span class="badge badge-info"><?= formatRole($r['role']) ?></span></td>
            <td><?= e($r['nama_unit'] ?? '-') ?></td>
            <td><?= $r['status']==='aktif' ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-danger">Non-aktif</span>' ?></td>
            <td><?= $r['last_login'] ? formatTanggal($r['last_login'],true) : '<span class="text-muted">Belum pernah</span>' ?></td>
            <td>
                <a href="?edit=<?= $r['id'] ?>" class="btn-icon" title="Edit"><i class="fas fa-edit"></i></a>
                <form method="post" style="display:inline" onsubmit="return confirm('Reset password user ini ke <password123>?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button class="btn-icon" title="Reset Password"><i class="fas fa-key"></i></button>
                </form>
                <?php if ($r['id'] != $user['id']): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Hapus user ini?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button class="btn-icon btn-icon-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="modal-overlay <?= $edit?'active':'' ?>" id="modalUser">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <h3><i class="fas fa-user"></i> <?= $edit?'Edit':'Tambah' ?> User</h3>
            <button class="modal-close" onclick="location.href='users.php'">&times;</button>
        </div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $edit['id']??'' ?>">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" class="form-control" required value="<?= e($edit['username']??'') ?>">
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" required value="<?= e($edit['email']??'') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap *</label>
                    <input type="text" name="nama_lengkap" class="form-control" required value="<?= e($edit['nama_lengkap']??'') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Role *</label>
                        <select name="role" class="form-control" required>
                            <?php foreach (['admin','unit_kerja','verifikator','pimpinan'] as $rl): ?>
                            <option value="<?= $rl ?>" <?= ($edit['role']??'')===$rl?'selected':'' ?>><?= formatRole($rl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Unit Kerja</label>
                        <select name="unit_id" class="form-control">
                            <option value="">— Tanpa Unit —</option>
                            <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($edit['unit_id']??'')==$u['id']?'selected':'' ?>><?= e($u['nama_unit']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Password <?= $edit?'(kosongkan jika tidak diganti)':'*' ?></label>
                        <input type="password" name="password" class="form-control" <?= $edit?'':'required' ?> minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" class="form-control" required>
                            <option value="aktif" <?= ($edit['status']??'aktif')==='aktif'?'selected':'' ?>>Aktif</option>
                            <option value="nonaktif" <?= ($edit['status']??'')==='nonaktif'?'selected':'' ?>>Non-aktif</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="location.href='users.php'">Batal</button>
                <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
