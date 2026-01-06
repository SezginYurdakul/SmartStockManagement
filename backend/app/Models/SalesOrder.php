<?php

namespace App\Models;

use App\Enums\SalesOrderStatus;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'order_number',
        'customer_id',
        'warehouse_id',
        'order_date',
        'requested_delivery_date',
        'promised_delivery_date',
        'status',
        'currency',
        'exchange_rate',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_cost',
        'total_amount',
        'payment_terms',
        'shipping_method',
        'shipping_address',
        'notes',
        'internal_notes',
        'meta_data',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'requested_delivery_date' => 'date',
        'promised_delivery_date' => 'date',
        'status' => SalesOrderStatus::class,
        'exchange_rate' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'meta_data' => 'array',
        'approved_at' => 'datetime',
    ];

    /**
     * Company relationship
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
     * Order items
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class)->orderBy('line_number');
    }

    /**
     * Delivery notes
     */
    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    /**
     * Creator relationship
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * CreatedBy relationship (alias for creator)
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Updater relationship
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * ApprovedBy relationship
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if order can be edited
     */
    public function canBeEdited(): bool
    {
        return $this->status->canEdit();
    }

    /**
     * Check if order can be approved
     */
    public function canBeApproved(): bool
    {
        return $this->status->requiresApproval();
    }

    /**
     * Check if order can be shipped
     */
    public function canBeShipped(): bool
    {
        return $this->status->canShip();
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return $this->status->canCancel();
    }

    /**
     * Get total quantity ordered
     */
    public function getTotalQuantityOrderedAttribute(): float
    {
        return $this->items->sum('quantity_ordered');
    }

    /**
     * Get total quantity shipped
     */
    public function getTotalQuantityShippedAttribute(): float
    {
        return $this->items->sum('quantity_shipped');
    }

    /**
     * Get remaining quantity to ship
     */
    public function getRemainingQuantityAttribute(): float
    {
        return $this->total_quantity_ordered - $this->total_quantity_shipped;
    }

    /**
     * Get shipping progress percentage
     */
    public function getShippingProgressAttribute(): float
    {
        if ($this->total_quantity_ordered <= 0) {
            return 0;
        }

        return round(($this->total_quantity_shipped / $this->total_quantity_ordered) * 100, 2);
    }

    /**
     * Calculate and update totals from items
     */
    public function calculateTotals(): void
    {
        $subtotal = $this->items()->sum('line_total');
        $taxAmount = $this->items()->sum('tax_amount');

        $this->subtotal = $subtotal;
        $this->tax_amount = $taxAmount;
        $this->total_amount = $subtotal - $this->discount_amount + $taxAmount + $this->shipping_cost;
    }

    /**
     * Scope: By status
     */
    public function scopeStatus($query, $status)
    {
        if ($status instanceof SalesOrderStatus) {
            $status = $status->value;
        }
        return $query->where('status', $status);
    }

    /**
     * Scope: Pending orders
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [
            SalesOrderStatus::PENDING_APPROVAL->value,
            SalesOrderStatus::APPROVED->value,
            SalesOrderStatus::CONFIRMED->value,
            SalesOrderStatus::PROCESSING->value,
            SalesOrderStatus::PARTIALLY_SHIPPED->value,
        ]);
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
        return $query->whereBetween('order_date', [$from, $to]);
    }

    /**
     * Scope: Search
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('order_number', 'ilike', "%{$term}%")
              ->orWhereHas('customer', function ($cq) use ($term) {
                  $cq->where('name', 'ilike', "%{$term}%");
              });
        });
    }
}
