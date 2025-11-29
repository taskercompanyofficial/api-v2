<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
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
     * Get the users associated with this role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
    /**
     * Get the user who created this role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Get the user who last updated this role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    
    
    /**
     * Get the permissions associated with this role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withPivot(['status', 'created_by', 'updated_by'])
            ->withTimestamps();
    }
    
    /**
     * Get the role permissions for this role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function rolePermissions()
    {
        return $this->hasMany(RolePermission::class, 'role_id');
    }
}
