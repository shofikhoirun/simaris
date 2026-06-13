<?php
require_once __DIR__ . '/functions.php';
requireLogin();

$user = currentUser();
$current_page = $current_page ?? '';
$page_title   = $page_title ?? ($pageTitle ?? 'Dashboard');
$page_subtitle = $page_subtitle ?? '';

// Hitung notifikasi belum dibaca
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE (user_id=? OR user_id IS NULL) AND dibaca=0");
$stmt->execute([$user['id']]);
$unread = (int)$stmt->fetchColumn();

$initial = strtoupper(substr($user['nama_lengkap'] ?? 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title) ?> - <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app">

    <!-- ========== SIDEBAR ========== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="logo-box">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L4 5v6c0 5 3.5 9.5 8 11 4.5-1.5 8-6 8-11V5l-8-3z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M8 12l3 3 5-6" stroke="#7eb6ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div>
                <h1>SIMARIS</h1>
                <p>Risk Management</p>
            </div>
        </div>

        <div class="sidebar-user">
            <div class="user-avatar"><?= $initial ?></div>
            <div class="user-info">
                <strong><?= e($user['nama_lengkap']) ?></strong>
                <span><?= formatRole($user['role']) ?></span>
            </div>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-section">
                <div class="menu-title">Utama</div>
                <a href="<?= APP_URL ?>/dashboard.php" class="menu-item <?= $current_page==='dashboard'?'active':'' ?>">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
            </div>

            <div class="menu-section">
                <div class="menu-title">Manajemen Risiko</div>
                <a href="<?= APP_URL ?>/pages/risiko.php" class="menu-item <?= $current_page==='risiko'?'active':'' ?>">
                    <i class="fas fa-clipboard-list"></i> Risk Register
                </a>
                <a href="<?= APP_URL ?>/pages/profil_risiko.php" class="menu-item <?= $current_page==='profil'?'active':'' ?>">
                    <i class="fas fa-file-alt"></i> Profil Risiko
                </a>
                <a href="<?= APP_URL ?>/pages/pemantauan.php" class="menu-item <?= $current_page==='pemantauan'?'active':'' ?>">
                    <i class="fas fa-eye"></i> Pemantauan & Reviu
                </a>
                <a href="<?= APP_URL ?>/pages/tindak_lanjut.php" class="menu-item <?= $current_page==='tindak_lanjut'?'active':'' ?>">
                    <i class="fas fa-tasks"></i> Tindak Lanjut
                </a>
            </div>

            <?php if (in_array($user['role'], ['admin','verifikator'])): ?>
            <div class="menu-section">
                <div class="menu-title">Verifikasi</div>
                <a href="<?= APP_URL ?>/pages/verifikasi.php" class="menu-item <?= $current_page==='verifikasi'?'active':'' ?>">
                    <i class="fas fa-check-circle"></i> Verifikasi Risiko
                </a>
            </div>
            <?php endif; ?>

            <div class="menu-section">
                <div class="menu-title">Pelaporan</div>
                <a href="<?= APP_URL ?>/pages/laporan.php" class="menu-item <?= $current_page==='laporan'?'active':'' ?>">
                    <i class="fas fa-chart-line"></i> Laporan & Export
                </a>
            </div>

            <?php if ($user['role'] === 'admin'): ?>
            <div class="menu-section">
                <div class="menu-title">Administrasi</div>
                <a href="<?= APP_URL ?>/pages/users.php" class="menu-item <?= $current_page==='users'?'active':'' ?>">
                    <i class="fas fa-users-cog"></i> Manajemen User
                </a>
                <a href="<?= APP_URL ?>/pages/unit.php" class="menu-item <?= $current_page==='unit'?'active':'' ?>">
                    <i class="fas fa-building"></i> Unit Kerja
                </a>
                <a href="<?= APP_URL ?>/pages/audit_trail.php" class="menu-item <?= $current_page==='audit'?'active':'' ?>">
                    <i class="fas fa-history"></i> Audit Trail
                </a>
            </div>
            <?php endif; ?>

            <div class="menu-section">
                <div class="menu-title">Akun</div>
                <a href="<?= APP_URL ?>/pages/profil.php" class="menu-item <?= $current_page==='profil_user'?'active':'' ?>">
                    <i class="fas fa-user"></i> Profil Saya
                </a>
                <a href="<?= APP_URL ?>/logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>
    </aside>

    <!-- ========== MAIN CONTENT ========== -->
<div class="main">
    <header class="topbar">
        <div class="topbar-title">
            <h2><?= e($page_title) ?></h2>
            <?php if ($page_subtitle): ?>
                <p><?= e($page_subtitle) ?></p>
            <?php endif; ?>
        </div>

        <div class="topbar-actions">
            <!-- SEARCH MENU -->
            <div style="position:relative;">
                <button
                    type="button"
                    id="menuSearchBtn"
                    class="icon-btn"
                    title="Cari Menu"
                    onclick="toggleMenuSearch(event)"
                >
                    <i class="fas fa-search"></i>
                </button>

                <div
                    id="menuSearchBox"
                    style="display:none;
                           position:absolute;
                           top:55px;
                           right:0;
                           width:320px;
                           background:#fff;
                           border:1px solid #e5e7eb;
                           border-radius:12px;
                           padding:12px;
                           box-shadow:0 10px 30px rgba(0,0,0,.15);
                           z-index:9999;"
                >
                    <input
                        type="text"
                        id="menuSearchInput"
                        placeholder="Cari menu..."
                        oninput="filterMenuItems(this.value)"
                        onkeydown="if(event.key==='Enter'){event.preventDefault();openFirstMenuMatch();}"
                        style="width:100%;
                               padding:10px 12px;
                               border:1px solid #d1d5db;
                               border-radius:8px;
                               outline:none;"
                    >
                </div>
            </div>

            <!-- NOTIFIKASI -->
            <div style="position:relative;">
                <button
                    type="button"
                    id="notifBtn"
                    class="icon-btn"
                    title="Notifikasi"
                    onclick="toggleNotif(event)"
                >
                    <i class="fas fa-bell"></i>
                    <?php if ($unread > 0): ?>
                        <span class="notif-dot"></span>
                    <?php endif; ?>
                </button>

                <div
                    id="notifBox"
                    style="display:none;
                           position:absolute;
                           top:55px;
                           right:0;
                           width:340px;
                           background:#fff;
                           border:1px solid #e5e7eb;
                           border-radius:12px;
                           box-shadow:0 10px 30px rgba(0,0,0,.15);
                           z-index:9999;"
                >
                    <div style="padding:14px 16px;border-bottom:1px solid #eee;">
                        <strong>Notifikasi Sistem</strong>
                    </div>

                    <div style="padding:16px;">
                        <div style="background:#fff7ed;border-left:4px solid #f97316;padding:12px;border-radius:8px;">
                            <div style="font-weight:600;color:#9a3412;">
                                🔧 Pemeliharaan Sistem
                            </div>
                            <div style="font-size:13px;color:#666;margin-top:6px;">
                                Aplikasi SIMARIS akan menjalani pemeliharaan
                                pada tanggal <b>15 Juni 2025</b>
                                pukul <b>20:00 - 22:00 WIB</b>.
                            </div>
                        </div>

                        <div style="margin-top:12px;background:#eff6ff;border-left:4px solid #2563eb;padding:12px;border-radius:8px;">
                            <div style="font-weight:600;color:#1d4ed8;">
                                ℹ️ Informasi
                            </div>
                            <div style="font-size:13px;color:#666;margin-top:6px;">
                                Versi terbaru SIMARIS berhasil diperbarui.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PROFIL -->
            <a href="<?= APP_URL ?>/pages/profil.php" class="icon-btn" title="Profil">
                <i class="fas fa-user"></i>
            </a>
        </div>
    </header>

    <style>
        .topbar{
            height:80px;
            background:#fff;
            border-bottom:1px solid #eef2f7;
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:0 28px;
            box-shadow:0 2px 12px rgba(0,0,0,.04);
        }

        .topbar-title h2{
            margin:0;
            font-size:24px;
            font-weight:800;
            color:#002060;
        }

        .topbar-title p{
            margin-top:4px;
            color:#64748b;
            font-size:14px;
        }

        .topbar-actions{
            display:flex;
            align-items:center;
            gap:12px;
        }

        .icon-btn{
            width:44px;
            height:44px;
            border:none;
            border-radius:12px;
            background:#f3f5f9;
            color:#64748b;
            cursor:pointer;
            display:flex;
            align-items:center;
            justify-content:center;
            text-decoration:none;
            position:relative;
            transition:.2s;
        }

        .icon-btn:hover{
            background:#e9eef5;
            color:#002060;
        }

        .notif-dot{
            position:absolute;
            top:10px;
            right:12px;
            width:8px;
            height:8px;
            background:#ef4444;
            border-radius:50%;
            border:2px solid #fff;
        }
    </style>

    <script>
        function toggleMenuSearch(e){
            e.stopPropagation();

            const box = document.getElementById('menuSearchBox');
            const input = document.getElementById('menuSearchInput');

            if (!box) return;

            if (box.style.display === 'block') {
                box.style.display = 'none';
            } else {
                box.style.display = 'block';
                setTimeout(() => {
                    if (input) input.focus();
                }, 50);
            }
        }

        function filterMenuItems(query){
            const q = query.toLowerCase().trim();
            const items = document.querySelectorAll('.sidebar-menu .menu-item');
            let firstMatch = null;

            items.forEach(item => {
                const match = item.textContent.toLowerCase().includes(q);

                item.style.display = (q === '' || match) ? '' : 'none';

                if (match && !firstMatch) {
                    firstMatch = item;
                }
            });

            window.firstMenuMatch = firstMatch;
        }

        function openFirstMenuMatch(){
            if (window.firstMenuMatch) {
                window.firstMenuMatch.click();
            }
        }

        function toggleNotif(e){
            e.stopPropagation();

            const box = document.getElementById('notifBox');
            if (!box) return;

            box.style.display = (box.style.display === 'block') ? 'none' : 'block';
        }

        document.addEventListener('click', function(e){
            const menuBox = document.getElementById('menuSearchBox');
            const menuBtn = document.getElementById('menuSearchBtn');
            const notifBox = document.getElementById('notifBox');
            const notifBtn = document.getElementById('notifBtn');

            if (menuBox && menuBox.style.display === 'block') {
                if (!menuBox.contains(e.target) && !(menuBtn && menuBtn.contains(e.target))) {
                    menuBox.style.display = 'none';
                }
            }

            if (notifBox && notifBox.style.display === 'block') {
                if (!notifBox.contains(e.target) && !(notifBtn && notifBtn.contains(e.target))) {
                    notifBox.style.display = 'none';
                }
            }
        });
    </script>
            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>">
                    <i class="fas fa-info-circle"></i> <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>