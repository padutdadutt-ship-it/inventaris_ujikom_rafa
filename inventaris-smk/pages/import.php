<?php
// Memproses file CSV untuk data inventaris atau siswa.
require __DIR__ . '/../app/init.php';
$user = Auth::guard(['administrator']);

// Validasi file, lalu jalankan proses import sesuai jenis datanya.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    try {
        $importType = post('jenis', 'inventaris');
        if (!in_array($importType, ['inventaris', 'siswa'], true)) {
            throw new RuntimeException('Jenis data yang dipilih tidak dikenal.');
        }

        $uploadedFile = $_FILES['file'] ?? null;
        if (!$uploadedFile || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File gagal diunggah.');
        }

        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        if ($fileExtension !== 'csv') {
            throw new RuntimeException('Gunakan file berformat .csv (dari Excel: Save As → CSV).');
        }

        if ($importType === 'siswa') {
            $result = Report::importSiswa($uploadedFile['tmp_name']);
            $message = "Import siswa selesai: {$result['ok']} ditambahkan, "
                . "{$result['duplikat']} duplikat dilewati, {$result['gagal']} gagal.";
        } else {
            $startDate = post('dari') ?: null;
            $endDate = post('sampai') ?: null;

            if (($startDate xor $endDate) || ($startDate && $startDate > $endDate)) {
                throw new RuntimeException('Isi kedua tanggal dengan benar, atau kosongkan keduanya.');
            }

            $result = Report::importInventaris(
                $uploadedFile['tmp_name'],
                $startDate,
                $endDate,
                $user['id']
            );
            $message = "Import inventaris selesai: {$result['ok']} ditambahkan, "
                . "{$result['duplikat']} duplikat dilewati, "
                . "{$result['diluar_rentang']} di luar rentang, {$result['gagal']} gagal.";
        }

        if ($result['pesan']) {
            $message .= ' ' . implode(' ', array_slice($result['pesan'], 0, 3));
        }

        flash($result['gagal'] ? 'warning' : 'success', $message);
    } catch (Throwable $e) {
        flash('danger', errmsg($e));
    }
}

// Kembali ke halaman laporan setelah proses selesai.
redirect('pages/laporan.php?jenis=inventaris&dari=2000-01-01&sampai=' . date('Y-m-d'));
