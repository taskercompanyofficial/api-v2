<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffSkill extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'staff_skills';

    protected $fillable = [
        'staff_id',
        'skill_name',
        'skill_category',
        'proficiency_level',
        'years_of_experience',
        'last_used_date',
        'is_certified',
        'certification_body',
        'certification_date',
        'certification_expiry',
        'description',
        'is_primary_skill'
    ];

    protected $casts = [
        'last_used_date' => 'date',
        'certification_date' => 'date',
        'certification_expiry' => 'date',
        'years_of_experience' => 'integer',
        'is_certified' => 'boolean',
        'is_primary_skill' => 'boolean',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}