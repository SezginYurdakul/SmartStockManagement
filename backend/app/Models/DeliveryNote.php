<?php

namespace App\Models;

use App\Enums\DeliveryNoteStatus;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryNote extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'delivery_number',
        'sales_order_id',
        'customer_id',
        'warehouse_id',
        'delivery_date',
        'status',
        'shipping_method',
        'tracking_number',
        'notes',
        'delivered_by',
        'delivered_at',
        'created_by',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'status' => DeliveryNoteStatus::class,
        'delivered_at' => 'datetime',
    ];

    /**
     * Company relationship
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Sales order relationship
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * Customer relationship
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Warehouse relationship
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Delivery note items
     */
    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }

    /**
     * Creator relationship
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Created by relationship (alias for creator)
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Delivered by relationship
     */
    public function deliveredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /**
     * Check if can be edited
     */
    public function canBeEdited(): bool
    {
        return $this->status->canEdit();
    }

    /**
     * Check if can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return $this->status->canCancel();
    }

    /**
     * Get total quantity shipped
     */
    public function getTotalQuantityShippedAttribute(): float
    {
        return $this->items->sum('quantity_shipped');
    }

    /**
     * Scope: By status
     */
    public function scopeStatus($query, $status)
    {
        if ($status instanceof DeliveryNoteStatus) {
            $status = $status->value;
        }
        return $query->where('status', $status);
    }

    /**
     * Scope: For sales order
     */
    public function scopeForSalesOrder($query, int $salesOrderId)
    {
        return $query->where('sales_order_id', $salesOrderId);
    }

    /**
     * Scope: For customer
     */
    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope: Date range
     */
    public function scopeDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('delivery_date', [$from, $to]);
    }

    /**
     * Scope: Search
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('delivery_number', 'ilike', "%{$term}%")
              ->orWhere('tracking_number', 'ilike', "%{$term}%")
              ->orWhereHas('customer', function ($cq) use ($term) {
                  $cq->where('name', 'ilike', "%{$term}%");
              });
        });
    }
}
