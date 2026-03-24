<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CvBatch extends Model
{
    protected $fillable = [
        'config_id',
        'status',
        'total',
        'processed',
    ];

    public function analyses()
    {
        return $this->hasMany(CvAnalysis::class, 'batch_id');
    }

    public function config()
    {
        return $this->belongsTo(AnalysisConfig::class, 'config_id');
    }

    public function isCompleted(): bool
    {
        return $this->processed >= $this->total;
    }

    public function ranking(): array
    {
        return $this->analyses()
            ->where('status', 'completed')
            ->get()
            ->sortByDesc(fn($a) => $a->result['score'] ?? 0)
            ->values()
            ->map(fn($a, $i) => [
                'rank'     => $i + 1,
                'id'       => $a->id,
                'filename' => $a->filename,
                'score'    => $a->result['score'] ?? null,
                'summary'  => $a->result['summary'] ?? null,
                'fit_for_position' => $a->result['fit_for_position'] ?? null,
                'recommended_role' => $a->result['recommended_role'] ?? null,
                'main_skills'      => $a->result['main_skills'] ?? [],
            ])
            ->toArray();
    }
}