<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Dados;

class BackupSusanTable extends Command
{
    protected $signature = 'backup:susan-table';
    protected $description = 'Backup somente da tabela do DadosSusanPetRescue (streaming, cross-platform)';

    public function handle(): int
    {
        $table = (new Dados())->getTable();

        $backupDir = storage_path('backups/mysql');
        if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

        $ts = now()->format('Y-m-d_H-i-s');

        $canGzip = function_exists('gzopen') && function_exists('gzwrite');
        $file = $backupDir . DIRECTORY_SEPARATOR . $table . "_{$ts}.sql" . ($canGzip ? '.gz' : '');

        $pdo = DB::connection()->getPdo();

        // helper para escrever no arquivo (gzip ou normal)
        if ($canGzip) {
            $fh = gzopen($file, 'wb9');
            if (!$fh) {
                $this->error("Não consegui criar o arquivo: {$file}");
                return self::FAILURE;
            }
            $write = fn(string $s) => gzwrite($fh, $s);
            $close = fn() => gzclose($fh);
        } else {
            $fh = fopen($file, 'wb');
            if (!$fh) {
                $this->error("Não consegui criar o arquivo: {$file}");
                return self::FAILURE;
            }
            $write = fn(string $s) => fwrite($fh, $s);
            $close = fn() => fclose($fh);
        }

        // Cabeçalho + estrutura
        $write("-- Backup table: {$table}\n");
        $write("-- Generated at: " . now()->toDateTimeString() . "\n\n");

        $create = DB::select("SHOW CREATE TABLE `{$table}`");
        $createSql = $create[0]->{'Create Table'} ?? null;

        if (!$createSql) {
            $close();
            $this->error("Não consegui obter SHOW CREATE TABLE de {$table}");
            return self::FAILURE;
        }

        $write("DROP TABLE IF EXISTS `{$table}`;\n");
        $write($createSql . ";\n\n");

        // Dados (em lotes)
        $chunk = 1000;

        DB::table($table)
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use ($table, $pdo, $write) {
                foreach ($rows as $row) {
                    $data = (array) $row;

                    $cols = array_map(fn($c) => "`{$c}`", array_keys($data));

                    $vals = array_map(function ($v) use ($pdo) {
                        if (is_null($v)) return "NULL";
                        if (is_bool($v)) return $v ? "1" : "0";
                        if (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v))) return (string) $v;

                        // quote seguro via PDO (melhor que replace manual)
                        return $pdo->quote((string) $v);
                    }, array_values($data));

                    $write("INSERT INTO `{$table}` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
                }
            }, 'id');

        $close();

        $this->info("Backup gerado: {$file}");
        return self::SUCCESS;
    }
}
