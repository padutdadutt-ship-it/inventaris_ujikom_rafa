<?php
// Menyimpan, mengambil, dan menghapus data induk berdasarkan konfigurasi master_config.php.
class Crud
{
    public function __construct(private array $config) {}

    // Ambil semua baris untuk tabel yang sedang dibuka.
    public function rows(): array
    {
        return Database::all($this->config['sql']);
    }

    /** Ambil pilihan untuk field select dari daftar atau query SQL. */
    public function options(array $field): array
    {
        if (is_array($field['o'])) {
            return array_map(
                fn($option) => ['v' => $option, 't' => $option],
                $field['o']
            );
        }

        return Database::all($field['o']);
    }

    // Validasi data formulir, kemudian tambahkan atau perbarui data.
    public function save(array $input, int $recordId = 0): void
    {
        $data = [];
        foreach ($this->config['fields'] as $field) {
            $name = $field['n'];
            $type = $field['t'];
            $label = $field['l'];
            $value = $type === 'bool'
                ? (($input[$name] ?? '') === '1' ? 1 : 0)
                : trim((string)($input[$name] ?? ''));

            if ($type === 'password') {
                if ($value === '') {
                    if ($recordId) {
                        continue;
                    }

                    throw new RuntimeException("$label wajib diisi.");
                }

                if (strlen($value) < 6) {
                    throw new RuntimeException("$label minimal 6 karakter.");
                }

                $value = password_hash($value, PASSWORD_DEFAULT);
            } else {
                if (!empty($field['r']) && $value === '' && $type !== 'bool') {
                    throw new RuntimeException("$label wajib diisi.");
                }

                if ($type === 'number') {
                    $value = (int)$value;
                    $minimum = $field['min'] ?? 0;

                    if ($value < $minimum) {
                        throw new RuntimeException("$label minimal $minimum.");
                    }
                }

                if (!empty($field['up'])) {
                    $value = strtoupper($value);
                }

                if ($type === 'date' && $value !== '' && !strtotime($value)) {
                    throw new RuntimeException("$label tidak valid.");
                }

                if ($value === '' && !in_array($type, ['number', 'bool'], true)) {
                    $value = null;
                }

                if (!empty($field['u']) && $value !== null && $this->exists($name, $value, $recordId)) {
                    throw new RuntimeException("$label \"$value\" sudah terdaftar (anti duplikat).");
                }
            }

            $data[$name] = $value;
        }

        $table = $this->config['table'];
        $primaryKey = $this->config['pk'];

        if ($recordId) {
            $updates = implode(',', array_map(fn($column) => "$column=?", array_keys($data)));
            Database::q(
                "UPDATE $table SET $updates WHERE $primaryKey=?",
                [...array_values($data), $recordId]
            );
        } else {
            $data += $this->config['auto'] ?? [];
            $columns = implode(',', array_keys($data));
            $placeholders = implode(',', array_fill(0, count($data), '?'));

            Database::q(
                "INSERT INTO $table ($columns) VALUES ($placeholders)",
                array_values($data)
            );
        }
    }

    // Hapus satu data berdasarkan primary key.
    public function delete(int $recordId): void
    {
        Database::q(
            "DELETE FROM {$this->config['table']} WHERE {$this->config['pk']}=?",
            [$recordId]
        );
    }

    // Periksa nilai unik, kecuali pada baris yang sedang diperbarui.
    private function exists(string $column, $value, int $recordId): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->config['table']} "
            . "WHERE $column=? AND {$this->config['pk']}<>?";

        return (bool)Database::val($sql, [$value, $recordId]);
    }
}
