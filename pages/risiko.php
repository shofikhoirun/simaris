<?php
ob_start();
session_start();

$page_title = 'Risk Register';
$page_subtitle = 'Manajemen Risiko - Identifikasi, Analisis & Evaluasi';
$current_page = 'risiko';
require_once __DIR__ . '/../includes/header.php';

// =========== HANDLE POST (CREATE / UPDATE) ===========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);

        $lh    = (int)$_POST['likelihood'];
        $im    = (int)$_POST['impact'];
        $bobot = getBobot($lh, $im);
        $nilai = hitungNilaiRisiko($lh, $im, $bobot);
        $tngk  = getTingkatRisiko($nilai);
        $prio  = getPrioritas($tngk);
        $selera = getSeleraRisiko($tngk);

        // Target
        $t_lh = (int)($_POST['target_likelihood'] ?? 0);
        $t_im = (int)($_POST['target_impact'] ?? 0);
        $t_bb = $t_lh && $t_im ? getBobot($t_lh, $t_im) : 0;
        $t_nl = $t_lh && $t_im ? hitungNilaiRisiko($t_lh, $t_im, $t_bb) : 0;
        $t_tk = $t_nl > 0 ? getTingkatRisiko($t_nl) : null;

        $data = [
            'kode_risiko'   => trim($_POST['kode_risiko']),
            'nama_risiko'   => trim($_POST['nama_risiko']),
            'unit_id'       => (int)$_POST['unit_id'],
            'penyebab'      => trim($_POST['penyebab']),
            'sumber_risiko' => $_POST['sumber_risiko'],
            'kategori_cuc'  => $_POST['kategori_cuc'],
            'dampak_uraian' => trim($_POST['dampak_uraian']),
            'pengendalian'  => trim($_POST['pengendalian']),
            'efektivitas'   => $_POST['efektivitas'],
            'likelihood'    => $lh,
            'impact'        => $im,
            'bobot'         => $bobot,
            'nilai_risiko'  => $nilai,
            'tingkat_risiko'=> $tngk,
            'prioritas'     => $prio,
            'selera_risiko' => $selera,
            'pilihan_penanganan' => $_POST['pilihan_penanganan'],
            'rpr_uraian'    => trim($_POST['rpr_uraian']),
            'jadwal_mulai'  => $_POST['jadwal_mulai'] ?: null,
            'jadwal_selesai'=> $_POST['jadwal_selesai'] ?: null,
            'target_likelihood'     => $t_lh ?: null,
            'target_impact'         => $t_im ?: null,
            'target_bobot'          => $t_bb ?: null,
            'target_nilai_risiko'   => $t_nl ?: null,
            'target_tingkat_risiko' => $t_tk,
        ];

        try {
            if ($id) {
                $sql = "UPDATE risiko SET ".implode(',', array_map(fn($k)=>"$k=:$k", array_keys($data)))." WHERE id=:id";
                $data['id'] = $id;
                $pdo->prepare($sql)->execute($data);
                logAudit('UPDATE', 'risiko', $id, "Update risiko: {$data['nama_risiko']}");
                flash('success', 'Data risiko berhasil diperbarui');
            } else {
                $data['created_by']    = $user['id'];
                $data['status_mitigasi'] = 'belum_dimulai';
                $data['status_verifikasi'] = 'menunggu';
                $cols = implode(',', array_keys($data));
                $vals = ':'.implode(',:', array_keys($data));
                $pdo->prepare("INSERT INTO risiko ($cols) VALUES ($vals)")->execute($data);
                $newId = $pdo->lastInsertId();
                logAudit('CREATE', 'risiko', $newId, "Tambah risiko: {$data['nama_risiko']}");
                flash('success', 'Data risiko baru berhasil ditambahkan');
            }
        } catch (Exception $e) {
            flash('danger', 'Gagal menyimpan: '.$e->getMessage());
        }
        header('Location: risiko.php');
        exit;
    }
}

// =========== HANDLE DELETE ===========
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $risk = $pdo->prepare("SELECT nama_risiko FROM risiko WHERE id=?");
    $risk->execute([$id]);
    $r = $risk->fetch();
    if ($r) {
        $pdo->prepare("DELETE FROM risiko WHERE id=?")->execute([$id]);
        logAudit('DELETE', 'risiko', $id, "Hapus risiko: {$r['nama_risiko']}");
        flash('success', 'Data risiko berhasil dihapus');
    }
    header('Location: risiko.php');
    exit;
}

// =========== FILTER & DATA ===========
$f_unit  = $_GET['unit'] ?? '';
$f_level = $_GET['level'] ?? '';
$f_stat  = $_GET['status'] ?? '';
$search  = trim($_GET['q'] ?? '');

$where = []; $params = [];
if ($f_unit)  { $where[] = 'r.unit_id = ?';        $params[] = $f_unit; }
if ($f_level) { $where[] = 'r.tingkat_risiko = ?'; $params[] = $f_level; }
if ($f_stat)  { $where[] = 'r.status_mitigasi = ?';$params[] = $f_stat; }
if ($search)  {
    $where[] = '(r.kode_risiko LIKE ? OR r.nama_risiko LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
}

// Unit kerja membatasi unit_kerja role
if ($user['role'] === 'unit_kerja') {
    $where[] = 'r.unit_id = ?'; $params[] = $user['unit_id'];
}

$sqlWhere = $where ? 'WHERE '.implode(' AND ', $where) : '';
$risiko = $pdo->prepare("
    SELECT r.*, u.nama_unit FROM risiko r
    LEFT JOIN unit_kerja u ON u.id = r.unit_id
    $sqlWhere ORDER BY r.prioritas ASC, r.created_at DESC");
$risiko->execute($params);
$risiko = $risiko->fetchAll();

$units = $pdo->query("SELECT id, nama_unit FROM unit_kerja ORDER BY nama_unit")->fetchAll();

// Untuk edit modal
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM risiko WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}
?>

<div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <i class="fas fa-chevron-right"></i>
    <span>Risk Register</span>
</div>

<!-- FILTER & ACTION -->
<div class="card">
    <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:12px;align-items:end">
        <div class="form-group" style="margin:0">
            <label class="form-label">Cari Risiko</label>
            <input type="text" name="q" class="form-control" placeholder="Kode atau nama risiko..."
                   value="<?= e($search) ?>">
        </div>
        <div class="form-group" style="margin:0">
            <label class="form-label">Unit Kerja</label>
            <select name="unit" class="form-control">
                <option value="">Semua Unit</option>
                <?php foreach ($units as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $f_unit==$u['id']?'selected':'' ?>>
                        <?= e($u['nama_unit']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0">
            <label class="form-label">Level Risiko</label>
            <select name="level" class="form-control">
                <option value="">Semua Level</option>
                <option value="sangat_rendah" <?= $f_level=='sangat_rendah'?'selected':'' ?>>Sangat Rendah</option>
                <option value="rendah" <?= $f_level=='rendah'?'selected':'' ?>>Rendah</option>
                <option value="sedang" <?= $f_level=='sedang'?'selected':'' ?>>Sedang</option>
                <option value="tinggi" <?= $f_level=='tinggi'?'selected':'' ?>>Tinggi</option>
                <option value="sangat_tinggi" <?= $f_level=='sangat_tinggi'?'selected':'' ?>>Sangat Tinggi</option>
            </select>
        </div>
        <div class="form-group" style="margin:0">
            <label class="form-label">Status Mitigasi</label>
            <select name="status" class="form-control">
                <option value="">Semua Status</option>
                <option value="belum_dimulai" <?= $f_stat=='belum_dimulai'?'selected':'' ?>>Belum Dimulai</option>
                <option value="dalam_proses" <?= $f_stat=='dalam_proses'?'selected':'' ?>>Dalam Proses</option>
                <option value="selesai" <?= $f_stat=='selesai'?'selected':'' ?>>Selesai</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
    </form>
</div>

<!-- TABLE -->
<div class="card">
    <div class="card-header">
        <div>
            <h3><i class="fas fa-clipboard-list"></i> Daftar Risiko</h3>
            <p><?= count($risiko) ?> risiko ditampilkan</p>
        </div>
        <?php if (in_array($user['role'], ['admin','unit_kerja'])): ?>
        <button class="btn btn-primary" onclick="openModal('modalRisiko')">
            <i class="fas fa-plus"></i> Tambah Risiko
        </button>
        <?php endif; ?>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th style="min-width:200px">Risiko / Unit</th>
                    <th>Penyebab</th>
                    <th>P</th>
                    <th>D</th>
                    <th>Skor</th>
                    <th>Tingkat</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($risiko)): ?>
                    <tr><td colspan="10" class="text-center text-mute" style="padding:36px">
                        <i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:8px;opacity:.3"></i>
                        Tidak ada data risiko
                    </td></tr>
                <?php else: foreach ($risiko as $r): ?>
                    <tr>
                        <td><strong><?= e($r['kode_risiko']) ?></strong></td>
                        <td>
                            <div style="font-weight:600"><?= e($r['nama_risiko']) ?></div>
                            <small class="text-mute"><i class="fas fa-building"></i> <?= e($r['nama_unit']) ?></small>
                        </td>
                        <td style="max-width:200px;font-size:12px"><?= e(mb_substr($r['penyebab'],0,80)) ?>...</td>
                        <td><strong><?= $r['likelihood'] ?></strong></td>
                        <td><strong><?= $r['impact'] ?></strong></td>
                        <td><span class="badge badge-info"><?= $r['nilai_risiko'] ?></span></td>
                        <td><?= badgeTingkatRisiko($r['tingkat_risiko']) ?></td>
                        <td><?= badgeStatusMitigasi($r['status_mitigasi']) ?></td>
                        <td style="min-width:100px">
                            <div class="progress"><div class="progress-bar" style="width:<?= $r['progress'] ?>%">
                                <?= $r['progress'] ?>%
                            </div></div>
                        </td>
                        <td class="text-center" style="white-space:nowrap">
                            <a href="profil_risiko.php?id=<?= $r['id'] ?>" class="btn btn-secondary btn-icon" title="Detail">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if (in_array($user['role'], ['admin','unit_kerja'])): ?>
                            <a href="?edit=<?= $r['id'] ?>" class="btn btn-primary btn-icon" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($user['role'] === 'admin'): ?>
                            <button onclick="confirmDelete('?delete=<?= $r['id'] ?>', 'Hapus risiko <?= e($r['kode_risiko']) ?>?')"
                                    class="btn btn-danger btn-icon" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ MODAL FORM ============ -->
<div class="modal-overlay <?= $edit?'show':'' ?>" id="modalRisiko">
    <div class="modal" style="max-width:900px">
        <form method="POST" action="risiko.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">

            <div class="modal-header">
                <h3><i class="fas fa-clipboard-check"></i> <?= $edit?'Edit':'Tambah' ?> Data Risiko</h3>
                <button type="button" class="modal-close" onclick="window.location='risiko.php'">×</button>
            </div>

            <div class="modal-body">
                <!-- IDENTIFIKASI -->
                <h4 style="color:var(--primary-light);margin-bottom:12px;font-size:14px">
                    <i class="fas fa-search"></i> 1. Identifikasi Risiko
                </h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Kode Risiko <span class="req">*</span></label>
                        <input type="text" name="kode_risiko" class="form-control" required
                               value="<?= e($edit['kode_risiko'] ?? '') ?>" placeholder="RSK-001">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit Pemilik Risiko <span class="req">*</span></label>
                        <select name="unit_id" class="form-control" required>
                            <option value="">-- Pilih Unit --</option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($edit['unit_id'] ?? '')==$u['id']?'selected':'' ?>>
                                    <?= e($u['nama_unit']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Nama / Pernyataan Risiko <span class="req">*</span></label>
                    <input type="text" name="nama_risiko" class="form-control" required
                           value="<?= e($edit['nama_risiko'] ?? '') ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Sumber Risiko</label>
                        <select name="sumber_risiko" class="form-control" required>
                            <option value="internal" <?= ($edit['sumber_risiko']??'')=='internal'?'selected':'' ?>>Internal</option>
                            <option value="eksternal" <?= ($edit['sumber_risiko']??'')=='eksternal'?'selected':'' ?>>Eksternal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kategori (C/UC)</label>
                        <select name="kategori_cuc" class="form-control" required>
                            <option value="C"  <?= ($edit['kategori_cuc']??'')=='C'?'selected':'' ?>>Controllable (C)</option>
                            <option value="UC" <?= ($edit['kategori_cuc']??'')=='UC'?'selected':'' ?>>Uncontrollable (UC)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Penyebab Risiko <span class="req">*</span></label>
                    <textarea name="penyebab" class="form-control" required><?= e($edit['penyebab'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Dampak Risiko <span class="req">*</span></label>
                    <textarea name="dampak_uraian" class="form-control" required><?= e($edit['dampak_uraian'] ?? '') ?></textarea>
                </div>

                <!-- ANALISIS -->
                <h4 style="color:var(--primary-light);margin:20px 0 12px;font-size:14px">
                    <i class="fas fa-chart-bar"></i> 2. Analisis Risiko
                </h4>
                <div class="form-group">
                    <label class="form-label">Pengendalian Yang Ada</label>
                    <textarea name="pengendalian" class="form-control"><?= e($edit['pengendalian'] ?? '') ?></textarea>
                </div>

                <div class="form-row three">
                    <div class="form-group">
                        <label class="form-label">Efektivitas Pengendalian</label>
                        <select name="efektivitas" class="form-control">
                            <option value="tidak_efektif" <?= ($edit['efektivitas']??'')=='tidak_efektif'?'selected':'' ?>>Tidak Efektif</option>
                            <option value="efektif"       <?= ($edit['efektivitas']??'')=='efektif'?'selected':'' ?>>Efektif</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Likelihood / P (1-5) <span class="req">*</span></label>
                        <select name="likelihood" id="likelihood" class="form-control" required onchange="calcSkor()">
                            <option value="">--</option>
                            <?php for($i=1;$i<=5;$i++): ?>
                                <option value="<?= $i ?>" <?= ($edit['likelihood']??'')==$i?'selected':'' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Impact / D (1-5) <span class="req">*</span></label>
                        <select name="impact" id="impact" class="form-control" required onchange="calcSkor()">
                            <option value="">--</option>
                            <?php for($i=1;$i<=5;$i++): ?>
                                <option value="<?= $i ?>" <?= ($edit['impact']??'')==$i?'selected':'' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row three" style="background:#f8fafc;padding:12px;border-radius:8px">
                    <div>
                        <small class="text-mute">Bobot</small>
                        <input type="text" id="bobot" readonly class="form-control"
                               value="<?= $edit['bobot'] ?? '' ?>" style="background:#fff;font-weight:700">
                    </div>
                    <div>
                        <small class="text-mute">Nilai Risiko (P × D × Bobot)</small>
                        <input type="text" id="nilai" readonly class="form-control"
                               value="<?= $edit['nilai_risiko'] ?? '' ?>" style="background:#fff;font-weight:700;color:var(--primary-light)">
                    </div>
                    <div>
                        <small class="text-mute">Tingkat Risiko</small>
                        <input type="hidden" id="tingkat">
                        <div style="padding:10px 0">
                            <span id="tingkat_label" class="badge <?= !empty($edit['tingkat_risiko']) ? 'badge-info':'' ?>">
                                <?= $edit ? formatTingkatRisiko($edit['tingkat_risiko']) : 'Otomatis' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- EVALUASI -->
                <h4 style="color:var(--primary-light);margin:20px 0 12px;font-size:14px">
                    <i class="fas fa-balance-scale"></i> 3. Evaluasi Risiko & Penanganan
                </h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Pilihan Penanganan</label>
                        <select name="pilihan_penanganan" class="form-control" required>
                            <option value="mitigasi"    <?= ($edit['pilihan_penanganan']??'')=='mitigasi'?'selected':'' ?>>Mitigasi Risiko</option>
                            <option value="menerima"    <?= ($edit['pilihan_penanganan']??'')=='menerima'?'selected':'' ?>>Menerima Risiko</option>
                            <option value="menghindari" <?= ($edit['pilihan_penanganan']??'')=='menghindari'?'selected':'' ?>>Menghindari Risiko</option>
                            <option value="berbagi"     <?= ($edit['pilihan_penanganan']??'')=='berbagi'?'selected':'' ?>>Berbagi Risiko</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jadwal Mulai</label>
                        <input type="date" name="jadwal_mulai" class="form-control"
                               value="<?= e($edit['jadwal_mulai'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Uraian Rencana Penanganan Risiko (RPR)</label>
                    <textarea name="rpr_uraian" class="form-control"><?= e($edit['rpr_uraian'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Jadwal Selesai</label>
                        <input type="date" name="jadwal_selesai" class="form-control"
                               value="<?= e($edit['jadwal_selesai'] ?? '') ?>">
                    </div>
                </div>

                <!-- TARGET -->
                <h4 style="color:var(--primary-light);margin:20px 0 12px;font-size:14px">
                    <i class="fas fa-bullseye"></i> 4. Target Penurunan Tingkat Risiko
                </h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Target Likelihood (1-5)</label>
                        <select name="target_likelihood" class="form-control">
                            <option value="">--</option>
                            <?php for($i=1;$i<=5;$i++): ?>
                                <option value="<?= $i ?>" <?= ($edit['target_likelihood']??'')==$i?'selected':'' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Target Impact (1-5)</label>
                        <select name="target_impact" class="form-control">
                            <option value="">--</option>
                            <?php for($i=1;$i<=5;$i++): ?>
                                <option value="<?= $i ?>" <?= ($edit['target_impact']??'')==$i?'selected':'' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="window.location='risiko.php'">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Risiko</button>
            </div>
        </form>
    </div>
</div>

<script>
// Init kalkulasi jika edit
<?php if ($edit): ?>
document.addEventListener('DOMContentLoaded', () => calcSkor());
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
