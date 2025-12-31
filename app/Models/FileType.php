<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'max_file_size',
        'mime_types',
        'extensions',
        'icon',
        'color',
        'is_image',
        'is_document',
        'is_video',
        'is_audio',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'mime_types' => 'array',
        'extensions' => 'array',
        'is_image' => 'boolean',
        'is_document' => 'boolean',
        'is_video' => 'boolean',
        'is_audio' => 'boolean',
        'status' => 'boolean',
        'max_file_size' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->max_file_size;
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    /**
     * Check if file type accepts a specific MIME type
     */
    public function acceptsMimeType(string $mimeType): bool
    {
        if (empty($this->mime_types)) {
            return false;
        }
        
        return in_array($mimeType, $this->mime_types);
    }

    /**
     * Check if file type accepts a specific extension
     */
    public function acceptsExtension(string $extension): bool
    {
        if (empty($this->extensions)) {
            return false;
        }
        
        $extension = strtolower(ltrim($extension, '.'));
        return in_array($extension, array_map('strtolower', $this->extensions));
    }

    /**
     * Scope: Only active file types
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope: Only image types
     */
    public function scopeImages($query)
    {
        return $query->where('is_image', true);
    }

    /**
     * Scope: Only document types
     */
    public function scopeDocuments($query)
    {
        return $query->where('is_document', true);
    }

    /**
     * Scope: Only video types
     */
    public function scopeVideos($query)
    {
        return $query->where('is_video', true);
    }

    /**
     * Scope: Only audio types
     */
    public function scopeAudios($query)
    {
        return $query->where('is_audio', true);
    }

    /**
     * Scope: Order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }
}
