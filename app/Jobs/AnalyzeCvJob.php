<?php

namespace App\Jobs;

use App\Models\AnalysisConfig;
use App\Models\CvAnalysis;
use App\Services\CvAnalyzerService;
use App\Services\PdfExtractorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeCvJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        private CvAnalysis  $analysis,
        private string      $filePath,
        private ?AnalysisConfig $config = null
    )
    {}

    public function handle(
        PdfExtractorService $extractor,
        CvAnalyzerService   $analyzer
    ): void {
        try {
            $this->analysis->update(['status' => 'processing']);

            $text   = $extractor->extract($this->filePath);
            $result = $analyzer->analyze($text, $this->config);

            $this->analysis->update([
                'cv_text' => $text,
                'status'  => 'completed',
                'result'  => $result,
            ]);

        } catch (Throwable $e) {
            Log::error('CvAnalysis failed', [
                'analysis_id' => $this->analysis->id,
                'error'       => $e->getMessage(),
            ]);

            $this->analysis->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        } finally {
            // Actualiza el progreso del batch si existe
            if ($this->analysis->batch_id) {
                $batch = \App\Models\CvBatch::find($this->analysis->batch_id);
                if ($batch) {
                    $batch->increment('processed');
                    if ($batch->isCompleted()) {
                        $batch->update(['status' => 'completed']);
                    }
                }
            }
        }
    }
}
