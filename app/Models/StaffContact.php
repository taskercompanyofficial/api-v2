<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffContact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'staff_id',
        'contact_type',
        'name',
        'relationship',
        'phone',
        'email',
        'address',
        'is_primary',
        'notes'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}