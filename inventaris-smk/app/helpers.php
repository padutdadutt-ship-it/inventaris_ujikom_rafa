<?php
// Helper umum untuk tampilan dan navigasi.
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function tgl(?string $date): string
{
    return $date ? date('d M Y', strtotime($date)) : '-';
}

function post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

// Menyimpan dan menampilkan pesan setelah proses formulir.
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = [$type, $message];
}

function flash_show(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }

    [$type, $message] = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return '<div class="alert alert-' . e($type) . ' alert-dismissible fade show soft-alert" role="alert">'
        . e($message)
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// Membuat dan memeriksa token keamanan untuk formulir.
function csrf_field(): string
{
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));

    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Sesi tidak valid, muat ulang halaman.');
    }
}

/** Ambil pesan error yang ramah (pesan SIGNAL dari stored procedure / duplikat / relasi). */
function errmsg(Throwable $e): string
{
    if ($e instanceof PDOException) {
        $code = $e->errorInfo[1] ?? 0;
        if ($code == 1062) return 'Data sudah ada (duplikat).';
        if ($code == 1451) return 'Data sedang dipakai di tabel lain sehingga tidak bisa dihapus.';
        if ($code == 1644) return $e->errorInfo[2];
        return 'Kesalahan database: ' . $e->getMessage();
    }
    return $e->getMessage();
}

// Membuat ikon SVG langsung di halaman tanpa koneksi internet.
function icon(string $name, int $size = 20): string
{
    static $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'box' => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
        'tag' => '<path d="M20 12l-8 8-9-9V3h8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'home' => '<path d="M3 10l9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'out' => '<path d="M7 17L17 7M8 7h9v9"/>',
        'back' => '<path d="M17 7L7 17M16 17H7V8"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'trash' => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'print' => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    ];
    return '<svg class="ico" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
        . ($paths[$name] ?? '')
        . '</svg>';
}

/** Daftar menu: [alamat halaman, label, ikon, level pengguna yang diizinkan]. */
function menu_items(): array
{
    $administrator = 'administrator';
    $operator = 'operator';

    return [
        ['pages/dashboard.php', 'Dashboard', 'grid', [$administrator, $operator]],
        ['pages/peminjaman.php', 'Peminjaman', 'out', [$operator]],
        ['pages/pengembalian.php', 'Pengembalian', 'back', [$operator]],
        ['pages/master.php?m=inventaris', 'Inventaris', 'box', [$administrator]],
        ['pages/master.php?m=jenis', 'Kategori', 'tag', [$administrator]],
        ['pages/master.php?m=ruang', 'Ruang', 'home', [$administrator]],
        ['pages/master.php?m=pegawai', Auth::is($operator) ? 'Persetujuan Pengajuan' : 'Data Peminjam', 'users', [$administrator, $operator]],
        ['pages/master.php?m=petugas', 'User Petugas', 'user', [$administrator]],
        ['pages/laporan.php', 'Laporan', 'file', [$administrator]],
    ];
}
