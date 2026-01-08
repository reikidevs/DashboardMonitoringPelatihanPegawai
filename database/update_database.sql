-- Update Database untuk Sync Google Sheets
-- Jalankan file ini untuk menambahkan kolom sync_hash

USE monitor_pelatihan_pegawai;

-- Ubah kolom NIP menjadi nullable (opsional)
ALTER TABLE pegawai 
MODIFY COLUMN nip VARCHAR(50) NULL;

-- Tambah kolom sync_hash ke tabel monitoring_pelatihan jika belum ada
ALTER TABLE monitoring_pelatihan 
ADD COLUMN IF NOT EXISTS sync_hash VARCHAR(64) DEFAULT NULL AFTER jumlah_jp;

-- Tambah index untuk sync_hash jika belum ada
CREATE INDEX IF NOT EXISTS idx_sync_hash ON monitoring_pelatihan(sync_hash);

-- Buat tabel jadwal_peserta untuk relasi jadwal dengan pegawai
CREATE TABLE IF NOT EXISTS jadwal_peserta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jadwal_id INT NOT NULL,
    pegawai_id INT NOT NULL,
    status ENUM('Terdaftar', 'Hadir', 'Tidak Hadir', 'Batal') DEFAULT 'Terdaftar',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jadwal_id) REFERENCES jadwal_pelatihan(id) ON DELETE CASCADE,
    FOREIGN KEY (pegawai_id) REFERENCES pegawai(id) ON DELETE CASCADE,
    UNIQUE KEY unique_peserta (jadwal_id, pegawai_id)
);

-- Pastikan tabel settings ada
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default spreadsheet ID
INSERT INTO settings (setting_key, setting_value) 
VALUES ('gsheet_id', '1KT8DWSKWpJxJY4elwNwtBPV39_cXbpD5bWfwD_fbwPk')
ON DUPLICATE KEY UPDATE setting_value = '1KT8DWSKWpJxJY4elwNwtBPV39_cXbpD5bWfwD_fbwPk';

INSERT INTO settings (setting_key, setting_value) 
VALUES ('gsheet_name', 'Form Responses 1')
ON DUPLICATE KEY UPDATE setting_value = 'Form Responses 1';

-- Insert default auto-sync settings
INSERT INTO settings (setting_key, setting_value) 
VALUES ('sync_interval_minutes', '5')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO settings (setting_key, setting_value) 
VALUES ('auto_sync_enabled', '1')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

SELECT 'Database berhasil diupdate!' as status;
