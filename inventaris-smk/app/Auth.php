<?php
// Autentikasi & otorisasi berdasarkan level petugas (administrator / operator).
// Peminjam TIDAK login: mereka hanya dicatat (diprofiling) oleh operator.
class Auth
{
    // Periksa username dan password, lalu simpan data pengguna ke session.
    public static function attempt(string $username, string $password): bool
    {
        $user = Database::row(
            'SELECT p.*, l.nama_level
               FROM petugas p
               JOIN level l ON l.id_level = p.id_level
              WHERE p.username = ?',
            [$username]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$user['id_petugas'],
            'nama' => $user['nama_petugas'],
            'level' => $user['nama_level'],
        ];

        return true;
    }

    // Kembalikan data pengguna yang sedang login.
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    // Hapus session saat pengguna keluar.
    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    /** Pastikan pengguna sudah login dan memiliki salah satu level yang diizinkan. */
    public static function guard(array $roles = []): array
    {
        $user = self::user();

        if (!$user) {
            redirect('login.php');
        }

        if ($roles && !in_array($user['level'], $roles, true)) {
            http_response_code(403);
            exit('<h3 style="font-family:sans-serif;padding:2rem">403 - Anda tidak memiliki akses ke halaman ini.</h3>');
        }

        return $user;
    }

    // Periksa apakah pengguna memiliki level tertentu.
    public static function is(string $level): bool
    {
        return (self::user()['level'] ?? '') === $level;
    }
}
