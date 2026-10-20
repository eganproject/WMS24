<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--keep-days= : Override how many days of backups to keep}
        {--no-prune : Do not delete old backups after this run}';

    protected $description = 'Dump the MySQL/MariaDB database to a gzip file and delete old backups.';

    public const FILE_PREFIX = 'db-backup-';

    public function handle(): int
    {
        $connectionName = config('database.default');
        $connection = config("database.connections.{$connectionName}", []);
        $driver = $connection['driver'] ?? null;

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return $this->failWith("Backup only supports MySQL/MariaDB, current connection [{$connectionName}] uses [{$driver}].");
        }

        $directory = rtrim((string) config('backup.path'), DIRECTORY_SEPARATOR);
        File::ensureDirectoryExists($directory, 0750);

        $timestamp = now()->format('Y-m-d_His');
        $finalPath = $directory.DIRECTORY_SEPARATOR.self::FILE_PREFIX.$connection['database'].'-'.$timestamp.'.sql.gz';
        $partialPath = $finalPath.'.part';
        $credentialsPath = tempnam(sys_get_temp_dir(), 'dbbackup');

        $gzip = null;
        $startedAt = microtime(true);

        try {
            // Credentials go in a private temp file so the password never shows up in the process list.
            File::put($credentialsPath, $this->credentialsFile($connection));
            chmod($credentialsPath, 0600);

            $gzip = gzopen($partialPath, 'wb6');
            if ($gzip === false) {
                return $this->failWith("Cannot write backup file [{$partialPath}].");
            }

            $errorOutput = '';
            $process = new Process($this->buildCommand($credentialsPath, $connection['database']));
            $process->setTimeout((int) config('backup.timeout', 3600));
            $process->run(function (string $type, string $buffer) use ($gzip, &$errorOutput) {
                if ($type === Process::OUT) {
                    gzwrite($gzip, $buffer);
                } else {
                    $errorOutput .= $buffer;
                }
            });

            gzclose($gzip);
            $gzip = null;

            if (! $process->isSuccessful()) {
                File::delete($partialPath);

                return $this->failWith('mysqldump failed (exit '.$process->getExitCode().'): '.trim($errorOutput));
            }

            File::move($partialPath, $finalPath);
            chmod($finalPath, 0640);
        } catch (Throwable $e) {
            if (is_resource($gzip)) {
                gzclose($gzip);
            }
            File::delete($partialPath);

            return $this->failWith('Backup failed: '.$e->getMessage());
        } finally {
            File::delete($credentialsPath);
        }

        $size = $this->humanSize((int) filesize($finalPath));
        $seconds = round(microtime(true) - $startedAt, 1);
        $message = "Database backup saved to {$finalPath} ({$size}, {$seconds}s).";
        $this->info($message);
        Log::info($message);

        if (! $this->option('no-prune')) {
            $this->pruneOldBackups($directory);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function buildCommand(string $credentialsPath, string $database): array
    {
        $dump = [
            (string) config('backup.mysqldump_binary', 'mysqldump'),
            '--defaults-extra-file='.$credentialsPath,
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--no-tablespaces',
            '--triggers',
            '--hex-blob',
            '--default-character-set=utf8mb4',
            $database,
        ];

        if (! config('backup.low_priority', true)) {
            return $dump;
        }

        $finder = new ExecutableFinder;
        $prefix = [];

        if ($ionice = $finder->find('ionice')) {
            array_push($prefix, $ionice, '-c3');
        }
        if ($nice = $finder->find('nice')) {
            array_push($prefix, $nice, '-n', '19');
        }

        return array_merge($prefix, $dump);
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function credentialsFile(array $connection): string
    {
        $lines = ['[client]'];
        $lines[] = 'user='.$this->quote((string) ($connection['username'] ?? ''));
        $lines[] = 'password='.$this->quote((string) ($connection['password'] ?? ''));

        if (! empty($connection['unix_socket'])) {
            $lines[] = 'socket='.$this->quote((string) $connection['unix_socket']);
        } else {
            $lines[] = 'host='.$this->quote((string) ($connection['host'] ?? '127.0.0.1'));
            $lines[] = 'port='.(int) ($connection['port'] ?? 3306);
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function quote(string $value): string
    {
        return '"'.addcslashes($value, '\\"').'"';
    }

    private function pruneOldBackups(string $directory): void
    {
        $keepDays = $this->option('keep-days') !== null
            ? (int) $this->option('keep-days')
            : (int) config('backup.keep_days', 14);

        if ($keepDays < 1) {
            return;
        }

        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $deleted = 0;

        foreach (File::glob($directory.DIRECTORY_SEPARATOR.self::FILE_PREFIX.'*.sql.gz') as $file) {
            if (filemtime($file) < $cutoff) {
                File::delete($file);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->line("Deleted {$deleted} backup(s) older than {$keepDays} days.");
        }
    }

    private function failWith(string $message): int
    {
        $this->error($message);
        Log::error('[db:backup] '.$message);

        return self::FAILURE;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 1).' '.$units[$i];
    }
}
