<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    use HasFactory;
    protected $fillable = [
        'created_by',
        'updated_by',
        'name',
        'slug',
        'description',
        'status',
    ];
    /**
     * Get the users associated with this permission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permissions')
            ->withPivot(['status', 'created_by', 'updated_by'])
            ->withTimestamps();
    }
    
    /**
     * Get the user who created this permission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Get the user who last updated this permission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    
    /**
     * Get the user permissions for this permission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class, 'permission_id');
    }
    
    /**
     * Get the roles associated with this permission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
            ->withPivot(['status', 'created_by', 'updated_by'])
            ->withTimestamps();
    }
    
    /**
     * Get the role permissions for this permission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'permission_id');
    }
    
    /**
     * Get the routes associated with this permission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function routes(): HasMany
    {
        return $this->hasMany(Route::class, 'permission_id');
    }
}
