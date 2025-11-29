<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffTraining extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'staff_training';

    protected $fillable = [
        'staff_id',
        'training_title',
        'training_provider',
        'training_type',
        'training_category',
        'start_date',
        'end_date',
        'duration_hours',
        'location',
        'instructor_name',
        'cost',
        'currency',
        'status',
        'completion_status',
        'score',
        'certificate_file',
        'is_mandatory',
        'expiry_date',
        'notes'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'expiry_date' => 'date',
        'cost' => 'decimal:2',
        'score' => 'decimal:2',
        'duration_hours' => 'integer',
        'is_mandatory' => 'boolean',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}