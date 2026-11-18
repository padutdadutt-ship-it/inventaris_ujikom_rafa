<?php
// Membuat koneksi database dan menjalankan query menggunakan PDO.
class Database
{
    private static ?PDO $pdo = null;

    // Gunakan kembali koneksi yang sama selama aplikasi berjalan.
    public static function conn(): PDO
    {
        return self::$pdo ??= new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    // Jalankan query yang sudah disiapkan agar nilai parameter aman.
    public static function q(string $sql, array $p = []): PDOStatement
    {
        $statement = self::conn()->prepare($sql);
        $statement->execute($p);

        return $statement;
    }

    // Ambil semua baris hasil query.
    public static function all(string $sql, array $p = []): array
    {
        return self::q($sql, $p)->fetchAll();
    }

    // Ambil satu baris hasil query.
    public static function row(string $sql, array $p = []): ?array
    {
        return self::q($sql, $p)->fetch() ?: null;
    }

    // Ambil satu nilai dari hasil query.
    public static function val(string $sql, array $p = [])
    {
        return self::q($sql, $p)->fetchColumn();
    }
}
