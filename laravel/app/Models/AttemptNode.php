<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptNode extends Model
{
    protected $fillable = ['attempt_id', 'node_name', 'instance_name'];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }
}
