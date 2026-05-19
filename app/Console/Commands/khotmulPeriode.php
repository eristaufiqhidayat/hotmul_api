<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\KhotmulService;

class KhotmulPeriode extends Command
{
    protected $signature = 'cron:khotmul';
    protected $description = 'Generate khotmul periode & juz assignment';

    public function handle(KhotmulService $service)
    {
        //$service->runCron();
        //$service->runPushperiode(1);
        $service->runNextPeriode();

        $this->info('Cron khotmul berhasil dijalankan');
    }
}