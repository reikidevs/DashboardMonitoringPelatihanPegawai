# Sistem Monitoring Pelatihan Pegawai BPOM

Aplikasi web untuk monitoring dan pengelolaan data pelatihan pegawai BPOM dengan fitur sinkronisasi otomatis dari Google Sheets.

## 📁 Struktur Folder

```
/
├── assets/                 # Asset statis (CSS, JS, Images)
│   ├── BADAN_POM.png      # Logo BPOM
│   └── style.css          # Custom CSS
│
├── config/                 # Konfigurasi aplikasi
│   └── config.php         # Database & helper functions
│
├── includes/               # File include (header, footer)
│   ├── header.php         # Header & navbar
│   └── footer.php         # Footer
│
├── pages/                  # Halaman-halaman aplikasi
│   ├── pegawai.php        # Kelola data pegawai
│   ├── pegawai_detail.php # Detail pegawai
│   ├── pelatihan.php      # Database pelatihan
│   ├── jadwal.php         # Jadwal pelatihan
│   ├── kalender.php       # Kalender pelatihan
│   ├── monitoring.php     # Monitoring realisasi
│   ├── kewajiban.php      # Kewajiban pelatihan
│   ├── realisasi.php      # Rencana vs Realisasi
│   ├── laporan.php        # Laporan & export
│   ├── sync_gsheet.php    # Sinkronisasi Google Sheets
│   ├── import_gsheet.php  # Import data manual
│   └── settings_sync.php  # Pengaturan auto-sync
│
├── api/                    # API endpoints
│   ├── auto_sync.php      # Auto-sync endpoint
│   ├── export.php         # Export Excel
│   ├── template.php       # Download template CSV
│   └── test_gsheet_connection.php  # Test koneksi GSheet
│
├── database/               # File database
│   ├── database.sql       # Struktur database
│   ├── update_database.sql # Update script
│   └── run_update_database.php # Jalankan update
│
├── docs/                   # Dokumentasi
│   ├── CHECKLIST_HALAMAN.md
│   ├── PANDUAN_AUTO_SYNC.md
│   ├── PANDUAN_SYNC_GSHEET.md
│   └── README_SYNC.md
│
├── reference/              # File referensi
│   └── *.xlsx             # File Excel referensi
│
└── index.php              # Dashboard utama
```

## 🚀 Cara Instalasi

### 1. Setup Database
```bash
# Import database
mysql -u root -p < database/database.sql

# Atau jalankan via browser
http://localhost:3000/database/run_update_database.php
```

### 2. Konfigurasi Database
Edit file `config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'monitor_pelatihan_pegawai');
```

### 3. Akses Aplikasi
```
http://localhost:3000/
```

## ✨ Fitur Utama

### 📊 Dashboard
- Statistik pegawai, pelatihan, jadwal, monitoring
- Menu navigasi cepat
- Status jadwal pelatihan
- Monitoring terbaru

### 👥 Manajemen Pegawai
- CRUD data pegawai
- Detail riwayat pelatihan per pegawai
- Kewajiban pelatihan

### 📚 Database Pelatihan
- Master data pelatihan
- Kategori & lingkup pelatihan
- Jadwal pelatihan

### 📋 Monitoring
- Input realisasi pelatihan
- Upload sertifikat
- Tracking JP (Jam Pelajaran)

### 🔄 Sinkronisasi Google Sheets
- Auto-sync dari Google Form
- Interval sync yang bisa diatur
- Deteksi duplikat otomatis
- Preview data sebelum sync

### 📈 Laporan
- Export ke Excel
- Filter berdasarkan tahun, pegawai, kategori
- Rekap pelatihan

## 🔗 Integrasi Google Form

### Google Form
https://forms.gle/gtAyX37spwN6FqdJ7

### Spreadsheet
https://docs.google.com/spreadsheets/d/1KT8DWSKWpJxJY4elwNwtBPV39_cXbpD5bWfwD_fbwPk/edit

### Struktur Kolom Form
1. Timestamp
2. Nama Pegawai
3. Pelatihan yang sudah diikuti
4. Tanggal Pelatihan
5. Keterangan
6. Upload Sertifikat

## 🛠️ Teknologi

- **Backend**: PHP Native
- **Database**: MySQL
- **Frontend**: Tailwind CSS
- **Font**: Inter (Google Fonts)

## 📝 Dokumentasi Lengkap

Lihat folder `docs/` untuk dokumentasi lengkap:
- `PANDUAN_AUTO_SYNC.md` - Panduan fitur auto-sync
- `PANDUAN_SYNC_GSHEET.md` - Panduan sinkronisasi
- `CHECKLIST_HALAMAN.md` - Daftar semua halaman
- `README_SYNC.md` - Dokumentasi teknis sync

## 📞 Support

Jika ada masalah atau pertanyaan, hubungi administrator sistem.

---

**Version**: 1.0.0  
**Last Update**: Januari 2025
