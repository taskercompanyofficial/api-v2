<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model
{
    use HasApiTokens;
    protected $fillable = [
        'created_by',
        'updated_by',
        'name',
        'avatar',
        'email',
        'phone',
        'whatsapp',
        'customer_id',
        'is_care_of_customer',
        'status',
        'description',
    ];

    protected $appends = ['avatar_url'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the full URL for the avatar
     */
    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return url('storage/' . $this->avatar);
        }
        return null;
    }
    
}
