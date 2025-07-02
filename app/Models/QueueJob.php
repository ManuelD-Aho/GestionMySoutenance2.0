<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueueJob extends Model
{
    use HasFactory;

    protected $table = 'queue_jobs';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'integer';
    public $timestamps = false; // Using custom created_at, started_at, completed_at

    protected $fillable = [
        'job_name',
        'payload',
        'status',
        'attempts',
        'created_at',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array', // Assuming payload is JSON
        'attempts' => 'integer',
        'created_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
