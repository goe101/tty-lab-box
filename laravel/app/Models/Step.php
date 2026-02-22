<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Step extends Model
{
    protected $fillable = ['lab_id', 'order', 'title', 'markdown'];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }
}
