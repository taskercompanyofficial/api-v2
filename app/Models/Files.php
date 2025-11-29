<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Files extends Model
{
    protected $fillable = ['file_name', 'file_path', 'file_size', 'file_mime_type', 'file_type', 'file_extension', 'file_status', 'file_description', 'file_created_at', 'file_updated_at'];

    public function getFileUrlAttribute($value)
    {
        return url($value);
    }
}
