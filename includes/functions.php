<?php
/**
 * SIMARIS - Helper Functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/* =====================================================
 * DB Singleton
 * ===================================================== */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = getDB();
    }
    return $pdo;
}

/* =====================================================
 * AUTH & SESSION
 * ===================================================== */

function isLoggedIn(): bool {
    return isset($_SESSION['user']['id']) || isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function hasRole(array $roles): bool {
    $u = currentUser();
    return in_array($u['role'] ?? '', $roles, true);
}

function requireRole(array $roles): void {
    requireLogin();
    if (!hasRole($roles)) {
        http_response_code(403);
        die('Akses ditolak. Anda tidak memiliki izin untuk halaman ini.');
    }
}

function currentUser(): array {
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    return [
        'id'           => $_SESSION['user_id']      ?? null,
        'username'     => $_SESSION['username']     ?? null,
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? null,
        'role'         => $_SESSION['role']         ?? null,
        'unit_id'      => $_SESSION['unit_id']      ?? null,
        'email'        => $_SESSION['email']        ?? null,
        'foto'         => $_SESSION['foto']         ?? null,
    ];
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

/* =====================================================
 * RISK CALCULATIONS
 * ===================================================== */

function getBobot(int $likelihood, int $impact): int {
    $stmt = db()->prepare("SELECT bobot FROM matriks_bobot WHERE likelihood=? AND impact=?");
    $stmt->execute([$likelihood, $impact]);
    $row = $stmt->fetch();
    return $row ? (int)$row['bobot'] : 1;
}

function hitungNilaiRisiko(int $likelihood, int $impact, int $bobot): int {
    return $likelihood * $impact * $bobot;
}

function getTingkatRisiko(int $nilai): string {
    if ($nilai <= 15)  return 'sangat_rendah';
    if ($nilai <= 40)  return 'rendah';
    if ($nilai <= 80)  return 'sedang';
    if ($nilai <= 140) return 'tinggi';
    return 'sangat_tinggi';
}

function getPrioritas(string $tingkat): int {
    return match($tingkat) {
        'sangat_tinggi' => 1,
        'tinggi'        => 2,
        'sedang'        => 3,
        'rendah'        => 4,
        default         => 5,
    };
}

function getSeleraRisiko(string $tingkat): string {
    return in_array($tingkat, ['sangat_rendah','rendah']) ? 'dalam_batas' : 'diatas_batas';
}

/* =====================================================
 * FORMATTERS
 * ===================================================== */

function formatTingkatRisiko(string $tingkat): string {
    return match($tingkat) {
        'sangat_rendah' => 'Sangat Rendah',
        'rendah'        => 'Rendah',
        'sedang'        => 'Sedang',
        'tinggi'        => 'Tinggi',
        'sangat_tinggi' => 'Sangat Tinggi',
        default         => ucfirst($tingkat),
    };
}

function badgeTingkatRisiko(string $tingkat): string {
    $label = formatTingkatRisiko($tingkat);
    $class = match($tingkat) {
        'sangat_rendah' => 'badge-success',
        'rendah'        => 'badge-success',
        'sedang'        => 'badge-warning',
        'tinggi'        => 'badge-orange',
        'sangat_tinggi' => 'badge-danger',
        default         => 'badge-secondary',
    };
    return "<span class=\"badge $class\">$label</span>";
}

function badgeStatusMitigasi(string $status): string {
    $label = match($status) {
        'belum'         => 'Belum',
        'belum_dimulai' => 'Belum Dimulai',
        'on_progress'   => 'On Progress',
        'dalam_proses'  => 'Dalam Proses',
        'selesai'       => 'Selesai',
        default         => ucfirst(str_replace('_',' ',$status)),
    };
    $class = match($status) {
        'belum','belum_dimulai' => 'badge-secondary',
        'on_progress','dalam_proses' => 'badge-info',
        'selesai'       => 'badge-success',
        default         => 'badge-secondary',
    };
    return "<span class=\"badge $class\">$label</span>";
}

function badgeStatusVerifikasi(string $status): string {
    $label = match($status) {
        'draft'      => 'Draft',
        'menunggu'   => 'Menunggu',
        'disetujui'  => 'Disetujui',
        'ditolak'    => 'Ditolak',
        default      => ucfirst($status),
    };
    $class = match($status) {
        'draft'      => 'badge-secondary',
        'menunggu'   => 'badge-warning',
        'disetujui'  => 'badge-success',
        'ditolak'    => 'badge-danger',
        default      => 'badge-secondary',
    };
    return "<span class=\"badge $class\">$label</span>";
}

function formatTanggal(?string $tgl, bool $withTime = false): string {
    if (!$tgl || str_starts_with($tgl, '0000-00-00')) return '-';
    return $withTime
        ? date('d M Y, H:i', strtotime($tgl))
        : date('d M Y', strtotime($tgl));
}

function formatRole(string $role): string {
    return match($role) {
        'admin'       => 'Administrator',
        'unit_kerja'  => 'Unit Kerja',
        'verifikator' => 'Verifikator',
        'pimpinan'    => 'Pimpinan',
        default       => ucfirst($role),
    };
}

/* =====================================================
 * AUDIT TRAIL
 * ===================================================== */

function logAudit(string $aksi, string $tabel, ?int $recordId = null,
                  string $deskripsi = '', $dataLama = null, $dataBaru = null): void {
    try {
        $stmt = db()->prepare("INSERT INTO audit_trail
            (user_id, aksi, tabel, record_id, deskripsi, data_lama, data_baru, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            currentUser()['id'] ?? null,
            $aksi, $tabel, $recordId, $deskripsi,
            $dataLama ? json_encode($dataLama) : null,
            $dataBaru ? json_encode($dataBaru) : null,
            $_SERVER['REMOTE_ADDR']     ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    } catch (Exception $e) { /* silently fail */ }
}

/* =====================================================
 * SECURITY
 * ===================================================== */

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf" value="' . csrfToken() . '">';
}

function verifyCsrf(?string $token = null): bool {
    $token = $token ?? ($_POST['csrf'] ?? $_POST['csrf_token'] ?? '');
    return $token !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/* =====================================================
 * NOTIFIKASI
 * ===================================================== */

function buatNotifikasi(?int $userId, string $judul, string $pesan,
                        string $tipe = 'info', ?string $link = null): void {
    try {
        $stmt = db()->prepare("INSERT INTO notifikasi (user_id, judul, pesan, tipe, link)
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $judul, $pesan, $tipe, $link]);
    } catch (Exception $e) { /* silently fail */ }
}

function getNotifikasi(int $userId, int $limit = 5): array {
    $stmt = db()->prepare("SELECT * FROM notifikasi
        WHERE (user_id = ? OR user_id IS NULL)
        ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/* =====================================================
 * BACKWARD COMPATIBILITY
 * Inisialisasi $pdo global supaya file lama tetap jalan
 * ===================================================== */
$pdo = db();
