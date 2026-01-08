# Sistem Monitoring Pelatihan Pegawai - Sinkronisasi Google Sheets

## Fitur Sinkronisasi Otomatis

Sistem ini sudah terintegrasi dengan Google Form dan Google Sheets untuk memudahkan input data pelatihan pegawai.

### Link Penting

- **Google Form**: https://forms.gle/gtAyX37spwN6FqdJ7
- **Spreadsheet**: https://docs.google.com/spreadsheets/d/1KT8DWSKWpJxJY4elwNwtBPV39_cXbpD5bWfwD_fbwPk/edit

### Cara Kerja

1. **Pegawai mengisi Google Form** dengan data pelatihan yang sudah diikuti
2. **Data otomatis masuk ke Google Sheets** (Form Responses 1)
3. **Admin melakukan sync** melalui halaman `sync_gsheet.php`
4. **Data otomatis masuk ke database** aplikasi monitoring

### Struktur Data Google Form

| Kolom | Keterangan | Contoh |
|-------|------------|--------|
| Timestamp | Otomatis dari Google Form | 08/01/2025 14:30:00 |
| Nama Pegawai | Nama lengkap pegawai | Anna Tresia Siahaan, S.Gz |
| Pelatihan yang sudah diikuti | Nama pelatihan | Diklat Manajemen |
| Tanggal Pelatihan | Tanggal pelaksanaan | 15/01/2025 |
| Keterangan | No sertifikat atau catatan | SERT-2025-001 |
| Upload Sertifikat | Link file sertifikat (opsional) | https://drive.google.com/... |

### Cara Menggunakan

#### 1. Setup Awal (Sudah Dilakukan ✓)

- Spreadsheet sudah di-publish ke web
- Spreadsheet ID sudah dikonfigurasi: `1KT8DWSKWpJxJY4elwNwtBPV39_cXbpD5bWfwD_fbwPk`
- Sheet name: `Form Responses 1`

#### 2. Sinkronisasi Data

1. Buka aplikasi monitoring pelatihan
2. Klik menu **Import Data** atau **Sync Google Sheets**
3. Klik tombol **Sinkronkan Sekarang**
4. Sistem akan:
   - Mengambil data dari Google Sheets
   - Menambahkan pegawai baru jika belum ada
   - Menambahkan pelatihan baru jika belum ada
   - Memasukkan data monitoring pelatihan
   - Skip data yang sudah pernah di-sync (tidak duplikat)

#### 3. Preview Data (Opsional)

Sebelum melakukan sync, Anda bisa preview data terlebih dahulu:
1. Klik tombol **Preview Data**
2. Sistem akan menampilkan 10 baris pertama dari spreadsheet
3. Pastikan struktur kolom sudah sesuai

### Keunggulan Sistem Sync

✅ **Tidak Ada Duplikat**: Data yang sudah di-sync tidak akan duplikat
✅ **Auto-Create**: Pegawai dan pelatihan baru otomatis ditambahkan
✅ **Aman**: Menggunakan hash unik untuk deteksi duplikat
✅ **Mudah**: Tidak perlu download CSV manual
✅ **Real-time**: Data bisa di-sync kapan saja

### Alur Data

```
Pegawai → Google Form → Google Sheets → Sync → Database → Aplikasi
```

### Mapping Database

- **Nama Pegawai** → `pegawai.nama`
- **Pelatihan yang sudah diikuti** → `pelatihan.nama`
- **Tanggal Pelatihan** → `monitoring_pelatihan.pelaksanaan`
- **Keterangan + Upload Sertifikat** → `monitoring_pelatihan.no_sertifikat`
- **Tahun** → Otomatis dari tanggal pelatihan → `monitoring_pelatihan.tahun`

### Deteksi Duplikat

Sistem menggunakan `sync_hash` yang dibuat dari kombinasi:
- Timestamp
- Nama Pegawai
- Nama Pelatihan

Jika hash sudah ada di database, data akan di-skip.

### Troubleshooting

#### Error: "Gagal mengambil data dari Google Sheets"

**Penyebab:**
- Spreadsheet belum di-publish
- Koneksi internet bermasalah
- Spreadsheet ID salah

**Solusi:**
1. Pastikan spreadsheet sudah di-publish (File → Share → Publish to web)
2. Cek koneksi internet
3. Verifikasi Spreadsheet ID di halaman settings

#### Data tidak muncul setelah sync

**Penyebab:**
- Data sudah pernah di-sync sebelumnya
- Kolom wajib kosong (Nama Pegawai atau Pelatihan)

**Solusi:**
1. Cek di halaman Monitoring apakah data sudah ada
2. Pastikan kolom "Nama Pegawai" dan "Pelatihan yang sudah diikuti" tidak kosong
3. Lihat pesan hasil sync (berapa data baru, berapa yang di-skip)

#### Format tanggal tidak sesuai

**Solusi:**
Sistem mendukung berbagai format tanggal:
- `dd/mm/yyyy` (contoh: 15/01/2025)
- `mm/dd/yyyy` (contoh: 01/15/2025)
- `yyyy-mm-dd` (contoh: 2025-01-15)

### Tips Penggunaan

1. **Sync Berkala**: Lakukan sync setiap minggu atau setelah ada banyak data baru
2. **Validasi Data**: Pastikan data di Google Form sudah benar sebelum di-sync
3. **Backup**: Lakukan backup database sebelum sync data dalam jumlah besar
4. **Monitor**: Perhatikan pesan hasil sync untuk memastikan semua data berhasil

### File Terkait

- `sync_gsheet.php` - Halaman sinkronisasi
- `import_gsheet.php` - Halaman import dengan link ke sync
- `config.php` - Konfigurasi database
- `database.sql` - Struktur database (termasuk tabel `settings` dan `sync_hash`)

### Kontak Support

Jika ada masalah atau pertanyaan, hubungi administrator sistem.

---

**Status**: ✅ Siap Digunakan
**Last Update**: 08 Januari 2025
