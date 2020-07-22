<?php

namespace App\Jobs;

use App\Console\Commands\AggregateConversionEvents;
use App\Conversion;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Artisan;

class AggregateConversionEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $conversion;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Conversion $conversion)
    {
        $this->conversion = $conversion;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Artisan::call(AggregateConversionEvents::COMMAND, [
            '--conversion_id' => $this->conversion->id
        ]);
    }
}
