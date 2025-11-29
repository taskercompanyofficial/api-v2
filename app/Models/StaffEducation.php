<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffEducation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'staff_education';

    protected $fillable = [
        'staff_id',
        'institution_name',
        'degree_title',
        'field_of_study',
        'education_level',
        'start_date',
        'end_date',
        'is_completed',
        'gpa',
        'grade',
        'certificate_file',
        'is_verified',
        'verified_date',
        'verified_by',
        'notes'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'verified_date' => 'date',
        'is_completed' => 'boolean',
        'is_verified' => 'boolean',
        'gpa' => 'decimal:2',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}