<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$user = currentUser();
$pdo = db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!verifyCsrf($_POST['csrf']??'')) { flash('error','Token tidak valid.'); header('Location: profil.php'); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $nama  = trim($_POST['nama_lengkap']);
        $email = trim($_POST['email']);

        // Handle photo upload
        $foto = $user['foto'];
        if (!empty($_FILES['foto']['name'])) {
            $upload_dir = __DIR__ . '/../uploads/avatars/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif'])) {
                $fname = 'user_'.$user['id'].'_'.time().'.'.$ext;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir.$fname)) {
                    $foto = $fname;
                }
            }
        }

        $pdo->prepare("UPDATE users SET nama_lengkap=?, email=?, foto=?, updated_at=NOW() WHERE id=?")
            ->execute([$nama,$email,$foto,$user['id']]);
        $_SESSION['user']['nama_lengkap'] = $nama;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['foto'] = $foto;
        logAudit('UPDATE','users',$user['id'],"Update profil sendiri");
        flash('success','Profil berhasil diperbarui.');
        header('Location: profil.php'); exit;
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'];
        $new     = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        $st = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $st->execute([$user['id']]);
        $hash = $st->fetchColumn();

        if (!password_verify($current, $hash)) {
            flash('error','Password lama tidak cocok.');
        } elseif ($new !== $confirm) {
            flash('error','Konfirmasi password tidak sesuai.');
        } elseif (strlen($new) < 6) {
            flash('error','Password minimal 6 karakter.');
        } else {
            $new_hash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password=?, updated_at=NOW() WHERE id=?")
                ->execute([$new_hash,$user['id']]);
            logAudit('UPDATE','users',$user['id'],"Ubah password sendiri");
            flash('success','Password berhasil diubah.');
        }
        header('Location: profil.php'); exit;
    }
}

// Re-load user data
$st = $pdo->prepare("SELECT u.*, uk.nama_unit FROM users u LEFT JOIN unit_kerja uk ON uk.id=u.unit_id WHERE u.id=?");
$st->execute([$user['id']]);
$me = $st->fetch();

$pageTitle = 'Profil Saya';
include __DIR__.'/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-user-circle"></i> Profil Saya</h1>
        <p class="page-subtitle">Kelola informasi akun dan keamanan</p>
    </div>
</div>

<div class="grid-2">
    <!-- Card Info Profil -->
    <div class="card">
        <h3 style="margin-bottom:16px"><i class="fas fa-id-card"></i> Informasi Profil</h3>
        <form method="post" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_profile">

            <div style="text-align:center;margin-bottom:20px">
                <?php if (!empty($me['foto']) && file_exists(__DIR__.'/../uploads/avatars/'.$me['foto'])): ?>
                    <img src="<?= APP_URL ?>/uploads/avatars/<?= e($me['foto']) ?>" alt="" style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:4px solid var(--primary-light)">
                <?php else: ?>
                    <div class="avatar-large"><?= strtoupper(substr($me['nama_lengkap'],0,2)) ?></div>
                <?php endif; ?>
                <div style="margin-top:8px"><input type="file" name="foto" accept="image/*" class="form-control"></div>
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" class="form-control" value="<?= e($me['username']) ?>" disabled>
            </div>
            <div class="form-group">
                <label>Nama Lengkap *</label>
                <input type="text" name="nama_lengkap" class="form-control" required value="<?= e($me['nama_lengkap']) ?>">
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" class="form-control" required value="<?= e($me['email']) ?>">
            </div>
            <div class="form-group">
                <label>Role</label>
                <input type="text" class="form-control" value="<?= formatRole($me['role']) ?>" disabled>
            </div>
            <div class="form-group">
                <label>Unit Kerja</label>
                <input type="text" class="form-control" value="<?= e($me['nama_unit'] ?? '-') ?>" disabled>
            </div>
            <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan Profil</button>
        </form>
    </div>

    <!-- Card Ganti Password -->
    <div class="card">
        <h3 style="margin-bottom:16px"><i class="fas fa-lock"></i> Ubah Password</h3>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label>Password Lama *</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Password Baru *</label>
                <input type="password" name="new_password" class="form-control" required minlength="6">
                <small class="text-muted">Minimal 6 karakter</small>
            </div>
            <div class="form-group">
                <label>Konfirmasi Password Baru *</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="6">
            </div>
            <button class="btn btn-primary"><i class="fas fa-key"></i> Ubah Password</button>
        </form>

        <hr style="margin:24px 0">

        <h4><i class="fas fa-info-circle"></i> Informasi Akun</h4>
        <table class="info-table" style="margin-top:12px">
            <tr><td>Login Terakhir</td><td>: <?= $me['last_login'] ? formatTanggal($me['last_login'],true) : '-' ?></td></tr>
            <tr><td>Status</td><td>: <?= $me['status']==='aktif'?'<span class="badge badge-success">Aktif</span>':'<span class="badge badge-danger">Non-aktif</span>' ?></td></tr>
            <tr><td>Tgl Bergabung</td><td>: <?= formatTanggal($me['created_at']) ?></td></tr>
        </table>
    </div>
</div>

<style>
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
@media (max-width: 992px) { .grid-2 { grid-template-columns: 1fr; } }
.avatar-large {
    width: 120px; height: 120px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: #fff; display: inline-flex; align-items: center; justify-content: center;
    font-size: 36px; font-weight: 700;
}
.info-table { width: 100%; }
.info-table td { padding: 6px 0; }
</style>

<?php include __DIR__.'/../includes/footer.php'; ?>
