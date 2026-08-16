<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prediction extends Model
{
    protected $fillable = [
        'age',
        'sex',
        'sbp',
        'dbp',
        'hr',
        'rr',
        'temp',
        'pain_score',
        'chief_complaint',
        'ats_category',
        'confidence',
    ];
}
