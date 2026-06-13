<?php
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username=? OR email=?) AND status='aktif' LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && $password == $user['password']) {
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role']         = $user['role'];
            $_SESSION['unit_id']      = $user['unit_id'];
            $_SESSION['email']        = $user['email'];
            $_SESSION['foto']         = $user['foto'] ?? null;
            // Unified user array
            $_SESSION['user'] = [
                'id'           => $user['id'],
                'username'     => $user['username'],
                'nama_lengkap' => $user['nama_lengkap'],
                'role'         => $user['role'],
                'unit_id'      => $user['unit_id'],
                'email'        => $user['email'],
                'foto'         => $user['foto'] ?? null,
            ];

            // Update last login
            $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);

            // Audit
            logAudit('LOGIN', 'users', $user['id'], "User {$user['username']} berhasil login");

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Username/email atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif}
body{min-height:100vh;display:flex;background:#f8fafc;color:#1e293b}

.login-wrapper{display:flex;width:100%;min-height:100vh}

/* ===== LEFT PANEL ===== */
.left-panel{
    flex:1;background:linear-gradient(135deg,#002060 0%,#003a8f 100%);
    color:#fff;padding:48px;display:flex;flex-direction:column;justify-content:space-between;
    position:relative;overflow:hidden
}
.left-panel::before{
    content:'';position:absolute;top:-200px;right:-200px;width:500px;height:500px;
    border-radius:50%;background:rgba(0,112,192,.15);filter:blur(60px)
}
.left-panel::after{
    content:'';position:absolute;bottom:-150px;left:-150px;width:400px;height:400px;
    border-radius:50%;background:rgba(255,255,255,.05);filter:blur(60px)
}
.brand-top{position:relative;z-index:2;display:flex;align-items:center;gap:14px}
.logo-shield{
    width:56px;height:56px;background:rgba(255,255,255,.12);border-radius:14px;
    display:flex;align-items:center;justify-content:center;backdrop-filter:blur(10px);
    box-shadow:0 8px 32px rgba(0,0,0,.2)
}
.logo-shield svg{width:32px;height:32px}
.brand-text h1{font-size:24px;font-weight:800;letter-spacing:1px}
.brand-text p{font-size:12px;opacity:.8;font-weight:400}

.left-content{position:relative;z-index:2;margin:auto 0}
.left-content h2{font-size:42px;font-weight:800;line-height:1.2;margin-bottom:16px}
.left-content h2 span{color:#7eb6ff}
.left-content p{font-size:16px;opacity:.85;line-height:1.6;max-width:480px;margin-bottom:36px}

/* Mockup laptop */
.mockup{
    background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);
    border-radius:12px;padding:16px;backdrop-filter:blur(10px);max-width:480px;
    box-shadow:0 20px 50px rgba(0,0,0,.3)
}
.mockup-bar{display:flex;gap:6px;margin-bottom:12px}
.mockup-bar span{width:10px;height:10px;border-radius:50%;background:rgba(255,255,255,.3)}
.mockup-content{display:grid;grid-template-columns:80px 1fr;gap:12px}
.mockup-sidebar{background:rgba(0,32,96,.5);border-radius:8px;height:140px;padding:10px}
.mockup-sidebar div{height:8px;background:rgba(255,255,255,.2);border-radius:3px;margin-bottom:8px}
.mockup-main{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.mockup-card{background:rgba(255,255,255,.15);border-radius:8px;height:66px;padding:10px}
.mockup-card .num{font-size:18px;font-weight:700;color:#7eb6ff;display:block;margin-top:4px}
.mockup-chart{background:rgba(255,255,255,.15);border-radius:8px;height:66px;grid-column:1/3;
    display:flex;align-items:flex-end;gap:4px;padding:8px}
.mockup-chart span{flex:1;background:#7eb6ff;border-radius:2px}

/* Features */
.features{position:relative;z-index:2;display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:32px}
.feature{text-align:center}
.feature-ico{
    width:48px;height:48px;background:rgba(255,255,255,.1);border-radius:12px;
    display:flex;align-items:center;justify-content:center;margin:0 auto 8px;
    font-size:20px;color:#7eb6ff;border:1px solid rgba(255,255,255,.15)
}
.feature p{font-size:11px;font-weight:500;opacity:.9}

/* ===== RIGHT PANEL ===== */
.right-panel{
    flex:1;background:#fafbfc;display:flex;align-items:center;justify-content:center;padding:40px
}
.login-card{
    background:#fff;width:100%;max-width:440px;padding:48px 40px;border-radius:20px;
    box-shadow:0 12px 40px rgba(0,32,96,.08);border:1px solid #eef1f5
}
.login-card h3{font-size:28px;font-weight:700;color:#002060;margin-bottom:8px}
.login-card .subtitle{font-size:14px;color:#64748b;margin-bottom:32px}

.alert{padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:13px;font-weight:500;
    display:flex;align-items:center;gap:8px}
.alert-error{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}

.form-group{margin-bottom:20px}
.form-label{display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:8px}
.input-wrap{position:relative}
.input-wrap i.left-ico{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:14px}
.input-wrap input{
    width:100%;padding:14px 16px 14px 44px;border:1.5px solid #e2e8f0;border-radius:10px;
    font-size:14px;font-family:inherit;transition:.2s;background:#fff
}
.input-wrap input:focus{outline:none;border-color:#0070C0;box-shadow:0 0 0 4px rgba(0,112,192,.1)}
.toggle-pass{position:absolute;right:16px;top:50%;transform:translateY(-50%);
    background:none;border:none;color:#94a3b8;cursor:pointer;font-size:14px}

.row-between{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
.checkbox{display:flex;align-items:center;gap:8px;font-size:13px;color:#475569;cursor:pointer}
.checkbox input{width:16px;height:16px;accent-color:#0070C0}
.forgot{font-size:13px;color:#0070C0;text-decoration:none;font-weight:500}
.forgot:hover{text-decoration:underline}

.btn-login{
    width:100%;padding:14px;background:linear-gradient(135deg,#0070C0,#002060);color:#fff;
    border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;
    transition:.2s;box-shadow:0 4px 14px rgba(0,112,192,.3)
}
.btn-login:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,112,192,.4)}

.divider{display:flex;align-items:center;margin:24px 0;gap:12px}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#e2e8f0}
.divider span{font-size:12px;color:#94a3b8;font-weight:500}

.btn-sso{
    width:100%;padding:12px;background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;
    font-size:14px;font-weight:500;color:#334155;cursor:pointer;display:flex;
    align-items:center;justify-content:center;gap:10px;transition:.2s
}
.btn-sso:hover{border-color:#0070C0;color:#0070C0}

.demo-info{margin-top:24px;padding:16px;background:#f1f5f9;border-radius:10px;font-size:12px;color:#475569}
.demo-info strong{color:#002060;display:block;margin-bottom:6px}
.demo-info code{background:#fff;padding:2px 6px;border-radius:4px;font-family:monospace;color:#0070C0}

@media (max-width:900px){
    .left-panel{display:none}
}
</style>
</head>
<body>
<div class="login-wrapper">
    <!-- LEFT BRANDING -->
    <div class="left-panel">
        <div class="brand-top">
            <div class="logo-shield">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L4 5v6c0 5 3.5 9.5 8 11 4.5-1.5 8-6 8-11V5l-8-3z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M8 12l3 3 5-6" stroke="#7eb6ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="brand-text">
                <h1>SIMARIS</h1>
                <p>Risk Management System</p>
            </div>
        </div>

        <div class="left-content">
            <h2>Kelola Risiko<br>dengan <span>Cerdas & Aman</span></h2>
            <p>Platform digital terintegrasi untuk identifikasi, penilaian, pemantauan,
               mitigasi, dan pelaporan risiko organisasi secara real-time.</p>

            <div class="mockup">
                <div class="mockup-bar"><span></span><span></span><span></span></div>
                <div class="mockup-content">
                    <div class="mockup-sidebar">
                        <div style="width:60%"></div><div></div><div style="width:80%"></div>
                        <div></div><div style="width:70%"></div>
                    </div>
                    <div class="mockup-main">
                        <div class="mockup-card">Total Risk<span class="num">42</span></div>
                        <div class="mockup-card">Mitigated<span class="num">28</span></div>
                        <div class="mockup-chart">
                            <span style="height:40%"></span><span style="height:65%"></span>
                            <span style="height:50%"></span><span style="height:80%"></span>
                            <span style="height:70%"></span><span style="height:90%"></span>
                            <span style="height:60%"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="features">
            <div class="feature"><div class="feature-ico"><i class="fas fa-search"></i></div><p>Identifikasi Risiko</p></div>
            <div class="feature"><div class="feature-ico"><i class="fas fa-chart-bar"></i></div><p>Analisis Risiko</p></div>
            <div class="feature"><div class="feature-ico"><i class="fas fa-balance-scale"></i></div><p>Evaluasi Risiko</p></div>
            <div class="feature"><div class="feature-ico"><i class="fas fa-shield-alt"></i></div><p>Mitigasi & Monitoring</p></div>
        </div>
    </div>

    <!-- RIGHT FORM -->
    <div class="right-panel">
        <div class="login-card">
            <h3>Selamat Datang</h3>
            <p class="subtitle">Masuk ke akun SIMARIS Anda untuk melanjutkan</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label class="form-label">Username / Email</label>
                    <div class="input-wrap">
                        <i class="fas fa-user left-ico"></i>
                        <input type="text" name="username" placeholder="Masukkan username atau email"
                               value="<?= e($_POST['username'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock left-ico"></i>
                        <input type="password" name="password" id="password" placeholder="Masukkan password" required>
                        <button type="button" class="toggle-pass" onclick="togglePass()">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="row-between">
                    <label class="checkbox">
                        <input type="checkbox" name="remember"> Ingat saya
                    </label>
                    <a href="#" class="forgot">Lupa Password?</a>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> &nbsp; Login
                </button>

                <div class="divider"><span>atau</span></div>

                <button type="button" class="btn-sso" onclick="alert('Fitur SSO akan diaktifkan oleh administrator')">
                    <i class="fas fa-key"></i> Login dengan SSO Enterprise
                </button>
            </form>

            <div class="demo-info">
                <strong><i class="fas fa-info-circle"></i> Akun Demo (password: <code>password123</code>)</strong>
                Admin: <code>admin</code> &nbsp;|&nbsp;
                Unit Kerja: <code>unitkerja</code> &nbsp;|&nbsp;
                Verifikator: <code>verifikator</code> &nbsp;|&nbsp;
                Pimpinan: <code>pimpinan</code>
            </div>
        </div>
    </div>
</div>

<script>
function togglePass(){
    const p = document.getElementById('password');
    const i = document.getElementById('eyeIcon');
    if(p.type === 'password'){ p.type = 'text'; i.classList.replace('fa-eye','fa-eye-slash'); }
    else{ p.type = 'password'; i.classList.replace('fa-eye-slash','fa-eye'); }
}
</script>
</body>
</html>
