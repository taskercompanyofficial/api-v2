<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Authenticatable
{
    use SoftDeletes, HasApiTokens, Notifiable;

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
        'device_token',
        'profile_image',
        'cnic_front_image',
        'cnic_back_image',
        'permanent_address',
        'city',
        'state',
        'postal_code',
        'joining_date',
        'notes',
        'has_access_in_crm',
        'crm_login_email',
        'crm_login_password',
        'role_id',
        'status_id',
        'created_by',
        'updated_by',
        'branch_id',
    ];

    protected $casts = [
        'dob' => 'date',
        'joining_date' => 'date',
    ];

    protected $hidden = [
        'crm_login_password',
    ];

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return 'id';
    }

    /**
     * Get the column name for the "email" column.
     *
     * @return string
     */
    public function getEmailForPasswordReset()
    {
        return $this->crm_login_email;
    }

    /**
     * Get the password for the user (for authentication).
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->crm_login_password;
    }

    /**
     * Get the column name for the "remember me" token.
     *
     * @return string
     */
    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    /**
     * Get the role associated with the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the status associated with the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get the branch associated with the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(OurBranch::class);
    }

    /**
     * Get the staff member who created this staff record.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    /**
     * Get the staff member who last updated this staff record.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }

    /**
     * Get the permissions associated with the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
            ->withPivot(['status', 'created_by', 'updated_by'])
            ->withTimestamps();
    }
    
    /**
     * Get the user permissions for this staff member.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class, 'staff_id');
    }
    
    /**
     * Get all permissions for this staff member (combines role permissions and direct staff permissions).
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAllPermissions(): Collection
    {
        // Get permissions directly assigned to the staff member
        $staffPermissions = $this->permissions()->get();
        
        // Get permissions from the staff member's role
        $rolePermissions = $this->role ? $this->role->permissions()->get() : collect();
        
        // Merge and return unique permissions as a flat array
        return $staffPermissions->concat($rolePermissions)->unique('id')->values();
    }
    
    /**
     * Get all routes this staff member has permission to access.
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getPermittedRoutes(): Collection
    {
        // Get all permissions for this staff member
        $permissionIds = $this->getAllPermissions()->pluck('id');
        
        // Get all parent routes (top-level navigation items)
        $parentRoutes = \App\Models\Route::with(['children' => function ($query) use ($permissionIds) {
            // For children, only include those with permissions the staff member has
            $query->whereIn('permission_id', $permissionIds)
                  ->orWhereNull('permission_id')
                  ->where('status', true)
                  ->orderBy('order');
        }])
        ->whereNull('parent_id')
        ->where(function ($query) use ($permissionIds) {
            // Include routes that either have a permission the staff member has, or no permission requirement
            $query->whereIn('permission_id', $permissionIds)
                  ->orWhereNull('permission_id');
        })
        ->where('status', true)
        ->orderBy('order')
        ->get();
        
        // Filter out parent routes with no accessible children
        return $parentRoutes->filter(function ($route) {
            // Keep routes that either have accessible children or are endpoints themselves
            return $route->children->isNotEmpty() || !empty($route->path);
        });
    }
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

    public function staffRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
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