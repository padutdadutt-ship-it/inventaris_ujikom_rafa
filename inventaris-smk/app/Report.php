<?php
// Menyediakan data dashboard, laporan, dan proses import inventaris.
class Report
{
    const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    // Hitung angka ringkasan untuk dashboard.
    public static function stats(): array
    {
        $stats = Database::row("SELECT
            (SELECT COALESCE(SUM(jumlah),0) FROM inventaris) AS unit_total,
            (SELECT COUNT(*) FROM inventaris) AS jenis_barang,
            (SELECT COALESCE(SUM(jumlah),0) FROM inventaris WHERE kondisi='Baik') AS unit_baik,
            (SELECT COALESCE(SUM(d.jumlah),0) FROM detail_pinjam d JOIN peminjaman p ON p.id_peminjaman=d.id_peminjaman WHERE p.status_peminjaman='Dipinjam') AS unit_dipinjam,
            (SELECT COUNT(*) FROM peminjaman WHERE status_peminjaman='Dipinjam') AS trx_aktif,
            (SELECT COUNT(*) FROM peminjaman WHERE status_peminjaman='Dipinjam' AND batas_kembali < CURDATE()) AS terlambat,
            (SELECT COUNT(*) FROM pegawai) AS pegawai,
            (SELECT COUNT(*) FROM pegawai WHERE aktif=1) AS pegawai_aktif");
        $stats['persen_tersedia'] = $stats['unit_total'] > 0
            ? (int)round(($stats['unit_total'] - $stats['unit_dipinjam']) / $stats['unit_total'] * 100)
            : 0;

        return $stats;
    }

    /** Jumlah transaksi peminjaman 6 bulan terakhir: [label => jumlah]. */
    public static function perBulan(): array
    {
        $monthlyCounts = array_column(
            Database::all(
                "SELECT DATE_FORMAT(tanggal_pinjam,'%Y-%m') ym, COUNT(*) c
                   FROM peminjaman
                  WHERE tanggal_pinjam >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH,'%Y-%m-01')
                  GROUP BY ym"
            ),
            'c',
            'ym'
        );
        $monthlyLoans = [];

        for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
            $monthDate = strtotime("first day of -$monthOffset month");
            $monthLabel = self::BULAN[(int)date('n', $monthDate) - 1];
            $monthKey = date('Y-m', $monthDate);
            $monthlyLoans[$monthLabel] = (int)($monthlyCounts[$monthKey] ?? 0);
        }

        return $monthlyLoans;
    }

    /** Jumlah transaksi per hari selama 30 hari terakhir. */
    public static function perHari(): array
    {
        $dailyCounts = array_column(
            Database::all(
                'SELECT tanggal_pinjam d, COUNT(*) c
                   FROM peminjaman
                  WHERE tanggal_pinjam >= CURDATE() - INTERVAL 29 DAY
                  GROUP BY d'
            ),
            'c',
            'd'
        );
        $dailyLoans = [];

        for ($dayOffset = 29; $dayOffset >= 0; $dayOffset--) {
            $date = date('Y-m-d', strtotime("-$dayOffset day"));
            $dailyLoans[] = (int)($dailyCounts[$date] ?? 0);
        }

        return $dailyLoans;
    }

    // Ambil aktivitas peminjaman dan pengembalian terbaru.
    public static function aktivitas(int $limit = 7): array
    {
        return Database::all("(SELECT 'pinjam' AS tipe, g.nama_pegawai, p.dicatat_pada AS waktu, p.id_peminjaman FROM peminjaman p JOIN pegawai g ON g.id_pegawai=p.id_pegawai)
                              UNION ALL
                              (SELECT 'kembali', g.nama_pegawai, p.dikembalikan_pada, p.id_peminjaman FROM peminjaman p JOIN pegawai g ON g.id_pegawai=p.id_pegawai WHERE p.dikembalikan_pada IS NOT NULL)
                              ORDER BY waktu DESC LIMIT " . (int)$limit);
    }

    // ------------------------------ Data untuk laporan
    public static function peminjaman(string $dari, string $sampai): array
    {
        $statement = Database::q('CALL sp_laporan_peminjaman(?,?)', [$dari, $sampai]);
        $rows = $statement->fetchAll();
        $statement->closeCursor();

        return $rows;
    }

    public static function inventaris(string $dari, string $sampai): array
    {
        return Database::all(
            'SELECT i.kode_inventaris, i.nama, j.kode_jenis, j.nama_jenis,
                    r.kode_ruang, r.nama_ruang, i.kondisi, i.jumlah,
                    fn_stok_tersedia(i.id_inventaris) AS tersedia,
                    i.tanggal_register, i.keterangan
               FROM inventaris i
               JOIN jenis j ON j.id_jenis=i.id_jenis
               JOIN ruang r ON r.id_ruang=i.id_ruang
              WHERE i.tanggal_register BETWEEN ? AND ?
              ORDER BY i.tanggal_register, i.kode_inventaris',
            [$dari, $sampai]
        );
    }

    // ------------------------------ Import inventaris
    /** Import CSV inventaris. Kolom: kode_inventaris,nama,kode_jenis,kode_ruang,kondisi,jumlah,tanggal_register,keterangan
     *  Anti duplikat: kode_inventaris yang sudah ada (di DB / di file) dilewati. Rentang tanggal opsional memfilter baris. */
    public static function importInventaris(
        string $csvFile,
        ?string $startDate,
        ?string $endDate,
        int $staffId
    ): array
    {
        // Deteksi pemisah CSV, baik koma maupun titik koma.
        $fileHandle = fopen($csvFile, 'r');
        if ($fileHandle === false) {
            throw new RuntimeException('File CSV tidak dapat dibaca.');
        }

        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', (string)fgets($fileHandle));
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $headers = array_map(
            static fn($header) => strtolower(trim((string)$header)),
            str_getcsv(rtrim($firstLine, "\r\n"), $delimiter, '"', '')
        );

        // Siapkan data referensi dan daftar kode barang yang sudah ada.
        $categoryIds = array_change_key_case(
            array_column(Database::all('SELECT id_jenis, kode_jenis FROM jenis'), 'id_jenis', 'kode_jenis'),
            CASE_UPPER
        );
        $roomIds = array_change_key_case(
            array_column(Database::all('SELECT id_ruang, kode_ruang FROM ruang'), 'id_ruang', 'kode_ruang'),
            CASE_UPPER
        );
        $existingCodes = array_flip(array_map(
            'strtoupper',
            array_column(Database::all('SELECT kode_inventaris FROM inventaris'), 'kode_inventaris')
        ));
        $conditionNames = [
            'baik' => 'Baik',
            'rusak ringan' => 'Rusak Ringan',
            'rusak berat' => 'Rusak Berat',
        ];

        $result = ['ok' => 0, 'duplikat' => 0, 'diluar_rentang' => 0, 'gagal' => 0, 'pesan' => []];
        $lineNumber = 1;
        $database = Database::conn();
        $database->beginTransaction();

        // Baca file per baris, lalu simpan data yang lolos validasi.
        while (($row = fgetcsv($fileHandle, 0, $delimiter, '"', '')) !== false) {
            $lineNumber++;

            if (!array_filter($row, fn($value) => trim((string)$value) !== '')) {
                continue;
            }

            $rowData = array_combine(
                $headers,
                array_pad(array_slice($row, 0, count($headers)), count($headers), '')
            );
            $rowData = array_map('trim', $rowData);

            $inventoryCode = strtoupper($rowData['kode_inventaris'] ?? '');
            $registrationDate = self::tanggal($rowData['tanggal_register'] ?? '') ?? date('Y-m-d');
            $quantity = (int)($rowData['jumlah'] ?? 0);
            $categoryId = $categoryIds[strtoupper($rowData['kode_jenis'] ?? '')] ?? null;
            $roomId = $roomIds[strtoupper($rowData['kode_ruang'] ?? '')] ?? null;

            if ($inventoryCode === '' || ($rowData['nama'] ?? '') === '' || $quantity < 1 || !$categoryId || !$roomId) {
                $result['gagal']++;
                $result['pesan'][] = "Baris $lineNumber: data tidak lengkap / kode jenis atau ruang tidak dikenal.";
                continue;
            }

            if ($startDate && $endDate && ($registrationDate < $startDate || $registrationDate > $endDate)) {
                $result['diluar_rentang']++;
                continue;
            }

            if (isset($existingCodes[$inventoryCode])) {
                $result['duplikat']++;
                continue;
            }

            Database::q(
                'INSERT INTO inventaris (kode_inventaris,nama,kondisi,keterangan,jumlah,id_jenis,tanggal_register,id_ruang,id_petugas) VALUES (?,?,?,?,?,?,?,?,?)',
                [
                    $inventoryCode,
                    $rowData['nama'],
                    $conditionNames[strtolower($rowData['kondisi'] ?? '')] ?? 'Baik',
                    $rowData['keterangan'] ?? null,
                    $quantity,
                    $categoryId,
                    $registrationDate,
                    $roomId,
                    $staffId,
                ]
            );
            $existingCodes[$inventoryCode] = 1;
            $result['ok']++;
        }

        $database->commit();
        fclose($fileHandle);

        return $result;
    }

    /**
     * Import siswa dari CSV dengan kolom NIS, Nama Lengkap, dan Kelas.
     * Siswa disimpan pada tabel pegawai agar tetap memakai alur peminjaman yang sama.
     */
    public static function importSiswa(string $csvFile): array
    {
        $fileHandle = fopen($csvFile, 'r');
        if ($fileHandle === false) {
            throw new RuntimeException('File CSV tidak dapat dibaca.');
        }

        try {
            // Kenali pemisah CSV dan hapus tanda BOM dari file Excel.
            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', (string)fgets($fileHandle));
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

            if ($firstLine === '') {
                throw new RuntimeException('File CSV kosong.');
            }

            $headers = array_map(
                static fn($header) => strtolower(trim((string)$header)),
                str_getcsv(rtrim($firstLine, "\r\n"), $delimiter, '"', '')
            );
            $nisColumn = self::findCsvColumn($headers, ['nis', 'nomor induk siswa']);
            $nameColumn = self::findCsvColumn($headers, ['nama', 'nama lengkap']);
            $classColumn = self::findCsvColumn($headers, ['kelas', 'class']);

            if ($nisColumn === null || $nameColumn === null || $classColumn === null) {
                throw new RuntimeException('Kolom CSV harus berisi NIS, Nama Lengkap, dan Kelas.');
            }

            // Gunakan daftar NIS yang sudah ada untuk menghindari data ganda.
            $existingNis = array_fill_keys(
                array_map('strtoupper', array_column(Database::all('SELECT nip FROM pegawai'), 'nip')),
                true
            );
            $result = ['ok' => 0, 'duplikat' => 0, 'gagal' => 0, 'pesan' => []];
            $lineNumber = 1;
            $database = Database::conn();
            $database->beginTransaction();

            try {
                while (($row = fgetcsv($fileHandle, 0, $delimiter, '"', '')) !== false) {
                    $lineNumber++;

                    if (!array_filter($row, static fn($value) => trim((string)$value) !== '')) {
                        continue;
                    }

                    $nis = trim((string)($row[$nisColumn] ?? ''));
                    $name = trim((string)($row[$nameColumn] ?? ''));
                    $class = trim((string)($row[$classColumn] ?? ''));

                    if ($nis === '' || !preg_match('/^\d+$/', $nis) || $name === '' || $class === '') {
                        $result['gagal']++;
                        $result['pesan'][] = "Baris $lineNumber: NIS harus berupa angka dan nama serta kelas harus diisi.";
                        continue;
                    }

                    if (isset($existingNis[strtoupper($nis)])) {
                        $result['duplikat']++;
                        continue;
                    }

                    Database::q(
                        'INSERT INTO pegawai (nama_pegawai, nip, jabatan, aktif) VALUES (?, ?, ?, 1)',
                        [$name, $nis, 'Siswa - ' . $class]
                    );

                    $existingNis[strtoupper($nis)] = true;
                    $result['ok']++;
                }

                $database->commit();
            } catch (Throwable $error) {
                $database->rollBack();
                throw $error;
            }

            return $result;
        } finally {
            fclose($fileHandle);
        }
    }

    // Cari nama kolom CSV dari beberapa kemungkinan judul.
    private static function findCsvColumn(array $headers, array $names): ?int
    {
        foreach ($names as $name) {
            $column = array_search($name, $headers, true);

            if ($column !== false) {
                return $column;
            }
        }

        return null;
    }

    // Ubah tanggal CSV menjadi format database YYYY-MM-DD.
    private static function tanggal(string $date): ?string
    {
        if ($date === '') {
            return null;
        }

        if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $date, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
        }

        $timestamp = strtotime($date);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}
