<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lab extends Model
{
    protected $fillable = ['title', 'slug', 'duration', 'published', 'description', 'grader_script'];

    public function steps(): HasMany
    {
        return $this->hasMany(Step::class);
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }
}
