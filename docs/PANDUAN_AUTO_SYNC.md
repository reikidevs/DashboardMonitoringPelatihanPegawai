# 🔄 Panduan Auto-Sync Google Sheets

## Fitur Auto-Sync

Sistem sekarang sudah dilengkapi dengan **Auto-Sync** yang akan otomatis mengambil data baru dari Google Sheets tanpa perlu klik tombol sync manual!

## ✨ Cara Kerja

1. **Saat Buka Dashboard**: Sistem otomatis mengecek apakah ada data baru
2. **Interval Check**: Jika sudah lewat dari interval yang ditentukan (default 5 menit), sistem akan sync otomatis
3. **Notifikasi**: Jika ada data baru, akan muncul notifikasi hijau
4. **Auto Reload**: Halaman akan otomatis refresh untuk menampilkan data terbaru
5. **Background Sync**: Sistem juga akan sync otomatis setiap 5 menit di background (selama halaman dashboard terbuka)

## 🎯 Keunggulan Auto-Sync

✅ **Tidak Perlu Klik Tombol** - Data otomatis masuk
✅ **Real-time** - Data selalu update
✅ **Tidak Ada Duplikat** - Sistem menggunakan hash unik
✅ **Hemat Waktu** - Tidak perlu manual sync
✅ **Notifikasi Jelas** - Tahu kapan ada data baru
✅ **Bisa Diatur** - Interval sync bisa disesuaikan

## ⚙️ Pengaturan Auto-Sync

### Cara Mengatur:

1. Buka **Dashboard** (`index.php`)
2. Klik menu **"Pengaturan Auto-Sync"** di bagian "Menu Lainnya"
3. Atau langsung buka: `settings_sync.php`

### Opsi Pengaturan:

#### 1. Aktifkan/Nonaktifkan Auto-Sync
- Centang checkbox untuk mengaktifkan
- Hilangkan centang untuk menonaktifkan

#### 2. Atur Interval Sync (1-60 menit)
- Geser slider untuk mengatur interval
- **Rekomendasi**: 5-10 menit untuk penggunaan optimal
- Interval terlalu pendek (< 3 menit) bisa membebani server
- Interval terlalu panjang (> 30 menit) data kurang real-time

### Default Settings:
- **Auto-Sync**: Aktif
- **Interval**: 5 menit

## 📊 Cara Menggunakan

### Skenario 1: Pegawai Isi Google Form
1. Pegawai mengisi Google Form: https://forms.gle/gtAyX37spwN6FqdJ7
2. Data masuk ke Google Sheets
3. Admin buka Dashboard
4. Sistem otomatis sync (jika sudah lewat 5 menit dari sync terakhir)
5. Notifikasi muncul: "✓ Auto-sync selesai! X data baru"
6. Halaman auto-reload
7. Data baru sudah muncul di halaman Monitoring

### Skenario 2: Dashboard Tetap Terbuka
1. Admin buka Dashboard dan biarkan terbuka
2. Setiap 5 menit, sistem otomatis cek data baru
3. Jika ada data baru, notifikasi muncul
4. Halaman auto-reload
5. Data terbaru langsung tampil

### Skenario 3: Sync Manual (Jika Perlu)
1. Buka halaman **Sync Google Sheets** (`sync_gsheet.php`)
2. Klik tombol **"Sinkronkan Sekarang"**
3. Data langsung di-sync tanpa tunggu interval

## 🔍 Monitoring Status Sync

### Di Dashboard:
- **Status Sync**: Tampil di pojok kanan atas
- **Notifikasi**: Muncul saat ada data baru
- **Waktu Sync**: Menampilkan waktu sync terakhir

### Di Halaman Pengaturan:
- **Status**: Aktif/Nonaktif
- **Sync Terakhir**: Tanggal dan waktu
- **Berapa Lama**: X menit/jam yang lalu

## 🛠️ File-File Auto-Sync

### 1. `auto_sync.php`
- Script backend untuk melakukan sync
- Dipanggil via AJAX dari dashboard
- Return JSON response

### 2. `settings_sync.php`
- Halaman pengaturan auto-sync
- Atur interval dan enable/disable
- Lihat status sync

### 3. `index.php` (Dashboard)
- JavaScript untuk auto-sync
- Notifikasi dan status
- Auto-reload saat ada data baru

## 📝 Response Auto-Sync

### Success Response:
```json
{
  "success": true,
  "imported": 5,
  "skipped": 10,
  "message": "Auto-sync selesai! 5 data baru, 10 data sudah ada.",
  "needSync": true,
  "lastSync": "2025-01-08 14:30:00"
}
```

### No New Data:
```json
{
  "success": false,
  "message": "Sync tidak diperlukan. Terakhir sync: 08/01/2025 14:25",
  "needSync": false,
  "lastSync": "2025-01-08 14:25:00"
}
```

### Error Response:
```json
{
  "success": false,
  "message": "Gagal mengambil data dari Google Sheets",
  "imported": 0,
  "skipped": 0
}
```

## ⚡ Performance Tips

### Untuk Server Kecil:
- Set interval 10-15 menit
- Nonaktifkan auto-sync jika tidak diperlukan
- Gunakan sync manual saat perlu

### Untuk Server Besar:
- Set interval 3-5 menit
- Biarkan auto-sync aktif
- Data selalu real-time

### Untuk Banyak User:
- Set interval 5-10 menit
- Monitor load server
- Adjust interval jika perlu

## 🔒 Keamanan

- ✅ Tidak ada duplikat data (menggunakan sync_hash)
- ✅ Validasi data sebelum insert
- ✅ Auto-generate NIP untuk pegawai baru
- ✅ Timeout 10 detik untuk fetch data
- ✅ Error handling yang baik

## 🐛 Troubleshooting

### Auto-Sync Tidak Jalan
**Solusi:**
1. Cek pengaturan: Pastikan auto-sync aktif
2. Cek interval: Pastikan sudah lewat dari interval yang ditentukan
3. Cek koneksi: Test koneksi ke Google Sheets
4. Cek console browser: Lihat error di console

### Data Tidak Muncul
**Solusi:**
1. Refresh halaman dashboard
2. Cek halaman Monitoring langsung
3. Cek apakah data sudah ada sebelumnya (duplikat)
4. Lihat response di console browser

### Notifikasi Tidak Muncul
**Solusi:**
1. Cek JavaScript console untuk error
2. Pastikan file `auto_sync.php` bisa diakses
3. Clear cache browser
4. Coba sync manual dulu

### Sync Terlalu Sering
**Solusi:**
1. Buka Pengaturan Auto-Sync
2. Naikkan interval (misal dari 5 ke 10 menit)
3. Atau nonaktifkan auto-sync

## 📞 Support

Jika ada masalah atau pertanyaan:
1. Cek file `CHECKLIST_HALAMAN.md` untuk status semua halaman
2. Cek file `README_SYNC.md` untuk panduan lengkap sync
3. Test koneksi dengan `test_gsheet_connection.php`
4. Hubungi administrator sistem

---

**Status**: ✅ Auto-Sync Aktif dan Berfungsi
**Last Update**: 08 Januari 2025
