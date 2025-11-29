<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model
{
    use HasApiTokens;
    protected $fillable = [
        'created_by',
        'updated_by',
        'name',
        'email',
        'phone',
        'whatsapp',
        'customer_id',
        'is_care_of_customer',
        'status',
        'description',
        'kind_of_issue',
    ];
}
