<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_name',
        'record_id',
        'action',
        'user_id',
        'user_name',
        'user_role',
        'old_values',
        'new_values',
        'changed_fields',
        'ip_address',
        'user_agent',
        'request_method',
        'request_url',
        'additional_info'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    protected $dates = ['created_at'];
}