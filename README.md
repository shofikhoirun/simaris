# SIMARIS - Sistem Informasi Manajemen Risiko

> Aplikasi web berbasis PHP + MySQL untuk pengelolaan risiko organisasi sesuai **KMK HK.01.07/MENKES/1354/2024**.
> Menggantikan pengelolaan risiko manual berbasis Excel dengan dashboard enterprise modern.

![Versi](https://img.shields.io/badge/versi-1.0.0-blue) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4) ![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1) ![License](https://img.shields.io/badge/license-MIT-green)

---

## 📋 Daftar Isi

1. [Fitur Utama](#-fitur-utama)
2. [Persyaratan Sistem](#-persyaratan-sistem)
3. [Instalasi Laragon](#-instalasi-laragon)
4. [Setup Project](#-setup-project)
5. [Import Database](#-import-database)
6. [Setup VS Code](#-setup-vs-code)
7. [Setup GitHub](#-setup-github)
8. [Akun Demo](#-akun-demo)
9. [Struktur Project](#-struktur-project)
10. [Troubleshooting](#-troubleshooting)

---

## 🎯 Fitur Utama

### Manajemen Risiko Lengkap
- ✅ **Dashboard real-time** dengan heatmap matriks risiko 5×5
- ✅ **Risk Register** - Identifikasi, analisis, evaluasi risiko (25 kolom)
- ✅ **Profil Risiko** - Tampilan profil 18 kolom sesuai standar KMK
- ✅ **Pemantauan & Reviu** berkala dengan status efektivitas
- ✅ **Tindak Lanjut RPR** dengan tracking progress
- ✅ **Verifikasi berjenjang** oleh verifikator
- ✅ **Laporan Semester** dengan export PDF & Excel

### Sistem & Keamanan
- 🔐 **4 Role RBAC**: Administrator, Unit Kerja, Verifikator, Pimpinan
- 🔐 Password hashing **bcrypt**
- 🔐 **CSRF Protection** di semua form
- 🔐 **Audit Trail** semua aktivitas pengguna
- 🔐 SQL injection protection via **PDO Prepared Statements**

### Tampilan
- 🎨 Desain enterprise modern (Biru Royal `#002060`)
- 🎨 Font **Inter** dari Google Fonts
- 🎨 Responsive (desktop + mobile)
- 🎨 **Chart.js** untuk grafik dinamis
- 🎨 **Font Awesome 6** untuk ikon

### Otomasi Skor Risiko
- Kalkulasi otomatis: **Nilai = Probabilitas × Dampak × Bobot**
- Matriks bobot 5×5 (level 1-9)
- Klasifikasi otomatis 5 tingkat: Sangat Rendah, Rendah, Sedang, Tinggi, Sangat Tinggi
- Visualisasi heatmap warna-coded

---

## 💻 Persyaratan Sistem

- **OS**: Windows 10/11, macOS, atau Linux
- **PHP**: 7.4 atau lebih tinggi (rekomendasi 8.1+)
- **MySQL**: 5.7+ atau MariaDB 10.3+
- **Web Server**: Apache 2.4+ (Laragon, XAMPP, atau Nginx)
- **Browser**: Chrome, Edge, Firefox (versi terbaru)
- **RAM**: 4 GB minimum
- **Disk**: 200 MB ruang kosong

---

## 🚀 Instalasi Laragon (Windows)

Laragon adalah web stack development paling mudah untuk PHP di Windows.

### Step 1: Download & Install Laragon

1. Buka https://laragon.org/download/
2. Download **Laragon - Full** (versi terbaru, ±150 MB)
3. Jalankan installer, install di `C:\laragon` (default)
4. Setelah instalasi selesai, jalankan **Laragon** dari Desktop
5. Klik tombol **Start All** (button hijau di kanan bawah)
   - Apache dan MySQL akan berjalan otomatis

### Step 2: Verifikasi Laragon

- Buka browser → http://localhost
- Harus muncul halaman welcome Laragon ✅
- Buka http://localhost/phpmyadmin → muncul phpMyAdmin ✅

---

## 📦 Setup Project

### Step 1: Copy Project

1. Extract file `simaris.zip` ke folder Laragon:
   ```
   C:\laragon\www\simaris
   ```
2. Struktur akhir harus seperti ini:
   ```
   C:\laragon\www\simaris\
   ├── config\
   ├── pages\
   ├── assets\
   ├── database\
   ├── login.php
   ├── dashboard.php
   └── ... dll
   ```

### Step 2: Akses Project

Ada **dua cara** mengakses project:

**Cara A: URL standar (sederhana)**
```
http://localhost/simaris
```

**Cara B: Pretty URL (auto virtual host Laragon)**
1. Di Laragon, klik **Menu** → **Apache** → **sites-enabled** → ada auto.[YourProject].conf
2. Akses: `http://simaris.test`

---

## 🗄️ Import Database

### Cara 1: Via phpMyAdmin (paling mudah)

1. Buka http://localhost/phpmyadmin
2. Klik **New** di sidebar kiri → buat database baru:
   - Nama: `simaris_db`
   - Collation: `utf8mb4_unicode_ci`
   - Klik **Create**
3. Pilih database `simaris_db` → tab **Import**
4. **Choose File** → pilih `database/simaris.sql`
5. Klik **Go** di bawah
6. ✅ Database berhasil di-import dengan data demo

### Cara 2: Via Command Line (lebih cepat)

1. Di Laragon, klik **Menu** → **Tools** → **Quick add** → **MySQL**
2. Atau buka **Terminal** Laragon (Ctrl+Alt+T)
3. Jalankan:
   ```bash
   cd C:\laragon\www\simaris
   mysql -u root < database/simaris.sql
   ```

### Verifikasi Database

Buka phpMyAdmin → klik `simaris_db` → harus ada 10 tabel:
- ✅ users, unit_kerja, kriteria_likelihood, kriteria_impact, matriks_bobot
- ✅ risiko, pemantauan, tindak_lanjut, notifikasi, audit_trail

### Konfigurasi (Opsional)

Jika MySQL bukan default Laragon, edit `config/database.php`:
```php
define('DB_HOST', 'localhost');     // default
define('DB_USER', 'root');          // default Laragon
define('DB_PASS', '');              // default Laragon kosong
define('DB_NAME', 'simaris_db');
```

---

## 🛠️ Setup VS Code

### Step 1: Install VS Code

Download dari https://code.visualstudio.com → install seperti biasa.

### Step 2: Install Extensions Wajib

Buka VS Code → tab Extensions (Ctrl+Shift+X), install:

| Extension | ID | Fungsi |
|-----------|-----|--------|
| **PHP Intelephense** | `bmewburn.vscode-intelephense-client` | Autocomplete & IntelliSense PHP |
| **PHP Debug** | `xdebug.php-debug` | Debugging |
| **MySQL** | `cweijan.vscode-mysql-client2` | Akses MySQL dari VS Code |
| **Live Server** | `ritwickdey.LiveServer` | Auto-reload HTML |
| **Auto Rename Tag** | `formulahendry.auto-rename-tag` | Rename HTML tag berpasangan |
| **GitLens** | `eamodio.gitlens` | Git superpower |
| **Prettier** | `esbenp.prettier-vscode` | Format kode |

Atau install semua sekaligus via Terminal:
```bash
code --install-extension bmewburn.vscode-intelephense-client
code --install-extension xdebug.php-debug
code --install-extension cweijan.vscode-mysql-client2
code --install-extension ritwickdey.LiveServer
code --install-extension formulahendry.auto-rename-tag
code --install-extension eamodio.gitlens
code --install-extension esbenp.prettier-vscode
```

### Step 3: Open Project

1. VS Code → **File** → **Open Folder**
2. Pilih `C:\laragon\www\simaris`
3. **Trust** workspace jika muncul prompt

### Step 4: Setting PHP Path (Otomatis Intellisense)

1. **File** → **Preferences** → **Settings** (Ctrl+,)
2. Search: `php.validate.executablePath`
3. Set:
   ```
   C:\laragon\bin\php\php-8.1.x-Win32-vs16-x64\php.exe
   ```
   (sesuaikan versi PHP di folder Laragon Anda)

### Step 5: Settings JSON (rekomendasi)

Buka **Command Palette** (Ctrl+Shift+P) → ketik "Open User Settings (JSON)":

```json
{
    "editor.formatOnSave": true,
    "editor.defaultFormatter": "esbenp.prettier-vscode",
    "[php]": {
        "editor.defaultFormatter": "bmewburn.vscode-intelephense-client"
    },
    "files.autoSave": "afterDelay",
    "intelephense.environment.phpVersion": "8.1.0",
    "emmet.includeLanguages": {
        "php": "html"
    }
}
```

---

## 🐙 Setup GitHub

### Step 1: Install Git for Windows

1. Download https://git-scm.com/download/win
2. Install dengan setting default
3. Verifikasi: buka terminal/cmd → ketik `git --version`

### Step 2: Konfigurasi Git Identitas

```bash
git config --global user.name "Nama Lengkap Anda"
git config --global user.email "email@anda.com"
```

### Step 3: Buat Repository di GitHub

1. Buka https://github.com → Login
2. Klik tombol **+** kanan atas → **New repository**
3. Setting:
   - Repository name: `simaris`
   - Description: `Sistem Informasi Manajemen Risiko`
   - Private (rekomendasi) atau Public
   - **Jangan** centang "Initialize with README" (kita sudah punya)
4. Klik **Create repository**
5. Catat URL repo, contoh: `https://github.com/USERNAME/simaris.git`

### Step 4: Push Project ke GitHub

Buka terminal di folder project (`C:\laragon\www\simaris`):

```bash
# 1. Inisialisasi git lokal
git init

# 2. Add semua file (kecuali yang ada di .gitignore)
git add .

# 3. Commit pertama
git commit -m "Initial commit: SIMARIS v1.0.0"

# 4. Rename branch ke main
git branch -M main

# 5. Tambah remote (ganti USERNAME)
git remote add origin https://github.com/USERNAME/simaris.git

# 6. Push ke GitHub
git push -u origin main
```

Jika diminta login, gunakan **Personal Access Token** (bukan password):
- GitHub → Settings → Developer settings → Personal access tokens → Generate new token
- Centang scope **repo**
- Copy token & paste sebagai password saat git push

### Step 5: Workflow Selanjutnya

```bash
# Setiap kali ada perubahan
git add .
git commit -m "Tambah fitur XYZ"
git push

# Pull update terbaru
git pull
```

### Step 6: Clone di Komputer Lain

```bash
cd C:\laragon\www
git clone https://github.com/USERNAME/simaris.git
cd simaris
# Import database/simaris.sql
# Selesai!
```

---

## 👤 Akun Demo

Setelah import database, gunakan akun berikut untuk login:

| Username      | Password      | Role            | Akses                                       |
|---------------|---------------|-----------------|---------------------------------------------|
| `admin`       | `password123` | Administrator   | Full access (semua menu)                    |
| `unitkerja`   | `password123` | Unit Kerja      | Input & kelola data unitnya                 |
| `verifikator` | `password123` | Verifikator     | Verifikasi risiko (approve/reject)          |
| `pimpinan`    | `password123` | Pimpinan        | View laporan & dashboard (read-only)        |

> ⚠️ **PENTING**: Ganti password default segera setelah deployment ke production!

---

## 📁 Struktur Project

```
simaris/
├── config/
│   └── database.php          # Konfigurasi DB & APP
├── includes/
│   ├── functions.php          # Helper functions
│   ├── header.php             # Layout sidebar + topbar
│   └── footer.php             # Closing tags + scripts
├── assets/
│   ├── css/style.css          # Stylesheet utama
│   ├── js/app.js              # JavaScript helpers
│   └── img/                   # Gambar & logo
├── pages/
│   ├── risiko.php             # Risk Register (CRUD)
│   ├── profil_risiko.php      # Profil risiko detail
│   ├── pemantauan.php         # Pemantauan & reviu
│   ├── tindak_lanjut.php      # Tindak lanjut RPR
│   ├── verifikasi.php         # Verifikasi (admin/verifikator)
│   ├── laporan.php            # Laporan + Export PDF/Excel
│   ├── users.php              # Manajemen User (admin)
│   ├── unit.php               # Manajemen Unit (admin)
│   ├── audit_trail.php        # Log audit (admin)
│   └── profil.php             # Profil pengguna sendiri
├── uploads/                   # File bukti tindak lanjut
├── exports/                   # File hasil export
├── database/
│   └── simaris.sql            # Schema + seed data
├── index.php                  # Entry point (redirect)
├── login.php                  # Halaman login split-screen
├── dashboard.php              # Dashboard utama
├── logout.php                 # Logout handler
├── .gitignore                 # Git ignore rules
└── README.md                  # File ini
```

---

## 🔧 Troubleshooting

### ❌ Error: "Koneksi Database Gagal"
- Pastikan Apache **dan** MySQL aktif di Laragon (icon merah jadi hijau)
- Cek database `simaris_db` ada di phpMyAdmin
- Cek `config/database.php`: kredensial harus sesuai

### ❌ Error: "Class 'PDO' not found"
- Edit `C:\laragon\bin\php\php-x.x.x\php.ini`
- Cari & uncomment baris: `extension=pdo_mysql`
- Restart Apache di Laragon

### ❌ Halaman blank / putih
- Aktifkan error reporting di `config/database.php` (sudah default ON)
- Cek error log: `C:\laragon\bin\apache\httpd-x.x\logs\error.log`

### ❌ File upload error
- Pastikan folder `uploads/` writable
- Edit `php.ini`: `upload_max_filesize = 10M`, `post_max_size = 10M`

### ❌ "Access denied for user 'root'"
- Laragon default password kosong → `DB_PASS = ''`
- Jika sudah diset password, sesuaikan di `config/database.php`

### ❌ Export PDF tidak muncul jendela print
- Browser memblokir popup → klik ikon popup blocker → Always allow
- Atau langsung Ctrl+P di halaman PDF preview

---

## 📚 Dokumentasi Tambahan

### Cara Menambah Risiko

1. Login sebagai `admin` atau `unitkerja`
2. Menu **Risk Register** → klik **Tambah Risiko**
3. Isi form 4 bagian:
   - **Identifikasi**: kode, nama, unit, sumber, penyebab, dampak
   - **Analisis**: pengendalian, P × D (auto-calc bobot & nilai)
   - **Evaluasi**: pilihan penanganan, RPR, jadwal
   - **Target**: target P & D setelah mitigasi
4. Klik **Simpan**

### Cara Verifikasi Risiko

1. Login sebagai `verifikator`
2. Menu **Verifikasi Risiko** → tab **Menunggu**
3. Klik ikon ✅ (Setujui) atau ❌ (Tolak)
4. Isi catatan → konfirmasi

### Cara Export Laporan

1. Menu **Laporan & Export**
2. Pilih periode (Semester I/II)
3. Filter unit/level/status sesuai kebutuhan
4. Klik **Export Excel** atau **Export PDF**

---

## 📜 Lisensi

MIT License - Bebas digunakan, dimodifikasi, dan didistribusikan.

## 👥 Kontribusi

Pull request dipersilakan. Untuk perubahan besar, buka issue terlebih dahulu untuk mendiskusikan.

## 📞 Dukungan

Jika ada pertanyaan atau masalah:
- 🐛 [Buka Issue di GitHub](https://github.com/USERNAME/simaris/issues)
- 📧 Email: admin@example.com

---

**SIMARIS v1.0.0** • Sistem Informasi Manajemen Risiko • © 2026
