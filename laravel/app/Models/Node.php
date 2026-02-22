<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Node extends Model
{
    protected $fillable = ['lab_id', 'node_name', 'image', 'cpu', 'mem_mb', 'disk_gb'];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }
}
