<?php
$page_title = 'Pemantauan & Reviu Risiko';
$page_subtitle = 'Monitoring perkembangan dan efektivitas pengendalian';
$current_page = 'pemantauan';
require_once __DIR__ . '/../includes/header.php';

// HANDLE POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $rid = (int)$_POST['risiko_id'];
    $lh  = (int)$_POST['likelihood_post'];
    $im  = (int)$_POST['impact_post'];
    $bb  = getBobot($lh, $im);
    $nl  = hitungNilaiRisiko($lh, $im, $bb);
    $tk  = getTingkatRisiko($nl);

    // Tentukan simpulan dengan compare nilai awal
    $awal = $pdo->prepare("SELECT nilai_risiko FROM risiko WHERE id=?");
    $awal->execute([$rid]);
    $nilaiAwal = (int)$awal->fetchColumn();
    if ($nl < $nilaiAwal)      $simpulan = 'penurunan';
    elseif ($nl > $nilaiAwal)  $simpulan = 'peningkatan';
    else                       $simpulan = 'tidak_ada_penurunan';

    $stmt = $pdo->prepare("INSERT INTO pemantauan
        (risiko_id, tanggal, likelihood_post, impact_post, bobot_post, nilai_post, tingkat_post,
         simpulan, efektivitas, hasil_pemantauan, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $rid, $_POST['tanggal'], $lh, $im, $bb, $nl, $tk,
        $simpulan, $_POST['efektivitas'], $_POST['hasil_pemantauan'], $user['id']
    ]);

    logAudit('CREATE', 'pemantauan', $pdo->lastInsertId(), "Pemantauan risiko ID $rid");
    flash('success', 'Hasil pemantauan berhasil disimpan');
    header('Location: pemantauan.php');
    exit;
}

// Unit kerja filter
$whereRole = $user['role'] === 'unit_kerja' ? "WHERE r.unit_id = {$user['unit_id']}" : '';

// Data semua pemantauan
$data = $pdo->query("
    SELECT p.*, r.kode_risiko, r.nama_risiko, r.likelihood, r.impact, r.bobot, r.nilai_risiko,
           r.tingkat_risiko AS tingkat_awal, r.prioritas, r.rpr_uraian, r.jadwal_selesai
    FROM pemantauan p
    JOIN risiko r ON r.id = p.risiko_id
    " . ($user['role'] === 'unit_kerja' ? "WHERE r.unit_id = {$user['unit_id']}" : '') . "
    ORDER BY p.tanggal DESC")->fetchAll();

$risiko_list = $pdo->query("SELECT id, kode_risiko, nama_risiko FROM risiko $whereRole ORDER BY kode_risiko")->fetchAll();

$pre_risiko = (int)($_GET['risiko'] ?? 0);
?>

<div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <i class="fas fa-chevron-right"></i>
    <span>Pemantauan & Reviu</span>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h3><i class="fas fa-eye"></i> Tabel Pemantauan & Reviu Risiko</h3>
            <p>Pemantauan berkelanjutan untuk efektivitas pengendalian</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('modalPantau')">
            <i class="fas fa-plus"></i> Pemantauan Baru
        </button>
    </div>

    <div class="table-wrap">
        <table class="table" style="font-size:12px">
            <thead>
                <tr>
                    <th rowspan="2">No</th>
                    <th rowspan="2">Risiko</th>
                    <th rowspan="2">Kode</th>
                    <th rowspan="2">P</th>
                    <th rowspan="2">D</th>
                    <th rowspan="2">Bobot</th>
                    <th rowspan="2">Nilai</th>
                    <th rowspan="2">Tingkat Awal</th>
                    <th rowspan="2">Prioritas</th>
                    <th rowspan="2">Pengendalian</th>
                    <th rowspan="2">Jadwal</th>
                    <th colspan="5" style="text-align:center;background:#dbeafe">Hasil Pemantauan</th>
                    <th colspan="2" style="text-align:center;background:#dcfce7">Simpulan</th>
                </tr>
                <tr>
                    <th style="background:#dbeafe">P</th>
                    <th style="background:#dbeafe">D</th>
                    <th style="background:#dbeafe">Bobot</th>
                    <th style="background:#dbeafe">Nilai</th>
                    <th style="background:#dbeafe">Tingkat</th>
                    <th style="background:#dcfce7">Tingkat Risiko</th>
                    <th style="background:#dcfce7">Efektivitas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr><td colspan="18" class="text-center text-mute" style="padding:24px">Belum ada data pemantauan</td></tr>
                <?php else: foreach ($data as $i => $d): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td style="max-width:180px"><strong><?= e($d['nama_risiko']) ?></strong></td>
                        <td><?= e($d['kode_risiko']) ?></td>
                        <td><?= $d['likelihood'] ?></td>
                        <td><?= $d['impact'] ?></td>
                        <td><?= $d['bobot'] ?></td>
                        <td><strong><?= $d['nilai_risiko'] ?></strong></td>
                        <td><?= badgeTingkatRisiko($d['tingkat_awal']) ?></td>
                        <td>#<?= $d['prioritas'] ?></td>
                        <td style="max-width:160px"><?= e(mb_substr($d['rpr_uraian']??'-',0,40)) ?></td>
                        <td><?= formatTanggal($d['jadwal_selesai']) ?></td>
                        <td style="background:#f0f9ff"><?= $d['likelihood_post'] ?></td>
                        <td style="background:#f0f9ff"><?= $d['impact_post'] ?></td>
                        <td style="background:#f0f9ff"><?= $d['bobot_post'] ?></td>
                        <td style="background:#f0f9ff"><strong><?= $d['nilai_post'] ?></strong></td>
                        <td style="background:#f0f9ff"><?= badgeTingkatRisiko($d['tingkat_post']) ?></td>
                        <td style="background:#f0fdf4">
                            <?php
                            $simp = match($d['simpulan']) {
                                'penurunan' => '<span class="badge badge-success">↓ Menurun</span>',
                                'peningkatan' => '<span class="badge badge-danger">↑ Meningkat</span>',
                                default => '<span class="badge badge-secondary">Tetap</span>',
                            };
                            echo $simp;
                            ?>
                        </td>
                        <td style="background:#f0fdf4">
                            <?= $d['efektivitas']=='efektif' ? '<span class="badge badge-success">Efektif</span>' : '<span class="badge badge-warning">Tidak Efektif</span>' ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL -->
<div class="modal-overlay <?= $pre_risiko?'show':'' ?>" id="modalPantau">
    <div class="modal" style="max-width:650px">
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Pemantauan Risiko Baru</h3>
                <button type="button" class="modal-close" onclick="closeModal('modalPantau')">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Pilih Risiko <span class="req">*</span></label>
                    <select name="risiko_id" class="form-control" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($risiko_list as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $pre_risiko==$r['id']?'selected':'' ?>>
                                <?= e($r['kode_risiko']) ?> - <?= e($r['nama_risiko']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tanggal Pemantauan <span class="req">*</span></label>
                        <input type="date" name="tanggal" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Efektivitas Penanganan</label>
                        <select name="efektivitas" class="form-control" required>
                            <option value="efektif">Efektif</option>
                            <option value="tidak_efektif">Tidak Efektif</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">P Pasca Pengendalian (1-5) <span class="req">*</span></label>
                        <select name="likelihood_post" id="likelihood" class="form-control" required onchange="calcSkor()">
                            <option value="">--</option>
                            <?php for($i=1;$i<=5;$i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">D Pasca Pengendalian (1-5) <span class="req">*</span></label>
                        <select name="impact_post" id="impact" class="form-control" required onchange="calcSkor()">
                            <option value="">--</option>
                            <?php for($i=1;$i<=5;$i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row three" style="background:#f8fafc;padding:12px;border-radius:8px">
                    <div><small class="text-mute">Bobot</small>
                        <input type="text" id="bobot" readonly class="form-control" style="background:#fff;font-weight:700"></div>
                    <div><small class="text-mute">Nilai Risiko</small>
                        <input type="text" id="nilai" readonly class="form-control" style="background:#fff;font-weight:700;color:var(--primary-light)"></div>
                    <div><small class="text-mute">Tingkat</small>
                        <div style="padding:10px 0"><span id="tingkat_label" class="badge">Otomatis</span></div></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Hasil Pemantauan & Catatan</label>
                    <textarea name="hasil_pemantauan" class="form-control" placeholder="Uraian hasil pemantauan, temuan, dan rekomendasi..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalPantau')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Pemantauan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
