<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$user = currentUser();
$pdo = db();

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
        header('Location: tindak_lanjut.php'); exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $risiko_id   = (int)$_POST['risiko_id'];
        $tanggal     = $_POST['tanggal'];
        $uraian      = trim($_POST['uraian']);
        $progress    = (int)$_POST['progress'];
        $status      = $_POST['status'];
        $catatan     = trim($_POST['catatan'] ?? '');

        // Handle file upload
        $file_bukti = $_POST['existing_file'] ?? null;
        if (!empty($_FILES['file_bukti']['name'])) {
            $upload_dir = __DIR__ . '/../uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['file_bukti']['name'], PATHINFO_EXTENSION);
            $allowed = ['pdf','jpg','jpeg','png','doc','docx','xls','xlsx'];
            if (in_array(strtolower($ext), $allowed)) {
                $fname = 'tl_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['file_bukti']['tmp_name'], $upload_dir . $fname)) {
                    $file_bukti = $fname;
                }
            }
        }

        if ($id) {
            $stmt = $pdo->prepare("UPDATE tindak_lanjut SET risiko_id=?, tanggal=?, uraian=?, progress=?, status=?, catatan=?, file_bukti=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$risiko_id,$tanggal,$uraian,$progress,$status,$catatan,$file_bukti,$id]);
            logAudit('UPDATE','tindak_lanjut',$id,"Update tindak lanjut #$id");
            flash('success','Tindak lanjut berhasil diperbarui.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO tindak_lanjut (risiko_id,tanggal,uraian,progress,status,catatan,file_bukti,user_id,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())");
            $stmt->execute([$risiko_id,$tanggal,$uraian,$progress,$status,$catatan,$file_bukti,$user['id']]);
            $newId = $pdo->lastInsertId();
            logAudit('CREATE','tindak_lanjut',$newId,"Tambah tindak lanjut untuk risiko #$risiko_id");

            // Update progress on risiko table
            $pdo->prepare("UPDATE risiko SET progress=?, status_mitigasi=? WHERE id=?")
                ->execute([$progress, $status==='selesai'?'selesai':'on_progress', $risiko_id]);

            flash('success','Tindak lanjut berhasil ditambahkan.');
        }
        header('Location: tindak_lanjut.php'); exit;
    }

    if ($action === 'delete' && hasRole(['admin'])) {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM tindak_lanjut WHERE id=?")->execute([$id]);
        logAudit('DELETE','tindak_lanjut',$id,"Hapus tindak lanjut #$id");
        flash('success','Tindak lanjut berhasil dihapus.');
        header('Location: tindak_lanjut.php'); exit;
    }
}

// Edit data
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM tindak_lanjut WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}

// Filters
$f_risiko = $_GET['risiko'] ?? '';
$f_status = $_GET['status'] ?? '';

$where = ['1=1']; $params = [];
if ($user['role'] === 'unit_kerja') {
    $where[] = 'r.unit_id = ?'; $params[] = $user['unit_id'];
}
if ($f_risiko !== '') { $where[] = 'tl.risiko_id = ?'; $params[] = $f_risiko; }
if ($f_status !== '') { $where[] = 'tl.status = ?'; $params[] = $f_status; }

$sql = "SELECT tl.*, r.kode_risiko, r.nama_risiko, u.nama_lengkap AS pic_nama
        FROM tindak_lanjut tl
        JOIN risiko r ON r.id = tl.risiko_id
        LEFT JOIN users u ON u.id = tl.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY tl.tanggal DESC, tl.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Get list risiko untuk dropdown
$risiko_sql = "SELECT id, kode_risiko, nama_risiko FROM risiko WHERE 1=1";
$risiko_params = [];
if ($user['role']==='unit_kerja') {
    $risiko_sql .= " AND unit_id = ?"; $risiko_params[] = $user['unit_id'];
}
$risiko_sql .= " ORDER BY kode_risiko";
$st = $pdo->prepare($risiko_sql); $st->execute($risiko_params);
$risiko_list = $st->fetchAll();

$pageTitle = 'Tindak Lanjut';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-tasks"></i> Tindak Lanjut Risiko</h1>
        <p class="page-subtitle">Pencatatan rencana penanganan risiko (RPR) dan progress implementasi</p>
    </div>
    <?php if (hasRole(['admin','unit_kerja'])): ?>
    <button class="btn btn-primary" onclick="openModal('modalTL')">
        <i class="fas fa-plus"></i> Tambah Tindak Lanjut
    </button>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" class="filter-bar">
        <div class="filter-item">
            <label>Risiko</label>
            <select name="risiko" class="form-control">
                <option value="">— Semua —</option>
                <?php foreach ($risiko_list as $r): ?>
                <option value="<?= $r['id'] ?>" <?= $f_risiko==$r['id']?'selected':'' ?>>
                    <?= e($r['kode_risiko']) ?> - <?= e(mb_substr($r['nama_risiko'],0,50)) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <label>Status</label>
            <select name="status" class="form-control">
                <option value="">— Semua —</option>
                <option value="rencana" <?= $f_status==='rencana'?'selected':'' ?>>Rencana</option>
                <option value="on_progress" <?= $f_status==='on_progress'?'selected':'' ?>>On Progress</option>
                <option value="selesai" <?= $f_status==='selesai'?'selected':'' ?>>Selesai</option>
                <option value="terhambat" <?= $f_status==='terhambat'?'selected':'' ?>>Terhambat</option>
            </select>
        </div>
        <div class="filter-item">
            <label>&nbsp;</label>
            <button class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="tindak_lanjut.php" class="btn btn-outline">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
    <table class="data-table">
        <thead>
        <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Kode Risiko</th>
            <th>Risiko</th>
            <th>Uraian Tindak Lanjut</th>
            <th>Progress</th>
            <th>Status</th>
            <th>PIC</th>
            <th>Bukti</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="10" class="text-center text-muted">Belum ada data tindak lanjut.</td></tr>
        <?php else: $no=1; foreach ($rows as $r): ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><?= formatTanggal($r['tanggal']) ?></td>
            <td><strong><?= e($r['kode_risiko']) ?></strong></td>
            <td><?= e($r['nama_risiko']) ?></td>
            <td><?= nl2br(e(mb_substr($r['uraian'],0,150))) ?></td>
            <td style="min-width:120px">
                <div class="progress-bar"><div class="progress-fill" style="width:<?= $r['progress'] ?>%"></div></div>
                <small><?= $r['progress'] ?>%</small>
            </td>
            <td>
<?php
    $map = [
        'rencana'       => 'badge-info',
        'on_progress'   => 'badge-warning',
        'dalam_proses'  => 'badge-warning',
        'selesai'       => 'badge-success',
        'terhambat'     => 'badge-danger'
    ];

    $label = [
        'rencana'       => 'Rencana',
        'on_progress'   => 'On Progress',
        'dalam_proses'  => 'Dalam Proses',
        'selesai'       => 'Selesai',
        'terhambat'     => 'Terhambat'
    ];

    $status = $r['status'] ?? 'rencana';

    echo "<span class='badge ".$map[$status]."'>".$label[$status]."</span>";
?>
</td>
            <td><?= e($r['pic_nama'] ?? '-') ?></td>
            <td>
                <?php if ($r['file_bukti']): ?>
                <a href="<?= APP_URL ?>/uploads/<?= e($r['file_bukti']) ?>" target="_blank" class="btn-icon" title="Lihat bukti">
                    <i class="fas fa-paperclip"></i>
                </a>
                <?php else: ?><span class="text-muted">-</span><?php endif; ?>
            </td>
            <td>
                <?php if (hasRole(['admin','unit_kerja'])): ?>
                <a href="?edit=<?= $r['id'] ?>" class="btn-icon" title="Edit"><i class="fas fa-edit"></i></a>
                <?php endif; ?>
                <?php if (hasRole(['admin'])): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Hapus tindak lanjut ini?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button class="btn-icon btn-icon-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal Form -->
<div class="modal-overlay <?= $edit ? 'active':'' ?>" id="modalTL">
    <div class="modal" style="max-width:700px">
        <div class="modal-header">
            <h3><i class="fas fa-tasks"></i> <?= $edit?'Edit':'Tambah' ?> Tindak Lanjut</h3>
            <button type="button" class="modal-close" onclick="location.href='tindak_lanjut.php'">&times;</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
            <input type="hidden" name="existing_file" value="<?= e($edit['file_bukti'] ?? '') ?>">

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Risiko *</label>
                        <select name="risiko_id" class="form-control" required>
                            <option value="">— Pilih Risiko —</option>
                            <?php foreach ($risiko_list as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ($edit['risiko_id']??'')==$r['id']?'selected':'' ?>>
                                <?= e($r['kode_risiko']) ?> - <?= e($r['nama_risiko']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tanggal *</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= $edit['tanggal'] ?? date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Uraian Tindak Lanjut / RPR *</label>
                    <textarea name="uraian" class="form-control" rows="4" required placeholder="Jelaskan aktivitas tindak lanjut yang dilakukan..."><?= e($edit['uraian'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Progress (%) *</label>
                        <input type="number" name="progress" class="form-control" min="0" max="100" value="<?= $edit['progress'] ?? 0 ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" class="form-control" required>
                            <option value="rencana" <?= ($edit['status']??'')==='rencana'?'selected':'' ?>>Rencana</option>
                            <option value="on_progress" <?= ($edit['status']??'')==='on_progress'?'selected':'' ?>>On Progress</option>
                            <option value="selesai" <?= ($edit['status']??'')==='selesai'?'selected':'' ?>>Selesai</option>
                            <option value="terhambat" <?= ($edit['status']??'')==='terhambat'?'selected':'' ?>>Terhambat</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Catatan</label>
                    <textarea name="catatan" class="form-control" rows="2"><?= e($edit['catatan'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>File Bukti (PDF/Gambar/Word/Excel, max 5MB)</label>
                    <input type="file" name="file_bukti" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    <?php if (!empty($edit['file_bukti'])): ?>
                    <small>File saat ini: <a href="<?= APP_URL ?>/uploads/<?= e($edit['file_bukti']) ?>" target="_blank"><?= e($edit['file_bukti']) ?></a></small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="location.href='tindak_lanjut.php'">Batal</button>
                <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>