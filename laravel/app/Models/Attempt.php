<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Attempt extends Model
{
    protected $fillable = ['user_id', 'lab_id', 'status', 'started_at', 'ends_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function attemptNodes(): HasMany
    {
        return $this->hasMany(AttemptNode::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(Result::class);
    }
}
