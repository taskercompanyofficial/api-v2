<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use App\Models\Route;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'created_by',
        'updated_by',
        'email_verified_at',
        'role_id',
        'status_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

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
     * Get the user who created this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
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
     * Get the user permissions for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class, 'user_id');
    }
    
    /**
     * Get all permissions for this user (combines role permissions and direct user permissions).
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAllPermissions(): Collection
    {
        // Get permissions directly assigned to the user
        $userPermissions = $this->permissions()->get();
        
        // Get permissions from the user's role
        $rolePermissions = $this->role ? $this->role->permissions()->get() : collect();
        
        // Merge and return unique permissions as a flat array
        return $userPermissions->concat($rolePermissions)->unique('id')->values();
    }
    
    /**
     * Get all routes this user has permission to access.
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getPermittedRoutes(): Collection
    {
        // Get all permissions for this user
        $permissionIds = $this->getAllPermissions()->pluck('id');
        
        // Get all parent routes (top-level navigation items)
        $parentRoutes = Route::with(['children' => function ($query) use ($permissionIds) {
            // For children, only include those with permissions the user has
            $query->whereIn('permission_id', $permissionIds)
                  ->orWhereNull('permission_id')
                  ->where('status', true)
                  ->orderBy('order');
        }])
        ->whereNull('parent_id')
        ->where(function ($query) use ($permissionIds) {
            // Include routes that either have a permission the user has, or no permission requirement
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
}
