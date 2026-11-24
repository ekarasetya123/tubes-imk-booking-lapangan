-- ============================================================
-- LapanganKu — skema database dan data contoh
-- Database: lapanganku (utf8mb4, InnoDB)
-- Cara import: mysql -u root < database.sql
-- ============================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS lapanganku
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE lapanganku;

-- ------------------------------------------------------------
-- Tabel users: semua peran (penyewa, admin, pemilik)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    no_hp      VARCHAR(20)  NOT NULL,
    password   VARCHAR(255) NOT NULL, -- hash password_hash()
    role       ENUM('penyewa','admin','pemilik') NOT NULL DEFAULT 'penyewa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel lapangan
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lapangan (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama          VARCHAR(100) NOT NULL,
    jenis         ENUM('futsal','badminton') NOT NULL,
    deskripsi     TEXT,
    harga_per_jam DECIMAL(12,2) NOT NULL DEFAULT 0,
    jam_buka      TIME NOT NULL DEFAULT '08:00:00',
    jam_tutup     TIME NOT NULL DEFAULT '22:00:00',
    status        ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel booking
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS booking (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode         VARCHAR(20) NOT NULL UNIQUE, -- format LK-YYYYMMDD-XXXX
    user_id      INT UNSIGNED NOT NULL,
    lapangan_id  INT UNSIGNED NOT NULL,
    tanggal      DATE NOT NULL,
    jam_mulai    TIME NOT NULL,
    jam_selesai  TIME NOT NULL,
    durasi       TINYINT UNSIGNED NOT NULL, -- dalam jam (1-3)
    total_harga  DECIMAL(12,2) NOT NULL,
    status       ENUM('menunggu_pembayaran','menunggu_verifikasi','dikonfirmasi','ditolak','dibatalkan')
                 NOT NULL DEFAULT 'menunggu_pembayaran',
    batas_bayar  DATETIME NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_booking_user     FOREIGN KEY (user_id)     REFERENCES users(id),
    CONSTRAINT fk_booking_lapangan FOREIGN KEY (lapangan_id) REFERENCES lapangan(id),
    INDEX idx_booking_lapangan_tanggal (lapangan_id, tanggal),
    INDEX idx_booking_user (user_id),
    INDEX idx_booking_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel pembayaran (bukti transfer + hasil verifikasi admin)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pembayaran (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id    INT UNSIGNED NOT NULL,
    bukti_file    VARCHAR(255) NOT NULL, -- nama file acak di uploads/bukti/
    catatan_admin VARCHAR(255) NULL,     -- alasan penolakan (wajib saat ditolak)
    verified_by   INT UNSIGNED NULL,      -- users.id admin yang memverifikasi
    verified_at   DATETIME NULL,
    uploaded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pembayaran_booking FOREIGN KEY (booking_id) REFERENCES booking(id) ON DELETE CASCADE,
    CONSTRAINT fk_pembayaran_admin   FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATA CONTOH (seed)
-- Password semua akun demo di-hash dengan password_hash():
--   Admin123!  Pemilik123!  Budi123!  (lihat README untuk daftar lengkap)
-- ============================================================

-- CATATAN: hash password di bawah dihasilkan dengan password_hash() saat instalasi
-- (Admin123! / Pemilik123! / Budi123!). Lihat README bagian "Menghasilkan hash password".
INSERT INTO users (nama, email, no_hp, password, role) VALUES
('Administrator',     'admin@lapanganku.test',    '081234567890', '$2y$10$7.E6ACPdeksUsh.TBYiaweDFT1Vwz6HIBr7bqY11uCbH6KIt5OHOa', 'admin'),
('Pemilik Lapangan', 'pemilik@lapanganku.test',  '081234567891', '$2y$10$ReghR4Dn7w7w31arF0Ieme5yJ74L1IIXH6KRfGeZNTPDAijzlRpVq', 'pemilik'),
('Budi Santoso',      'budi@lapanganku.test',     '081234567892', '$2y$10$EQUnIQkFNSIY77f3/p9WY.hMisCdW.rxeRbJ4415c.jokuagm8NnW', 'penyewa'),
('Siti Rahma',        'siti@lapanganku.test',     '081234567893', '$2y$10$EQUnIQkFNSIY77f3/p9WY.hMisCdW.rxeRbJ4415c.jokuagm8NnW', 'penyewa'),
('Andi Pratama',      'andi@lapanganku.test',     '081234567894', '$2y$10$EQUnIQkFNSIY77f3/p9WY.hMisCdW.rxeRbJ4415c.jokuagm8NnW', 'penyewa');

INSERT INTO lapangan (nama, jenis, deskripsi, harga_per_jam, jam_buka, jam_tutup, status) VALUES
('Lapangan Futsal A — Rumput Sintetis', 'futsal', 'Lapangan futsal indoor dengan rumput sintetis FIFA quality, lampu LED terang, kapasitas 10 pemain.', 150000.00, '08:00:00', '22:00:00', 'aktif'),
('Lapangan Futsal B — Vinyl',           'futsal', 'Lapangan futsal outdoor berlantai vinyl anti slip, cocok untuk latihan tim kecil.',          100000.00, '08:00:00', '22:00:00', 'aktif'),
('Lapangan Badminton 1 — Karpet',        'badminton', 'Lapangan badminton indoor berkarpet, pencahayaan merata, bersih dan nyaman.',             80000.00,  '08:00:00', '22:00:00', 'aktif'),
('Lapangan Badminton 2 — Karpet',        'badminton', 'Lapangan badminton indoor kedua dengan ventilasi baik.',                                    80000.00,  '08:00:00', '22:00:00', 'aktif');

-- ------------------------------------------------------------
-- Data contoh booking (10+ dengan status bervariasi, tanggal relatif terhadap hari ini
-- dihitung saat import lewat variabel sesi agar laporan terisi)
-- ------------------------------------------------------------
SET @today := CURDATE();
SET @kemarin := DATE_SUB(@today, INTERVAL 1 DAY);
SET @lusa := DATE_ADD(@today, INTERVAL 2 DAY);
SET @mingdep := DATE_ADD(@today, INTERVAL 5 DAY);

-- user_id: 3=Budi, 4=Siti, 5=Andi ; lapangan_id: 1-2 futsal, 3-4 badminton
INSERT INTO booking (kode, user_id, lapangan_id, tanggal, jam_mulai, jam_selesai, durasi, total_harga, status, batas_bayar, created_at) VALUES
('LK-20260101-0001', 3, 1, @kemarin,  '18:00:00', '20:00:00', 2, 300000.00, 'dikonfirmasi', DATE_SUB(@kemarin, INTERVAL 2 HOUR), DATE_SUB(@kemarin, INTERVAL 1 DAY)),
('LK-20260101-0002', 4, 3, @kemarin,  '19:00:00', '20:00:00', 1,  80000.00, 'dikonfirmasi', DATE_SUB(@kemarin, INTERVAL 2 HOUR), DATE_SUB(@kemarin, INTERVAL 1 DAY)),
('LK-20260101-0003', 5, 2, DATE_SUB(@today, INTERVAL 3 DAY), '10:00:00', '12:00:00', 2, 200000.00, 'dikonfirmasi', DATE_SUB(@today, INTERVAL 3 DAY), DATE_SUB(@today, INTERVAL 4 DAY)),
('LK-20260101-0004', 3, 4, DATE_SUB(@today, INTERVAL 5 DAY), '16:00:00', '17:00:00', 1,  80000.00, 'ditolak',      DATE_SUB(@today, INTERVAL 5 DAY), DATE_SUB(@today, INTERVAL 6 DAY)),
('LK-20260101-0005', 4, 1, DATE_SUB(@today, INTERVAL 2 DAY), '20:00:00', '22:00:00', 2, 300000.00, 'dibatalkan',   DATE_SUB(@today, INTERVAL 2 DAY), DATE_SUB(@today, INTERVAL 3 DAY)),
('LK-20260101-0006', 5, 2, @lusa,     '09:00:00',  '11:00:00', 2, 200000.00, 'dikonfirmasi', DATE_ADD(@lusa, INTERVAL -2 HOUR), DATE_SUB(@lusa, INTERVAL 1 DAY)),
('LK-20260101-0007', 3, 3, @lusa,     '17:00:00',  '19:00:00', 2, 160000.00, 'menunggu_verifikasi', DATE_ADD(@lusa, INTERVAL -2 HOUR), DATE_SUB(@lusa, INTERVAL 1 HOUR)),
('LK-20260101-0008', 4, 4, @mingdep,  '08:00:00',  '09:00:00', 1,  80000.00, 'menunggu_pembayaran', DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW()),
('LK-20260101-0009', 5, 1, @mingdep,  '19:00:00',  '21:00:00', 2, 300000.00, 'menunggu_verifikasi', DATE_ADD(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
('LK-20260101-0010', 3, 2, @today,    '21:00:00',  '22:00:00', 1, 100000.00, 'dikonfirmasi', DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 1 DAY)),
('LK-20260101-0011', 4, 3, @today,    '07:00:00',  '08:00:00', 1,  80000.00, 'dibatalkan',   DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Pembayaran untuk booking yang menunggu verifikasi / dikonfirmasi
INSERT INTO pembayaran (booking_id, bukti_file, verified_by, verified_at, uploaded_at) VALUES
((SELECT id FROM booking WHERE kode='LK-20260101-0001'), 'seed-bukti-1.jpg', 1, DATE_SUB(@kemarin, INTERVAL 2 HOUR), DATE_SUB(@kemarin, INTERVAL 3 HOUR)),
((SELECT id FROM booking WHERE kode='LK-20260101-0002'), 'seed-bukti-2.jpg', 1, DATE_SUB(@kemarin, INTERVAL 1 HOUR), DATE_SUB(@kemarin, INTERVAL 2 HOUR)),
((SELECT id FROM booking WHERE kode='LK-20260101-0003'), 'seed-bukti-3.jpg', 1, DATE_SUB(@today, INTERVAL 3 DAY), DATE_SUB(@today, INTERVAL 4 DAY)),
((SELECT id FROM booking WHERE kode='LK-20260101-0006'), 'seed-bukti-4.jpg', 1, DATE_SUB(@lusa, INTERVAL 1 DAY), DATE_SUB(@lusa, INTERVAL 2 DAY)),
((SELECT id FROM booking WHERE kode='LK-20260101-0007'), 'seed-bukti-5.jpg', NULL, NULL, DATE_SUB(@lusa, INTERVAL 1 HOUR)),
((SELECT id FROM booking WHERE kode='LK-20260101-0009'), 'seed-bukti-6.jpg', NULL, NULL, DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
((SELECT id FROM booking WHERE kode='LK-20260101-0010'), 'seed-bukti-7.jpg', 1, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR)),
((SELECT id FROM booking WHERE kode='LK-20260101-0004'), 'seed-bukti-8.jpg', 1, DATE_SUB(@today, INTERVAL 5 DAY), DATE_SUB(@today, INTERVAL 6 DAY));

-- Catatan penolakan untuk booking yang ditolak
UPDATE pembayaran SET catatan_admin = 'Bukti transfer tidak terbaca jelas. Mohon unggah ulang foto yang lebih tajam.'
WHERE booking_id = (SELECT id FROM booking WHERE kode='LK-20260101-0004');
