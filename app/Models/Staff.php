<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Model
{
    use SoftDeletes, HasApiTokens;

    protected $table = 'staff';

    protected $fillable = [
        'code',
        'slug',
        'first_name',
        'middle_name',
        'last_name',
        'cnic',
        'dob',
        'gender',
        'email',
        'phone',
        'profile_image',
        'cnic_front_image',
        'cnic_back_image',
        'permanent_address',
        'city',
        'state',
        'postal_code',
        'designation',
        'joining_date',
        'status',
        'notes',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'dob' => 'date',
        'joining_date' => 'date',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(StaffDocument::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(StaffHistory::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(StaffContact::class);
    }

    public function education(): HasMany
    {
        return $this->hasMany(StaffEducation::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(StaffCertification::class);
    }

    public function training(): HasMany
    {
        return $this->hasMany(StaffTraining::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(StaffSkill::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(StaffAsset::class);
    }

    public function vehicleAssignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    public function vehicleUsageLogs(): HasMany
    {
        return $this->hasMany(VehicleUsageLog::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'designation');
    }
    
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }
}