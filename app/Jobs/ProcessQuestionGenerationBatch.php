<?php

namespace App\Jobs;

use App\Actions\Catalog\GenerateQuestionBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessQuestionGenerationBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 55;

    public function __construct(public int $batchId) {}

    public function handle(GenerateQuestionBatch $generate): void
    {
        $generate($this->batchId);
    }
}
