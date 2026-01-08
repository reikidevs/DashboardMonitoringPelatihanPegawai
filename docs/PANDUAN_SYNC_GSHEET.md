# Panduan Sinkronisasi Google Sheets

## Google Form & Spreadsheet
- **Google Form**: https://forms.gle/gtAyX37spwN6FqdJ7
- **Spreadsheet**: https://docs.google.com/spreadsheets/d/1KT8DWSKWpJxJY4elwNwtBPV39_cXbpD5bWfwD_fbwPk/edit

## Struktur Kolom Google Form
1. **Timestamp** - Otomatis dari Google Form
2. **Nama Pegawai** - Nama lengkap pegawai
3. **Pelatihan yang sudah diikuti** - Nama pelatihan yang telah diikuti
4. **Tanggal Pelatihan** - Tanggal pelaksanaan pelatihan (format: dd/mm/yyyy)
5. **Keterangan** - Nomor sertifikat atau catatan tambahan
6. **Upload Sertifikat** - Link file sertifikat (opsional)

## Cara Menggunakan

### 1. Publish Spreadsheet (Sudah Dilakukan ✓)
- Buka spreadsheet di Google Sheets
- Klik **File → Share → Publish to web**
- Pilih sheet "Form Responses 1"
- Klik **Publish**

### 2. Akses Halaman Sync
- Buka aplikasi monitoring pelatihan
- Klik menu **Import Data** atau langsung ke `sync_gsheet.php`
- Klik tombol **Setup Sync →**

### 3. Sinkronisasi Data
- Spreadsheet ID sudah otomatis terisi: `1KT8DWSKWpJxJY4elwNwtBPV39_cXbpD5bWfwD_fbwPk`
- Nama sheet: `Form Responses 1`
- Klik **Sinkronkan Sekarang**

### 4. Preview Data (Opsional)
- Klik tombol **Preview Data** untuk melihat data dari spreadsheet sebelum di-sync
- Pastikan struktur kolom sudah sesuai

## Fitur Sinkronisasi

### Otomatis Menambahkan Data Baru
- **Pegawai baru**: Jika nama pegawai belum ada di database, akan otomatis ditambahkan
- **Pelatihan baru**: Jika nama pelatihan belum ada, akan otomatis ditambahkan
- **Tidak ada duplikat**: Data yang sudah pernah di-sync tidak akan duplikat

### Mapping Data
- **Nama Pegawai** → Tabel `pegawai`
- **Pelatihan yang sudah diikuti** → Tabel `pelatihan`
- **Tanggal Pelatihan** → Field `pelaksanaan` di tabel `monitoring_pelatihan`
- **Keterangan + Upload Sertifikat** → Field `no_sertifikat` (digabung jika ada link sertifikat)

### Deteksi Duplikat
Sistem menggunakan hash unik berdasarkan:
- Timestamp
- Nama Pegawai
- Nama Pelatihan

Jika kombinasi ini sudah ada, data akan di-skip (tidak duplikat).

## Troubleshooting

### Error: "Gagal mengambil data dari Google Sheets"
**Solusi:**
1. Pastikan spreadsheet sudah di-publish ke web
2. Cek koneksi internet
3. Pastikan Spreadsheet ID benar

### Data tidak muncul setelah sync
**Solusi:**
1. Klik **Preview Data** untuk memastikan data bisa diambil
2. Cek apakah data sudah pernah di-sync sebelumnya (akan di-skip)
3. Pastikan kolom "Nama Pegawai" dan "Pelatihan yang sudah diikuti" tidak kosong

### Format tanggal tidak sesuai
**Solusi:**
- Gunakan format: dd/mm/yyyy (contoh: 15/01/2025)
- Atau format: yyyy-mm-dd (contoh: 2025-01-15)
- Sistem akan otomatis mendeteksi format

## Tips Penggunaan

1. **Sync Berkala**: Lakukan sync secara berkala (misalnya setiap minggu) untuk update data terbaru
2. **Cek Preview**: Selalu cek preview data sebelum sync pertama kali
3. **Backup Database**: Lakukan backup database sebelum sync data dalam jumlah besar
4. **Validasi Data**: Pastikan data di Google Form sudah benar sebelum di-sync

## Kontak
Jika ada masalah atau pertanyaan, hubungi administrator sistem.
