<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceivedNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_received_note_id',
        'purchase_order_item_id',
        'product_id',
        'line_number',
        'quantity_received',
        'quantity_accepted',
        'quantity_rejected',
        'uom_id',
        'unit_cost',
        'total_cost',
        'lot_number',
        'serial_number',
        'expiry_date',
        'manufacture_date',
        'storage_location',
        'bin_location',
        'inspection_status',
        'inspection_notes',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:3',
        'quantity_accepted' => 'decimal:3',
        'quantity_rejected' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'expiry_date' => 'date',
        'manufacture_date' => 'date',
    ];

    /**
     * GRN relationship
     */
    public function goodsReceivedNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class);
    }

    /**
     * Purchase order item relationship
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /**
     * Product relationship
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Unit of measure relationship
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Check if fully accepted
     */
    public function getIsFullyAcceptedAttribute(): bool
    {
        return $this->quantity_accepted >= $this->quantity_received;
    }

    /**
     * Check if fully rejected
     */
    public function getIsFullyRejectedAttribute(): bool
    {
        return $this->quantity_rejected >= $this->quantity_received;
    }

    /**
     * Get pending quantity (not yet accepted or rejected)
     */
    public function getPendingQuantityAttribute(): float
    {
        return $this->quantity_received - $this->quantity_accepted - $this->quantity_rejected;
    }

    /**
     * Calculate total cost
     */
    public function calculateTotalCost(): void
    {
        $this->total_cost = $this->quantity_accepted * $this->unit_cost;
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->calculateTotalCost();
        });
    }
}
