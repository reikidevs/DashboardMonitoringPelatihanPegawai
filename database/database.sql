-- Database Monitoring Pelatihan Pegawai
-- Untuk shared hosting: buat database manual via cPanel, lalu pilih database tersebut sebelum import
-- CREATE DATABASE IF NOT EXISTS monitor_pelatihan_pegawai;
-- USE monitor_pelatihan_pegawai;

-- Tabel Pegawai
CREATE TABLE pegawai (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(255) NOT NULL,
    nip VARCHAR(50) NOT NULL UNIQUE,
    jabatan VARCHAR(255),
    email VARCHAR(100),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Kategori Pelatihan
CREATE TABLE kategori_pelatihan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL
);

-- Tabel Lingkup Pelatihan
CREATE TABLE lingkup_pelatihan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL
);

-- Tabel Pelatihan
CREATE TABLE pelatihan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(255) NOT NULL,
    lingkup_id INT,
    kategori_id INT,
    tipe ENUM('Daring', 'Luring', 'Hybrid') DEFAULT 'Daring',
    jumlah_jp INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lingkup_id) REFERENCES lingkup_pelatihan(id) ON DELETE SET NULL,
    FOREIGN KEY (kategori_id) REFERENCES kategori_pelatihan(id) ON DELETE SET NULL
);

-- Tabel Jadwal Pelatihan
CREATE TABLE jadwal_pelatihan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pelatihan_id INT NOT NULL,
    tanggal_mulai DATE,
    tanggal_selesai DATE,
    rencana_peserta INT DEFAULT 0,
    biaya DECIMAL(15,2) DEFAULT 0,
    status ENUM('Not Started', 'In-Progress', 'Completed', 'Cancelled') DEFAULT 'Not Started',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pelatihan_id) REFERENCES pelatihan(id) ON DELETE CASCADE
);

-- Tabel Monitoring Pelatihan (Pegawai ikut pelatihan)
CREATE TABLE monitoring_pelatihan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pegawai_id INT NOT NULL,
    pelatihan_id INT NOT NULL,
    tahun YEAR,
    pelaksanaan DATE,
    no_sertifikat VARCHAR(255),
    jumlah_jp INT DEFAULT 0,
    sync_hash VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pegawai_id) REFERENCES pegawai(id) ON DELETE CASCADE,
    FOREIGN KEY (pelatihan_id) REFERENCES pelatihan(id) ON DELETE CASCADE,
    INDEX idx_sync_hash (sync_hash)
);

-- Tabel Settings
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert Data Kategori
INSERT INTO kategori_pelatihan (nama) VALUES 
('Mutlak'), ('Penting'), ('Perlu'), ('Pelatihan IDEAS');

-- Insert Data Lingkup
INSERT INTO lingkup_pelatihan (nama) VALUES 
('TI'), ('Komoditi Obat'), ('Komoditi Pangan'), ('Komoditi OBA-SK'), ('Komoditi Kosmetik'), ('Keuangan'), ('Lainnya');

-- Sample Data Pegawai
INSERT INTO pegawai (nama, nip, jabatan, email, phone) VALUES
('Anna Tresia Siahaan, S.Gz', '199505022025062007', 'Pengawas Farmasi dan Makanan Ahli Pertama', 'anna@example.com', '08123456789'),
('Budi Santoso', '199001012020011001', 'Pranata Komputer Ahli Pertama', 'budi@example.com', '08123456790'),
('Citra Dewi', '199203152021012002', 'Penata Laporan Keuangan', 'citra@example.com', '08123456791');

-- Tabel Kewajiban Pelatihan (pelatihan wajib per pegawai)
CREATE TABLE kewajiban_pelatihan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pegawai_id INT NOT NULL,
    pelatihan_id INT NOT NULL,
    tahun_target YEAR,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pegawai_id) REFERENCES pegawai(id) ON DELETE CASCADE,
    FOREIGN KEY (pelatihan_id) REFERENCES pelatihan(id) ON DELETE CASCADE,
    UNIQUE KEY unique_kewajiban (pegawai_id, pelatihan_id)
);

-- Sample Data Pelatihan
INSERT INTO pelatihan (nama, lingkup_id, kategori_id, tipe, jumlah_jp) VALUES
('Diklat fungsional penguatan Pranata Komputer', 1, 3, 'Daring', 5),
('Manajemen Layanan TI', 1, 3, 'Daring', 3),
('Diklat Manajemen', 1, 1, 'Daring', 2),
('Pengelolaan Data', 1, 2, 'Daring', 5),
('Pengantar Analisa Data', 1, 2, 'Daring', 2),
('Visualisasi Data Interaktif dengan Tableau', 3, 4, 'Daring', 4),
('Desain Presentasi', 6, 2, 'Hybrid', 2);
