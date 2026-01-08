<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="template_import_pelatihan.csv"');

$output = fopen('php://output', 'w');

// Header
fputcsv($output, ['Nama', 'NIP', 'Jabatan', 'Kategori', 'Nama Pelatihan', 'Tahun', 'Realisasi', 'No Sertifikat']);

// Sample data
fputcsv($output, ['Anna Tresia Siahaan, S.Gz', '199505022025062007', 'Pengawas Farmasi dan Makanan Ahli Pertama', 'Mutlak', 'Diklat Manajemen', '2025', '15/01/2025', 'SERT-001']);
fputcsv($output, ['Budi Santoso', '199001012020011001', 'Pranata Komputer Ahli Pertama', 'Perlu', 'Manajemen Layanan TI', '2025', '', '']);
fputcsv($output, ['Citra Dewi', '199203152021012002', 'Penata Laporan Keuangan', 'Penting', 'Pengelolaan Data', '2025', '20/02/2025', 'SERT-002']);

fclose($output);
?>
