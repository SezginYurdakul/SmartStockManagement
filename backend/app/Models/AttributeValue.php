<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeValue extends Model
{
    protected $fillable = [
        'attribute_id',
        'value',
        'label',
        'order',
        'is_active',
    ];

    protected $casts = [
        'attribute_id' => 'integer',
        'order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the attribute that owns this value
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * Get display label (use label if set, otherwise value)
     */
    public function getDisplayLabelAttribute(): string
    {
        return $this->label ?? $this->value;
    }

    /**
     * Scope: Get only active values
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
