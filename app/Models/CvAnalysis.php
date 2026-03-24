<?php

namespace App\Models;

use App\Models\AnalysisConfig;

use Illuminate\Database\Eloquent\Model;

class CvAnalysis extends Model
{
    protected $fillable = [
        'filename',
        'cv_text',
        'status',
        'result',
        'error_message',
        'config_id',
    ];

    protected $casts = [
        'result' => 'array',
    ];

    public function isPending():bool{
        return $this->status === 'pending';
    }

    public function isCompleted():bool{
        return $this->status === 'completed';
    }

    public function isFailed():bool{
        return $this->status === 'failed';
    }

    public function config(){
        return $this->belongsTo(AnalysisConfig::class, 'config_id');
    }
}
