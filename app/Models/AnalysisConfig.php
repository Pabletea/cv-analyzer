<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;



class AnalysisConfig extends Model
{
    protected $fillable = [
        'name',
        'position',
        'prompt_extra',
        'required_skills',
        'min_years_experience',
    ];

    protected $casts = [
        'required_skills' => 'array',
    ];
}
