<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Membuat dump harian database MySQL ke storage/app/private/backups (PRD §5)';

    public function handle(): int
    {
        $db = (string) config('database.connections.mysql.database');
        $host = (string) config('database.connections.mysql.host', '127.0.0.1');
        $port = (string) config('database.connections.mysql.port', '3306');
        $user = (string) config('database.connections.mysql.username', 'root');
        $pass = (string) config('database.connections.mysql.password', '');

        $relDir = 'backups';
        Storage::disk('local')->makeDirectory($relDir);
        $filename = sprintf('%s/backup-%s-%s.sql', $relDir, $db, now()->format('Y-m-d_His'));
        $absPath = Storage::disk('local')->path($filename);

        $cmd = ['mysqldump', "-h{$host}", "-P{$port}", "-u{$user}"];
        if ($pass !== '') {
            $cmd[] = "-p{$pass}";
        }
        $cmd[] = '--single-transaction';
        $cmd[] = '--quick';
        $cmd[] = $db;

        $process = new Process($cmd);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Backup gagal: '.$process->getErrorOutput());

            return self::FAILURE;
        }

        file_put_contents($absPath, $process->getOutput());
        $this->info("Backup tersimpan di {$filename}");

        return self::SUCCESS;
    }
}
