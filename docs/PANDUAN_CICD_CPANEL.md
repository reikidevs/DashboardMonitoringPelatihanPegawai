# Panduan CI/CD Deploy ke cPanel

## Langkah-langkah Setup

### 1. Buat FTP Account di cPanel

1. Login ke cPanel
2. Buka **FTP Accounts**
3. Klik **Add FTP Account**
4. Isi form:
   - **Log In**: nama username (misal: `deploy`)
   - **Domain**: pilih domain Anda
   - **Password**: buat password yang kuat
   - **Directory**: folder tujuan deploy (misal: `public_html/monitoring-pelatihan`)
5. Klik **Create FTP Account**
6. Catat informasi FTP:
   - Server: biasanya `ftp.namadomain.com` atau IP server
   - Username: `deploy@namadomain.com`
   - Password: password yang dibuat

### 2. Setup GitHub Secrets

1. Buka repository GitHub Anda
2. Pergi ke **Settings** → **Secrets and variables** → **Actions**
3. Klik **New repository secret** dan tambahkan:

| Secret Name | Nilai | Contoh |
|-------------|-------|--------|
| `FTP_SERVER` | Alamat FTP server | `ftp.namadomain.com` |
| `FTP_USERNAME` | Username FTP lengkap | `deploy@namadomain.com` |
| `FTP_PASSWORD` | Password FTP | `password123` |
| `FTP_SERVER_DIR` | Folder tujuan di server | `/public_html/monitoring/` |

> **Penting**: `FTP_SERVER_DIR` harus diakhiri dengan `/`

### 3. Setup File .env di Server

Karena file `.env` tidak di-deploy (untuk keamanan), Anda perlu membuat file ini manual di server:

1. Login ke cPanel → **File Manager**
2. Buka folder aplikasi
3. Buat file `.env` dengan isi sesuai `.env.example`
4. Sesuaikan nilai dengan konfigurasi server production

### 4. Test Deployment

1. Push perubahan ke branch `main` atau `master`
2. Buka tab **Actions** di GitHub repository
3. Lihat progress deployment
4. Jika sukses, cek website Anda

## Troubleshooting

### Error: Connection refused
- Pastikan FTP server address benar
- Cek apakah port 21 tidak diblokir firewall

### Error: Login incorrect
- Pastikan username FTP lengkap (termasuk @domain)
- Cek password sudah benar

### Error: Permission denied
- Pastikan FTP user punya akses ke folder tujuan
- Cek permission folder di cPanel (755 untuk folder, 644 untuk file)

### Files tidak terupdate
- Cek `FTP_SERVER_DIR` sudah benar
- Pastikan path diakhiri dengan `/`

## Manual Trigger Deployment

Jika ingin deploy tanpa push:
1. Buka tab **Actions** di GitHub
2. Pilih workflow **Deploy to cPanel**
3. Klik **Run workflow**
4. Pilih branch dan klik **Run workflow**

## File yang Tidak Di-deploy

File berikut dikecualikan dari deployment:
- `.git*` - file Git
- `.env` dan `.env.example` - konfigurasi environment
- `reference/` - file referensi
- `docs/` - dokumentasi
- `*.md` - file markdown
- `wasmer.toml` - konfigurasi wasmer
