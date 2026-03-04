<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffTodo extends Model
{
    protected $fillable = [
        'staff_id',
        'title',
        'description',
        'due_date',
        'status',
        'priority',
    ];

    protected $casts = [
        'due_date' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
