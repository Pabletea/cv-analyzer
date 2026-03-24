<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CvAnalysis extends Model
{
    protected $fillable = [
        'filename',
        'cv_text',
        'status',
        'result',
        'error_message',
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
}
