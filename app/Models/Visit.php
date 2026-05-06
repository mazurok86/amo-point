<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'host',
        'visitor_uid',
        'ip',
        'country',
        'city',
        'device',
        'browser',
        'os',
        'page_url',
        'referrer',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
