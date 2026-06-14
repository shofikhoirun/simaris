-- =====================================================
-- SIMARIS - Sistem Informasi Manajemen Risiko
-- Database Schema (MySQL / MariaDB)
-- =====================================================



-- -----------------------------------------------------
-- Tabel: users (Role-Based Access Control)
-- -----------------------------------------------------
CREATE TABLE `users` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)  NOT NULL UNIQUE,
  `email`         VARCHAR(100) NOT NULL UNIQUE,
  `password`      VARCHAR(255) NOT NULL,
  `nama_lengkap`  VARCHAR(150) NOT NULL,
  `role`          ENUM('admin','unit_kerja','verifikator','pimpinan') NOT NULL DEFAULT 'unit_kerja',
  `unit_id`       INT(11)      DEFAULT NULL,
  `foto`          VARCHAR(255) DEFAULT NULL,
  `status`        ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `last_login`    DATETIME     DEFAULT NULL,
  `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Tabel: unit_kerja
-- -----------------------------------------------------
CREATE TABLE `unit_kerja` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `kode_unit`  VARCHAR(20)  NOT NULL UNIQUE,
  `nama_unit`  VARCHAR(150) NOT NULL,
  `deskripsi`  TEXT,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Tabel: kriteria_likelihood (1-5)
-- -----------------------------------------------------
CREATE TABLE `kriteria_likelihood` (
  `id`        INT(11)     NOT NULL AUTO_INCREMENT,
  `level`     INT(1)      NOT NULL UNIQUE,
  `nama`      VARCHAR(50) NOT NULL,
  `deskripsi` TEXT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Tabel: kriteria_impact (1-5)
-- -----------------------------------------------------
CREATE TABLE `kriteria_impact` (
  `id`        INT(11)     NOT NULL AUTO_INCREMENT,
  `level`     INT(1)      NOT NULL UNIQUE,
  `nama`      VARCHAR(50) NOT NULL,
  `deskripsi` TEXT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Tabel: matriks_bobot (Bobot 5x5)
-- -----------------------------------------------------
CREATE TABLE `matriks_bobot` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `likelihood` INT(1)  NOT NULL,
  `impact`     INT(1)  NOT NULL,
  `bobot`      INT(2)  NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lh_im` (`likelihood`,`impact`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Tabel: risiko (Risk Register utama)
-- -----------------------------------------------------
CREATE TABLE `risiko` (
  `id`              INT(11)      NOT NULL AUTO_INCREMENT,
  `kode_risiko`     VARCHAR(30)  NOT NULL UNIQUE,
  `nama_risiko`     VARCHAR(255) NOT NULL,
  `unit_id`         INT(11)      NOT NULL,
  `penyebab`        TEXT         NOT NULL,
  `sumber_risiko`   ENUM('internal','eksternal') NOT NULL,
  `kategori_cuc`    ENUM('C','UC') NOT NULL COMMENT 'Controllable / Uncontrollable',
  `dampak_uraian`   TEXT         NOT NULL,
  -- Pengendalian saat ini
  `pengendalian`    TEXT,
  `efektivitas`     ENUM('efektif','tidak_efektif') DEFAULT 'tidak_efektif',
  -- Analisis Risiko (Awal)
  `likelihood`      INT(1)       NOT NULL,
  `impact`          INT(1)       NOT NULL,
  `bobot`           INT(2)       NOT NULL,
  `nilai_risiko`    INT(4)       NOT NULL COMMENT 'P x D x Bobot',
  `tingkat_risiko`  ENUM('sangat_rendah','rendah','sedang','tinggi','sangat_tinggi') NOT NULL,
  `prioritas`       INT(1)       NOT NULL COMMENT '1=ST, 2=T, 3=S, 4=R, 5=SR',
  -- Evaluasi
  `selera_risiko`   ENUM('dalam_batas','diatas_batas') NOT NULL,
  `pilihan_penanganan` ENUM('menerima','mitigasi','menghindari','berbagi') NOT NULL,
  -- Rencana Penanganan
  `rpr_uraian`      TEXT,
  `jadwal_mulai`    DATE,
  `jadwal_selesai`  DATE,
  `pj_id`           INT(11)      DEFAULT NULL COMMENT 'Penanggung Jawab',
  -- Target Penurunan
  `target_likelihood`     INT(1),
  `target_impact`         INT(1),
  `target_bobot`          INT(2),
  `target_nilai_risiko`   INT(4),
  `target_tingkat_risiko` ENUM('sangat_rendah','rendah','sedang','tinggi','sangat_tinggi'),
  -- Status Mitigasi
  `status_mitigasi` ENUM('belum','belum_dimulai','dalam_proses','on_progress','selesai') DEFAULT 'belum_dimulai',
  `progress`        INT(3)       DEFAULT 0 COMMENT 'Persentase 0-100',
  -- Verifikasi
  `status_verifikasi` ENUM('draft','menunggu','disetujui','ditolak') DEFAULT 'draft',
  `verifikator_id`    INT(11)    DEFAULT NULL,
  `tanggal_verifikasi` DATETIME  DEFAULT NULL,
  `catatan_verifikasi` TEXT,
  -- Metadata
  `created_by`      INT(11)      NOT NULL,
  `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_unit` (`unit_id`),
  KEY `idx_tingkat` (`tingkat_risiko`),
  KEY `idx_status` (`status_mitigasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Tabel: pemantauan (Tabel Pemantauan & Reviu)
-- -----------------------------------------------------
CREATE TABLE `pemantauan` (
  `id`              INT(11)      NOT NULL AUTO_INCREMENT,
  `risiko_id`       INT(11)      NOT NULL,
  `tanggal`         DATE         NOT NULL,
  `likelihood_post` INT(1)       NOT NULL,
  `impact_post`     INT(1)       NOT NULL,
  `bobot_post`      INT(2)       NOT NULL,
  `nilai_post`      INT(4)       NOT NULL,
  `tingkat_post`    ENUM('sangat_rendah','rendah','sedang','tinggi','sangat_tinggi') NOT NULL,
  `simpulan`        ENUM('tidak_ada_penurunan','penurunan','peningkatan') NOT NULL,
  `efektivitas`     ENUM('efektif','tidak_efektif') NOT NULL,
  `hasil_pemantauan` TEXT,
  `user_id`         INT(11)      NOT NULL,
  `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_risiko` (`risiko_id`),
  CONSTRAINT `fk_pemantauan_risiko` FOREIGN KEY (`risiko_id`) REFERENCES `risiko` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Tabel: tindak_lanjut (Update mitigasi)
-- -----------------------------------------------------
CREATE TABLE `tindak_lanjut` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `risiko_id`  INT(11)      NOT NULL,
  `tanggal`    DATE         NOT NULL,
  `uraian`     TEXT         NOT NULL,
  `progress`   INT(3)       NOT NULL DEFAULT 0,
  `status`     ENUM('rencana','on_progress','selesai','terhambat','belum_dimulai','dalam_proses') NOT NULL DEFAULT 'rencana',
  `catatan`    TEXT         DEFAULT NULL,
  `file_bukti` VARCHAR(255) DEFAULT NULL,
  `user_id`    INT(11)      NOT NULL,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_risiko_tl` (`risiko_id`),
  CONSTRAINT `fk_tl_risiko` FOREIGN KEY (`risiko_id`) REFERENCES `risiko` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Tabel: notifikasi
-- -----------------------------------------------------
CREATE TABLE `notifikasi` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)      DEFAULT NULL COMMENT 'NULL = broadcast',
  `judul`      VARCHAR(150) NOT NULL,
  `pesan`      TEXT         NOT NULL,
  `tipe`       ENUM('info','warning','danger','success') DEFAULT 'info',
  `link`       VARCHAR(255) DEFAULT NULL,
  `dibaca`     TINYINT(1)   DEFAULT 0,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_notif` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Tabel: audit_trail
-- -----------------------------------------------------
CREATE TABLE `audit_trail` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)      NOT NULL,
  `aksi`       VARCHAR(50)  NOT NULL COMMENT 'CREATE/UPDATE/DELETE/LOGIN',
  `tabel`      VARCHAR(50)  NOT NULL,
  `record_id`  INT(11)      DEFAULT NULL,
  `deskripsi`  TEXT,
  `data_lama`  TEXT,
  `data_baru`  TEXT,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(255),
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_audit` (`user_id`),
  KEY `idx_tanggal` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- DATA AWAL (SEEDING)
-- =====================================================

-- Unit Kerja
INSERT INTO `unit_kerja` (`kode_unit`,`nama_unit`,`deskripsi`) VALUES
('UK-001','Direktorat Utama','Unit pimpinan utama organisasi'),
('UK-002','Bagian Keuangan','Pengelola keuangan dan akuntansi'),
('UK-003','Bagian SDM','Pengelola sumber daya manusia'),
('UK-004','Bagian Operasional','Pengelola operasional harian'),
('UK-005','Bagian Teknologi Informasi','Pengelola infrastruktur TI'),
('UK-006','Bagian Pengadaan','Pengelola pengadaan barang dan jasa'),
('UK-007','Bagian Hukum','Pengelola aspek hukum dan kepatuhan');

-- Users (password default: password123 - ganti setelah login)
-- Hash dibawah adalah hasil password_hash('password123', PASSWORD_BCRYPT)
INSERT INTO `users` (`username`,`email`,`password`,`nama_lengkap`,`role`,`unit_id`) VALUES
('admin','admin@simaris.id','$2y$10$E0NRS/uYqo2Aqg1.RD1jQOR8e3sEYx7c8c2lyqxXTGJYqQ9w7yk0G','Administrator SIMARIS','admin',1),
('unitkerja','unit@simaris.id','$2y$10$E0NRS/uYqo2Aqg1.RD1jQOR8e3sEYx7c8c2lyqxXTGJYqQ9w7yk0G','Petugas Unit Keuangan','unit_kerja',2),
('verifikator','verif@simaris.id','$2y$10$E0NRS/uYqo2Aqg1.RD1jQOR8e3sEYx7c8c2lyqxXTGJYqQ9w7yk0G','Verifikator Risiko','verifikator',1),
('pimpinan','pimpinan@simaris.id','$2y$10$E0NRS/uYqo2Aqg1.RD1jQOR8e3sEYx7c8c2lyqxXTGJYqQ9w7yk0G','Pimpinan Organisasi','pimpinan',1);

-- Kriteria Likelihood
INSERT INTO `kriteria_likelihood` (`level`,`nama`,`deskripsi`) VALUES
(1,'Sangat Jarang','Hampir tidak pernah terjadi (< 1x dalam 5 tahun)'),
(2,'Jarang','Kemungkinan kecil terjadi (1x dalam 2-5 tahun)'),
(3,'Mungkin','Mungkin terjadi (1x dalam 1-2 tahun)'),
(4,'Sering','Kemungkinan besar terjadi (beberapa kali setahun)'),
(5,'Sangat Sering','Hampir pasti terjadi (sering dalam setahun)');

-- Kriteria Impact
INSERT INTO `kriteria_impact` (`level`,`nama`,`deskripsi`) VALUES
(1,'Tidak Signifikan','Dampak sangat kecil, tidak mengganggu operasional'),
(2,'Minor','Dampak kecil, dapat ditangani internal'),
(3,'Moderat','Dampak sedang, memerlukan koordinasi'),
(4,'Mayor','Dampak besar, mengganggu pencapaian sasaran'),
(5,'Katastropik','Dampak sangat besar, mengancam kelangsungan');

-- Matriks Bobot 5x5
INSERT INTO `matriks_bobot` (`likelihood`,`impact`,`bobot`) VALUES
(1,1,1),(1,2,2),(1,3,3),(1,4,4),(1,5,5),
(2,1,2),(2,2,3),(2,3,4),(2,4,5),(2,5,6),
(3,1,3),(3,2,4),(3,3,5),(3,4,6),(3,5,7),
(4,1,4),(4,2,5),(4,3,6),(4,4,7),(4,5,8),
(5,1,5),(5,2,6),(5,3,7),(5,4,8),(5,5,9);

-- Contoh data Risiko
INSERT INTO `risiko` (
  `kode_risiko`,`nama_risiko`,`unit_id`,`penyebab`,`sumber_risiko`,`kategori_cuc`,
  `dampak_uraian`,`pengendalian`,`efektivitas`,
  `likelihood`,`impact`,`bobot`,`nilai_risiko`,`tingkat_risiko`,`prioritas`,
  `selera_risiko`,`pilihan_penanganan`,`rpr_uraian`,`jadwal_mulai`,`jadwal_selesai`,`pj_id`,
  `target_likelihood`,`target_impact`,`target_bobot`,`target_nilai_risiko`,`target_tingkat_risiko`,
  `status_mitigasi`,`progress`,`status_verifikasi`,`created_by`
) VALUES
('RSK-001','Keterlambatan penyusunan laporan keuangan',2,
 'SDM kurang, sistem belum terintegrasi','internal','C',
 'Penilaian audit menurun, reputasi terganggu','SOP penyusunan laporan triwulanan','tidak_efektif',
 4,4,7,112,'tinggi',2,
 'diatas_batas','mitigasi','Pelatihan SDM dan implementasi sistem terintegrasi',
 '2026-01-01','2026-06-30',2,
 2,3,4,24,'rendah',
 'dalam_proses',45,'disetujui',1),

('RSK-002','Kebocoran data pengguna sistem',5,
 'Sistem keamanan belum optimal, password lemah','internal','C',
 'Pelanggaran UU PDP, denda regulator, reputasi rusak','Firewall dan antivirus standar','tidak_efektif',
 3,5,7,105,'tinggi',2,
 'diatas_batas','mitigasi','Penerapan enkripsi end-to-end dan audit keamanan berkala',
 '2026-02-01','2026-08-31',2,
 2,3,4,24,'rendah',
 'dalam_proses',30,'disetujui',1),

('RSK-003','Turnover pegawai kunci tinggi',3,
 'Kompensasi kurang kompetitif, beban kerja berlebih','internal','C',
 'Kehilangan knowledge, gangguan operasional','Evaluasi gaji tahunan','efektif',
 3,3,5,45,'sedang',3,
 'dalam_batas','menerima','Pemantauan berkala kepuasan pegawai',
 '2026-01-15','2026-12-31',2,
 2,2,3,12,'rendah',
 'belum_dimulai',0,'menunggu',1),

('RSK-004','Gangguan jaringan internet kantor',5,
 'Provider tidak stabil, infrastruktur lama','eksternal','UC',
 'Pelayanan terganggu, produktivitas turun','SLA dengan ISP','efektif',
 2,3,4,24,'rendah',4,
 'dalam_batas','menerima','Monitoring uptime jaringan',
 '2026-01-01','2026-12-31',2,
 2,2,3,12,'sangat_rendah',
 'selesai',100,'disetujui',1),

('RSK-005','Pengadaan barang tidak sesuai spesifikasi',6,
 'Verifikasi vendor lemah, dokumen lelang tidak detail','internal','C',
 'Barang tidak terpakai, anggaran terbuang','SOP pengadaan, panitia lelang','tidak_efektif',
 3,4,6,72,'sedang',3,
 'diatas_batas','mitigasi','Perbaikan SOP pengadaan dan training panitia',
 '2026-03-01','2026-09-30',2,
 2,2,3,12,'rendah',
 'dalam_proses',60,'disetujui',1);

-- Contoh Tindak Lanjut
INSERT INTO `tindak_lanjut` (`risiko_id`,`tanggal`,`uraian`,`progress`,`status`,`user_id`) VALUES
(1,'2026-02-15','Pelatihan akuntansi tahap 1 selesai untuk 5 staf',45,'dalam_proses',2),
(2,'2026-03-10','Audit keamanan internal dilaksanakan',30,'dalam_proses',2),
(5,'2026-04-01','Revisi SOP pengadaan selesai',60,'dalam_proses',2);

-- Contoh Notifikasi
INSERT INTO `notifikasi` (`user_id`,`judul`,`pesan`,`tipe`,`link`) VALUES
(NULL,'Selamat Datang di SIMARIS','Sistem Informasi Manajemen Risiko siap digunakan','success','dashboard.php'),
(1,'Reminder Mitigasi','Risiko RSK-001 mendekati deadline','warning','pages/risiko.php'),
(1,'Risiko Baru','Risiko RSK-005 perlu verifikasi','info','pages/verifikasi.php');
