<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    protected $fillable = ['attempt_id', 'score', 'error_json'];

    protected $casts = [
        'error_json' => 'array',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }
}
