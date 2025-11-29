<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffDocument extends Model
{
    protected $table = 'staff_documents';

    protected $fillable = [
        'staff_id','type','file_path','issued_at','expires_at','verified','notes'
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'verified' => 'boolean',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}