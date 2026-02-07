<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Benchmark extends Model
{
    protected $fillable = [
        'category',
        'key',
        'label',
        'target_value',
        'min_value',
        'max_value',
        'order_index',
        'is_active'
    ];
}
