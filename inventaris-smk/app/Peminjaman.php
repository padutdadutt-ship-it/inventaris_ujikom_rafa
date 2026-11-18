<?php
// Menangani transaksi peminjaman dan pengembalian barang.
class Peminjaman
{
    // Simpan peminjaman beserta semua barang yang dipinjam.
    public static function buat(
        int $borrowerId,
        string $loanDate,
        string $returnDeadline,
        array $items,
        int $staffId
    ): int
    {
        $database = Database::conn();
        $database->beginTransaction();

        try {
            $loanId = self::simpanDalamTransaksi($borrowerId, $loanDate, $returnDeadline, $items, $staffId);
            $database->commit();
            return $loanId;
        } catch (Throwable $error) {
            $database->rollBack();
            throw $error;
        }
    }

    /** Simpan permintaan publik yang harus ditinjau operator. */
    public static function ajukan(
        string $name,
        string $nis,
        string $class,
        int $catalogItemId,
        int $quantity,
        string $loanDate,
        string $returnDeadline,
        string $purpose
    ): int
    {
        $name = trim($name);
        $nis = trim($nis);
        $class = trim($class);
        $purpose = trim($purpose);

        if ($name === '' || strlen($name) > 100 || $nis === '' || strlen($nis) > 30 || $class === '' || strlen($class) > 60) {
            throw new RuntimeException('Nama, NIS, dan kelas wajib diisi dengan benar.');
        }

        if (strlen($purpose) > 255) {
            throw new RuntimeException('Keperluan maksimal 255 karakter.');
        }

        if ($catalogItemId < 1 || $quantity < 1) {
            throw new RuntimeException('Pilih barang dan jumlah yang valid.');
        }

        self::validasiTanggal($loanDate, $returnDeadline);

        if (!Database::val('SELECT COUNT(*) FROM katalog_barang_pinjam WHERE id_katalog_barang=?', [$catalogItemId])) {
            throw new RuntimeException('Pilihan barang tidak ditemukan.');
        }

        Database::q(
            "INSERT INTO pengajuan_peminjaman
                (nama_peminjam, nis, kelas, id_inventaris, id_katalog_barang, jumlah, tanggal_pinjam, batas_kembali, keperluan)
             VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?)",
            [$name, $nis, $class, $catalogItemId, $quantity, $loanDate, $returnDeadline, $purpose !== '' ? $purpose : null]
        );

        return (int)Database::conn()->lastInsertId();
    }

    /** Simpan permintaan publik untuk menggunakan ruangan sekolah. */
    public static function ajukanRuang(
        string $name,
        string $nis,
        string $class,
        int $roomId,
        string $startAt,
        string $endAt,
        string $purpose
    ): int {
        $name = trim($name);
        $nis = trim($nis);
        $class = trim($class);
        $purpose = trim($purpose);

        if ($name === '' || strlen($name) > 100 || $nis === '' || strlen($nis) > 30 || $class === '' || strlen($class) > 60) {
            throw new RuntimeException('Nama, NIS, dan kelas wajib diisi dengan benar.');
        }
        if ($purpose === '' || strlen($purpose) > 255) {
            throw new RuntimeException('Keperluan peminjaman ruangan wajib diisi (maksimal 255 karakter).');
        }
        if ($roomId < 1 || !Database::val('SELECT COUNT(*) FROM ruang WHERE id_ruang=?', [$roomId])) {
            throw new RuntimeException('Pilih ruangan yang tersedia.');
        }

        $start = self::tanggalWaktuValid($startAt);
        $end = self::tanggalWaktuValid($endAt);
        if (!$start || !$end || $end <= $start || $start <= new DateTimeImmutable()) {
            throw new RuntimeException('Pilih jadwal yang valid dan waktu mulai harus di masa mendatang.');
        }

        Database::q(
            "INSERT INTO pengajuan_peminjaman_ruang
                (nama_peminjam, nis, kelas, id_ruang, waktu_mulai, waktu_selesai, keperluan)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$name, $nis, $class, $roomId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $purpose]
        );

        return (int)Database::conn()->lastInsertId();
    }

    /** Daftar permintaan yang belum ditangani operator. */
    public static function pengajuanMenunggu(): array
    {
        return Database::all(
            "SELECT a.*, COALESCE(k.nama_barang, i.nama) AS nama_barang,
                    COALESCE(k.kategori, 'Barang lama') AS kategori_barang,
                    i.kode_inventaris
               FROM pengajuan_peminjaman a
               LEFT JOIN katalog_barang_pinjam k ON k.id_katalog_barang=a.id_katalog_barang
               LEFT JOIN inventaris i ON i.id_inventaris=a.id_inventaris
              WHERE a.status='Menunggu'
              ORDER BY a.diajukan_pada, a.id_pengajuan"
        );
    }

    /** Permintaan ruangan yang menunggu persetujuan operator. */
    public static function pengajuanRuangMenunggu(): array
    {
        return Database::all(
            "SELECT a.*, r.nama_ruang, r.kode_ruang
               FROM pengajuan_peminjaman_ruang a
               JOIN ruang r ON r.id_ruang=a.id_ruang
              WHERE a.status='Menunggu'
              ORDER BY a.waktu_mulai, a.id_pengajuan_ruang"
        );
    }

    /** Setujui permintaan dan catat transaksi peminjaman secara atomik. */
    public static function setujuiPengajuan(int $requestId, int $staffId, int $inventoryId): int
    {
        $database = Database::conn();
        $database->beginTransaction();

        try {
            $request = Database::row(
                "SELECT * FROM pengajuan_peminjaman WHERE id_pengajuan=? AND status='Menunggu' FOR UPDATE",
                [$requestId]
            );
            if (!$request) {
                throw new RuntimeException('Pengajuan tidak ditemukan atau sudah ditangani.');
            }
            if ($inventoryId < 1) {
                throw new RuntimeException('Pilih barang inventaris yang cocok dan masih tersedia.');
            }

            $matchingCatalogInventory = Database::val(
                "SELECT COUNT(*)
                   FROM katalog_barang_pinjam k
                   JOIN inventaris i ON LOWER(TRIM(i.nama))=LOWER(TRIM(k.nama_barang))
                   JOIN jenis j ON j.id_jenis=i.id_jenis
                  WHERE k.id_katalog_barang=? AND i.id_inventaris=?
                    AND j.nama_jenis=CASE k.kategori
                      WHEN 'Perabotan' THEN 'Perabot'
                      WHEN 'Elektronik' THEN 'Elektronik'
                      ELSE 'Alat Praktik'
                    END",
                [(int)$request['id_katalog_barang'], $inventoryId]
            );
            if (!$matchingCatalogInventory) {
                throw new RuntimeException('Barang inventaris tidak sesuai dengan nama barang pada daftar terbaru.');
            }

            $borrower = Database::row(
                'SELECT id_pegawai, nama_pegawai, aktif FROM pegawai WHERE nip=? FOR UPDATE',
                [$request['nis']]
            );

            if ($borrower && $borrower['nama_pegawai'] !== $request['nama_peminjam']) {
                throw new RuntimeException('NIS sudah terdaftar dengan nama berbeda. Periksa Data Peminjam sebelum menyetujui pengajuan.');
            }
            if ($borrower && !$borrower['aktif']) {
                throw new RuntimeException('Profil peminjam berstatus nonaktif. Aktifkan profil di Data Peminjam sebelum menyetujui pengajuan.');
            }
            if (!$borrower) {
                Database::q(
                    'INSERT INTO pegawai (nama_pegawai, nip, jabatan, aktif) VALUES (?, ?, ?, 1)',
                    [$request['nama_peminjam'], $request['nis'], $request['kelas']]
                );
                $borrowerId = (int)$database->lastInsertId();
            } else {
                $borrowerId = (int)$borrower['id_pegawai'];
            }

            $loanId = self::simpanDalamTransaksi(
                $borrowerId,
                $request['tanggal_pinjam'],
                $request['batas_kembali'],
                [['id' => $inventoryId, 'jumlah' => $request['jumlah']]],
                $staffId
            );

            Database::q(
                "UPDATE pengajuan_peminjaman
                    SET status='Disetujui', id_inventaris=?, id_petugas=?, ditangani_pada=NOW()
                  WHERE id_pengajuan=? AND status='Menunggu'",
                [$inventoryId, $staffId, $requestId]
            );
            $database->commit();

            return $loanId;
        } catch (Throwable $error) {
            $database->rollBack();
            throw $error;
        }
    }

    /** Setujui pengajuan ruangan jika jadwalnya belum dipakai. */
    public static function setujuiPengajuanRuang(int $requestId, int $staffId): void
    {
        $database = Database::conn();
        $database->beginTransaction();

        try {
            $request = Database::row(
                "SELECT * FROM pengajuan_peminjaman_ruang
                  WHERE id_pengajuan_ruang=? AND status='Menunggu'
                  FOR UPDATE",
                [$requestId]
            );
            if (!$request) {
                throw new RuntimeException('Pengajuan ruangan tidak ditemukan atau sudah ditangani.');
            }

            Database::row('SELECT id_ruang FROM ruang WHERE id_ruang=? FOR UPDATE', [(int)$request['id_ruang']]);
            $conflict = Database::val(
                "SELECT COUNT(*)
                   FROM pengajuan_peminjaman_ruang
                  WHERE id_ruang=? AND status='Disetujui'
                    AND waktu_mulai < ? AND waktu_selesai > ?",
                [$request['id_ruang'], $request['waktu_selesai'], $request['waktu_mulai']]
            );
            if ($conflict) {
                throw new RuntimeException('Ruangan sudah disetujui untuk jadwal yang bertabrakan. Pilih jadwal lain.');
            }

            self::peminjamUntukPengajuan($request);
            Database::q(
                "UPDATE pengajuan_peminjaman_ruang
                    SET status='Disetujui', id_petugas=?, ditangani_pada=NOW()
                  WHERE id_pengajuan_ruang=? AND status='Menunggu'",
                [$staffId, $requestId]
            );
            $database->commit();
        } catch (Throwable $error) {
            $database->rollBack();
            throw $error;
        }
    }

    /** Tolak permintaan ruangan yang masih menunggu. */
    public static function tolakPengajuanRuang(int $requestId, int $staffId): void
    {
        $statement = Database::q(
            "UPDATE pengajuan_peminjaman_ruang
                SET status='Ditolak', id_petugas=?, ditangani_pada=NOW()
              WHERE id_pengajuan_ruang=? AND status='Menunggu'",
            [$staffId, $requestId]
        );
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Pengajuan ruangan tidak ditemukan atau sudah ditangani.');
        }
    }

    /** Tolak permintaan yang masih menunggu. */
    public static function tolakPengajuan(int $requestId, int $staffId): void
    {
        $statement = Database::q(
            "UPDATE pengajuan_peminjaman
                SET status='Ditolak', id_petugas=?, ditangani_pada=NOW()
              WHERE id_pengajuan=? AND status='Menunggu'",
            [$staffId, $requestId]
        );

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Pengajuan tidak ditemukan atau sudah ditangani.');
        }
    }

    /** Ambil inventaris tersedia dengan nama persis sesuai barang pada katalog terbaru. */
    public static function barangTersediaUntukKatalog(int $catalogItemId): array
    {
        return Database::all(
            "SELECT i.id_inventaris, i.kode_inventaris, i.nama, j.nama_jenis,
                    fn_stok_tersedia(i.id_inventaris) AS tersedia
               FROM katalog_barang_pinjam k
               JOIN inventaris i ON LOWER(TRIM(i.nama))=LOWER(TRIM(k.nama_barang))
               JOIN jenis j ON j.id_jenis=i.id_jenis
              WHERE k.id_katalog_barang=? AND i.kondisi <> 'Rusak Berat'
                AND j.nama_jenis=CASE k.kategori
                  WHEN 'Perabotan' THEN 'Perabot'
                  WHEN 'Elektronik' THEN 'Elektronik'
                  ELSE 'Alat Praktik'
                END
              HAVING tersedia > 0
              ORDER BY i.nama, i.kode_inventaris",
            [$catalogItemId]
        );
    }

    private static function peminjamUntukPengajuan(array $request): int
    {
        $borrower = Database::row(
            'SELECT id_pegawai, nama_pegawai, aktif FROM pegawai WHERE nip=? FOR UPDATE',
            [$request['nis']]
        );

        if ($borrower && $borrower['nama_pegawai'] !== $request['nama_peminjam']) {
            throw new RuntimeException('NIS sudah terdaftar dengan nama berbeda. Periksa Data Peminjam sebelum menyetujui pengajuan.');
        }
        if ($borrower && !$borrower['aktif']) {
            throw new RuntimeException('Profil peminjam berstatus nonaktif. Aktifkan profil di Data Peminjam sebelum menyetujui pengajuan.');
        }
        if (!$borrower) {
            Database::q(
                'INSERT INTO pegawai (nama_pegawai, nip, jabatan, aktif) VALUES (?, ?, ?, 1)',
                [$request['nama_peminjam'], $request['nis'], $request['kelas']]
            );

            return (int)Database::conn()->lastInsertId();
        }

        return (int)$borrower['id_pegawai'];
    }

    private static function tanggalWaktuValid(string $value): ?DateTimeImmutable
    {
        $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$dateTime || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
            return null;
        }

        return $dateTime;
    }

    private static function simpanDalamTransaksi(
        int $borrowerId,
        string $loanDate,
        string $returnDeadline,
        array $items,
        int $staffId
    ): int
    {
        $borrower = Database::row('SELECT aktif FROM pegawai WHERE id_pegawai=?', [$borrowerId]);
        if (!$borrower || !$borrower['aktif']) {
            throw new RuntimeException('Peminjam tidak ditemukan atau berstatus nonaktif.');
        }

        self::validasiTanggal($loanDate, $returnDeadline);

        $itemsByInventoryId = [];
        foreach ($items as $item) {
            $inventoryId = (int)($item['id'] ?? 0);
            $quantity = (int)($item['jumlah'] ?? 0);
            if ($inventoryId > 0 && $quantity > 0) {
                $itemsByInventoryId[$inventoryId] = ($itemsByInventoryId[$inventoryId] ?? 0) + $quantity;
            }
        }

        if (!$itemsByInventoryId) {
            throw new RuntimeException('Pilih minimal satu barang yang dipinjam.');
        }

        foreach (array_keys($itemsByInventoryId) as $inventoryId) {
            $duplicateCount = Database::val(
                "SELECT COUNT(*)
                   FROM peminjaman p
                   JOIN detail_pinjam d ON d.id_peminjaman=p.id_peminjaman
                  WHERE p.id_pegawai=?
                    AND d.id_inventaris=?
                    AND p.status_peminjaman='Dipinjam'",
                [$borrowerId, $inventoryId]
            );
            if ($duplicateCount) {
                throw new RuntimeException('Peminjam ini masih meminjam barang yang sama dan belum mengembalikannya (double).');
            }
        }

        Database::q(
            'INSERT INTO peminjaman (tanggal_pinjam,batas_kembali,status_peminjaman,id_pegawai,id_petugas) VALUES (?,?,?,?,?)',
            [$loanDate, $returnDeadline, 'Dipinjam', $borrowerId, $staffId]
        );
        $loanId = (int)Database::conn()->lastInsertId();

        foreach ($itemsByInventoryId as $inventoryId => $quantity) {
            Database::q('CALL sp_tambah_detail_pinjam(?,?,?)', [$loanId, $inventoryId, $quantity]);
        }

        return $loanId;
    }

    private static function validasiTanggal(string $loanDate, string $returnDeadline): void
    {
        $validDate = static function (string $date): bool {
            $parsed = DateTime::createFromFormat('!Y-m-d', $date);
            return $parsed && $parsed->format('Y-m-d') === $date;
        };

        if (!$validDate($loanDate) || !$validDate($returnDeadline) || $returnDeadline < $loanDate) {
            throw new RuntimeException('Tanggal pinjam atau batas kembali tidak valid.');
        }
    }

    // Jalankan stored procedure untuk mencatat pengembalian barang.
    public static function kembalikan(int $loanId): void
    {
        Database::q('CALL sp_kembalikan(?)', [$loanId]);
    }

    /** Barang yang masih tersedia untuk dipinjam. */
    public static function barangTersedia(): array
    {
        return Database::all(
            "SELECT *
               FROM (
                    SELECT i.id_inventaris, i.kode_inventaris, i.nama, j.nama_jenis,
                           fn_stok_tersedia(i.id_inventaris) AS tersedia
                      FROM inventaris i
                      JOIN jenis j ON j.id_jenis=i.id_jenis
                     WHERE i.kondisi <> 'Rusak Berat'
               ) x
              WHERE tersedia > 0
              ORDER BY nama"
        );
    }

    /** Daftar jenis barang yang diminta pengguna, berdasarkan lembar kategori. */
    public static function barangKatalog(string $searchQuery = ''): array
    {
        $sql = "SELECT k.id_katalog_barang, k.kategori, k.nama_barang,
                       COALESCE(i.total_stok, 0) AS total_stok,
                       i.ringkasan_kondisi, i.foto
                  FROM katalog_barang_pinjam k
                  LEFT JOIN (
                      SELECT nama_key,
                             SUM(total_stok) AS total_stok,
                             GROUP_CONCAT(
                                 CONCAT(kondisi, ' — ', total_unit, ' unit')
                                 ORDER BY FIELD(kondisi, 'Baik', 'Rusak Ringan', 'Rusak Berat')
                                 SEPARATOR ', '
                             ) AS ringkasan_kondisi,
                             MAX(foto) AS foto
                        FROM (
                            SELECT LOWER(TRIM(nama)) AS nama_key, kondisi,
                                   SUM(jumlah) AS total_unit,
                                   SUM(CASE WHEN kondisi <> 'Rusak Berat'
                                            THEN fn_stok_tersedia(id_inventaris)
                                            ELSE 0 END) AS total_stok,
                                   MAX(foto) AS foto
                              FROM inventaris
                             GROUP BY LOWER(TRIM(nama)), kondisi
                        ) kondisi_barang
                       GROUP BY nama_key
                  ) i ON i.nama_key=LOWER(TRIM(k.nama_barang))";
        $params = [];

        if ($searchQuery !== '') {
            $sql .= ' WHERE k.nama_barang LIKE ? OR k.kategori LIKE ?';
            $pattern = '%' . $searchQuery . '%';
            $params = [$pattern, $pattern];
        }

        $sql .= " ORDER BY FIELD(k.kategori, 'Perabotan', 'Elektronik', 'Alat Praktik/Lab'), k.nama_barang";

        return Database::all(
            $sql,
            $params
        );
    }

    /** Barang inventaris aktif yang belum dimasukkan ke daftar pengajuan publik. */
    public static function barangInventarisLainnya(string $searchQuery = ''): array
    {
        $sql = "SELECT nama, nama_jenis, SUM(total_stok) AS total_stok,
                       GROUP_CONCAT(
                           CONCAT(kondisi, ' — ', total_unit, ' unit')
                           ORDER BY FIELD(kondisi, 'Baik', 'Rusak Ringan', 'Rusak Berat')
                           SEPARATOR ', '
                       ) AS ringkasan_kondisi,
                       MAX(foto) AS foto
                  FROM (
                      SELECT i.nama, j.nama_jenis, i.kondisi,
                             SUM(i.jumlah) AS total_unit,
                             SUM(CASE WHEN i.kondisi <> 'Rusak Berat'
                                      THEN fn_stok_tersedia(i.id_inventaris)
                                      ELSE 0 END) AS total_stok,
                             MAX(i.foto) AS foto
                        FROM inventaris i
                        JOIN jenis j ON j.id_jenis=i.id_jenis
                       WHERE NOT EXISTS (
                             SELECT 1
                               FROM katalog_barang_pinjam k
                              WHERE LOWER(TRIM(k.nama_barang))=LOWER(TRIM(i.nama))
                       )";
        $params = [];

        if ($searchQuery !== '') {
            $sql .= ' AND (i.nama LIKE ? OR j.nama_jenis LIKE ?)';
            $pattern = '%' . $searchQuery . '%';
            $params = [$pattern, $pattern];
        }

        $sql .= " GROUP BY LOWER(TRIM(i.nama)), j.nama_jenis, i.kondisi
                  ) kondisi_barang
                 GROUP BY LOWER(TRIM(nama)), nama_jenis
                 ORDER BY nama";

        return Database::all($sql, $params);
    }

    /** Ambil daftar peminjam yang berstatus aktif. */
    public static function pegawaiAktif(): array
    {
        return Database::all('SELECT id_pegawai, nama_pegawai, nip, jabatan FROM pegawai WHERE aktif=1 ORDER BY nama_pegawai');
    }

    /** Ambil daftar peminjaman atau riwayat beserta ringkasan barangnya. */
    public static function daftar(string $status, int $limit = 0): array
    {
        $sortOrder = $status === 'Dipinjam'
            ? 'p.batas_kembali ASC'
            : 'p.dikembalikan_pada DESC';
        $limitSql = $limit ? ' LIMIT ' . (int)$limit : '';
        $sql = "SELECT p.id_peminjaman, p.tanggal_pinjam, p.batas_kembali,
                       p.tanggal_kembali, p.status_peminjaman, g.nama_pegawai, g.jabatan,
                       (p.status_peminjaman='Dipinjam' AND p.batas_kembali < CURDATE()) AS terlambat,
                       GROUP_CONCAT(CONCAT(i.nama,' (',d.jumlah,')') SEPARATOR ', ') AS barang
                  FROM peminjaman p
                  JOIN pegawai g ON g.id_pegawai=p.id_pegawai
                  JOIN detail_pinjam d ON d.id_peminjaman=p.id_peminjaman
                  JOIN inventaris i ON i.id_inventaris=d.id_inventaris
                 WHERE p.status_peminjaman=?
                 GROUP BY p.id_peminjaman
                 ORDER BY $sortOrder$limitSql";

        return Database::all($sql, [$status]);
    }
}
