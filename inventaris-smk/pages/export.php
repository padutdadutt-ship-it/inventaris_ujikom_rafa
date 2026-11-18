<?php
// Menyiapkan data laporan untuk diunduh sebagai CSV atau Excel.
require __DIR__ . '/../app/init.php';
Auth::guard(['administrator']);

$reportType = $_GET['jenis'] ?? 'peminjaman';
$startDate = $_GET['dari'] ?? date('Y-m-01');
$endDate = $_GET['sampai'] ?? date('Y-m-d');
$exportFormat = ($_GET['format'] ?? 'csv') === 'xls' ? 'xls' : 'csv';

if (!strtotime($startDate) || !strtotime($endDate) || $startDate > $endDate) {
    exit('Rentang tanggal tidak valid.');
}

// Siapkan judul kolom dan data sesuai jenis laporan yang dipilih.
if ($reportType === 'template_siswa') {
    $headers = ['nis', 'nama', 'kelas'];
    $rows = [['1234567890', 'Contoh Nama Siswa', 'X PPLG 1']];
    $fileName = 'template_import_siswa';
    $exportFormat = 'csv';
} elseif ($reportType === 'template') {
    $headers = ['kode_inventaris', 'nama', 'kode_jenis', 'kode_ruang', 'kondisi', 'jumlah', 'tanggal_register', 'keterangan'];
    $rows = [['ELK-100', 'Contoh Proyektor', 'ELK', 'GDG', 'Baik', 3, date('Y-m-d'), 'Contoh baris']];
    $fileName = 'template_import_inventaris';
    $exportFormat = 'csv';
} elseif ($reportType === 'inventaris') {
    $headers = ['kode_inventaris', 'nama', 'kode_jenis', 'kode_ruang', 'kondisi', 'jumlah', 'tanggal_register', 'keterangan'];
    $inventoryRows = Report::inventaris($startDate, $endDate);
    $rows = array_map(
        fn($row) => [
            $row['kode_inventaris'],
            $row['nama'],
            $row['kode_jenis'],
            $row['kode_ruang'],
            $row['kondisi'],
            $row['jumlah'],
            $row['tanggal_register'],
            $row['keterangan'],
        ],
        $inventoryRows
    );
    $fileName = "inventaris_{$startDate}_{$endDate}";
} else {
    $headers = ['ID', 'Tanggal Pinjam', 'Peminjam', 'NIP', 'Jabatan', 'Barang', 'Total Unit', 'Batas Kembali', 'Tanggal Kembali', 'Status'];
    $loanRows = Report::peminjaman($startDate, $endDate);
    $rows = array_map(
        fn($row) => [
            $row['id_peminjaman'],
            $row['tanggal_pinjam'],
            $row['nama_pegawai'],
            $row['nip'],
            $row['jabatan'],
            $row['barang'],
            $row['total_unit'],
            $row['batas_kembali'],
            $row['tanggal_kembali'],
            $row['status_peminjaman'],
        ],
        $loanRows
    );
    $fileName = "peminjaman_{$startDate}_{$endDate}";
}

// Kirim hasil dalam format Excel.
if ($exportFormat === 'xls') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$fileName.xls\"");
    echo "\xEF\xBB\xBF<table border=\"1\"><tr>";

    foreach ($headers as $header) {
        echo '<th>' . e($header) . '</th>';
    }

    echo '</tr>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($row as $value) {
            echo "<td style=\"mso-number-format:'\\@'\">" . e($value) . '</td>';
        }

        echo '</tr>';
    }

    echo '</table>';
    exit;
}

// Kirim hasil dalam format CSV UTF-8.
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$fileName.csv\"");
$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, $headers, ',', '"', '');

foreach ($rows as $row) {
    fputcsv($output, $row, ',', '"', '');
}

fclose($output);
