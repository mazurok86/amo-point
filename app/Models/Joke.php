<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Joke extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'type',
        'setup',
        'punchline',
        'fetched_at',
    ];

    protected $casts = [
        'external_id' => 'integer',
        'fetched_at' => 'datetime',
    ];
}
