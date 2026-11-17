<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PingWorker implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Log::warning('PingWorker dijalankan oleh worker di background');
    }
}
