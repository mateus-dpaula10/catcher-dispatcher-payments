<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DrainMailQueue extends Command
{
    protected $signature = 'queue:drain-mails
        {--connection=database : Connection do queue (database/redis/etc)}
        {--queue=default : Nome da fila}
        {--tries=3 : Tentativas por job}
        {--timeout=120 : Timeout por job (segundos)}
        {--sleep=1 : Sleep entre checagens}
    ';

    protected $description = 'Processa a fila de emails e encerra (stop-when-empty). Ideal para cron.';

    public function handle(): int
    {
        $connection = (string) $this->option('connection');
        $queue      = (string) $this->option('queue');
        $tries      = (int) $this->option('tries');
        $timeout    = (int) $this->option('timeout');
        $sleep      = (int) $this->option('sleep');

        // roda o worker e encerra quando a fila estiver vazia
        $exitCode = Artisan::call('queue:work', [
            $connection,
            '--queue' => $queue,
            '--stop-when-empty' => true,
            '--tries' => $tries,
            '--timeout' => $timeout,
            '--sleep' => $sleep,
        ]);

        // opcional: imprimir output no log do cron
        $this->line(Artisan::output());

        return (int) $exitCode;
    }
}
