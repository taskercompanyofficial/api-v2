<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class FreeInstallation extends Model
{
    protected $fillable = [
        'customer_id',
        'contact_person',
        'phone',
        'product_type',
        'brand_id',
        'product_model',
        'purchase_date',
        'invoice_image',
        'warranty_image',
        'is_delivered',
        'is_wiring_done',
        'outdoor_bracket_needed',
        'extra_pipes',
        'extra_holes',
        'city',
        'status',
        'order_id',
        'admin_notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'is_delivered' => 'boolean',
        'is_wiring_done' => 'boolean',
        'outdoor_bracket_needed' => 'boolean',
        'extra_pipes' => 'integer',
        'extra_holes' => 'integer',
    ];

    protected $appends = ['invoice_image_url', 'warranty_image_url', 'display_name', 'price_display'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(AuthorizedBrand::class, 'brand_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get all order items for this free installation
     */
    public function orderItems(): MorphMany
    {
        return $this->morphMany(OrderItem::class, 'itemable');
    }

    /**
     * Get the full URL for the invoice image
     */
    public function getInvoiceImageUrlAttribute()
    {
        if ($this->invoice_image) {
            return url('storage/' . $this->invoice_image);
        }
        return null;
    }

    /**
     * Get the full URL for the warranty image
     */
    public function getWarrantyImageUrlAttribute()
    {
        if ($this->warranty_image) {
            return url('storage/' . $this->warranty_image);
        }
        return null;
    }

    /**
     * Get display name for the installation
     */
    public function getDisplayNameAttribute()
    {
        $productType = ucfirst(str_replace('_', ' ', $this->product_type));
        return "Free Installation - {$productType} ({$this->product_model})";
    }

    /**
     * Get price display (always 'Free Installation')
     */
    public function getPriceDisplayAttribute()
    {
        return 'Free Installation';
    }
}
