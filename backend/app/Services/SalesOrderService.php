<?php

namespace App\Services;

use App\Enums\SalesOrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Stock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SalesOrderService
{
    protected CustomerGroupPriceService $priceService;
    protected StockService $stockService;

    public function __construct(CustomerGroupPriceService $priceService, StockService $stockService)
    {
        $this->priceService = $priceService;
        $this->stockService = $stockService;
    }

    /**
     * Get paginated sales orders with filters
     */
    public function getSalesOrders(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SalesOrder::query()
            ->with(['customer', 'createdBy']);

        // Search
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('order_number', 'ilike', "%{$filters['search']}%")
                  ->orWhereHas('customer', function ($cq) use ($filters) {
                      $cq->where('name', 'ilike', "%{$filters['search']}%");
                  });
            });
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Customer filter
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        // Date range
        if (!empty($filters['from_date'])) {
            $query->where('order_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('order_date', '<=', $filters['to_date']);
        }

        // Pending approval
        if (!empty($filters['pending_approval'])) {
            $query->where('status', SalesOrderStatus::PENDING_APPROVAL->value);
        }

        return $query->latest('order_date')->paginate($perPage);
    }

    /**
     * Get single sales order with relations
     */
    public function getSalesOrder(SalesOrder $salesOrder): SalesOrder
    {
        return $salesOrder->load([
            'customer.customerGroup',
            'items.product',
            'deliveryNotes',
            'createdBy',
            'approvedBy',
        ]);
    }

    /**
     * Create new sales order
     */
    public function create(array $data): SalesOrder
    {
        Log::info('Creating sales order', [
            'customer_id' => $data['customer_id'],
        ]);

        DB::beginTransaction();

        try {
            $companyId = Auth::user()->company_id;
            $customer = Customer::findOrFail($data['customer_id']);

            $salesOrder = SalesOrder::create([
                'company_id' => $companyId,
                'customer_id' => $customer->id,
                'warehouse_id' => $data['warehouse_id'],
                'order_number' => $this->generateOrderNumber(),
                'order_date' => $data['order_date'] ?? now(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => SalesOrderStatus::DRAFT->value,
                'shipping_address' => $data['shipping_address'] ?? $customer->shipping_address,
                'billing_address' => $data['billing_address'] ?? $customer->billing_address,
                'notes' => $data['notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0,
                'created_by' => Auth::id(),
            ]);

            // Add items
            if (!empty($data['items'])) {
                $this->addItems($salesOrder, $data['items'], $customer);
            }

            DB::commit();

            Log::info('Sales order created', [
                'sales_order_id' => $salesOrder->id,
                'order_number' => $salesOrder->order_number,
            ]);

            return $salesOrder->fresh(['customer', 'items.product']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create sales order', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update sales order
     * Releases reservation if status changes from CONFIRMED to a non-confirmed status
     */
    public function update(SalesOrder $salesOrder, array $data): SalesOrder
    {
        if (!$salesOrder->canBeEdited()) {
            throw new BusinessException('Sales order cannot be edited in current status.');
        }

        Log::info('Updating sales order', [
            'sales_order_id' => $salesOrder->id,
            'changes' => array_keys($data),
        ]);

        DB::beginTransaction();

        try {
            $oldStatus = $salesOrder->status;
            $newStatus = isset($data['status']) ? SalesOrderStatus::from($data['status']) : null;

            // If status is being changed from CONFIRMED to a non-confirmed status, release reservations
            if ($oldStatus === SalesOrderStatus::CONFIRMED && $newStatus && $newStatus !== SalesOrderStatus::CONFIRMED) {
                $this->releaseStockForOrder($salesOrder);
            }

            $salesOrder->update([
                'expected_delivery_date' => $data['expected_delivery_date'] ?? $salesOrder->expected_delivery_date,
                'shipping_address' => $data['shipping_address'] ?? $salesOrder->shipping_address,
                'billing_address' => $data['billing_address'] ?? $salesOrder->billing_address,
                'notes' => $data['notes'] ?? $salesOrder->notes,
                'internal_notes' => $data['internal_notes'] ?? $salesOrder->internal_notes,
                'discount_amount' => $data['discount_amount'] ?? $salesOrder->discount_amount,
                'tax_amount' => $data['tax_amount'] ?? $salesOrder->tax_amount,
                'status' => $newStatus?->value ?? $salesOrder->status,
            ]);

            // Update items if provided
            if (isset($data['items'])) {
                $customer = $salesOrder->customer;
                $salesOrder->items()->delete();
                $this->addItems($salesOrder, $data['items'], $customer);
            }

            $this->recalculateTotals($salesOrder);

            DB::commit();

            return $salesOrder->fresh(['customer', 'items.product']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Add items to sales order
     */
    protected function addItems(SalesOrder $salesOrder, array $items, Customer $customer): void
    {
        $subtotal = 0;

        foreach ($items as $itemData) {
            $product = Product::findOrFail($itemData['product_id']);
            $quantity = $itemData['quantity'];

            // Calculate price
            $priceInfo = $this->priceService->calculateEffectivePrice(
                $product,
                $customer->customer_group_id,
                $quantity
            );

            $unitPrice = $itemData['unit_price'] ?? $priceInfo['effective_price'];
            $lineTotal = $quantity * $unitPrice;

            // Get UOM - use product's default UOM if not provided
            $uomId = $itemData['uom_id'] ?? $product->uom_id ?? 1; // Default to UOM ID 1 if not set

            SalesOrderItem::create([
                'sales_order_id' => $salesOrder->id,
                'product_id' => $product->id,
                'quantity_ordered' => $quantity,
                'uom_id' => $uomId,
                'unit_price' => $unitPrice,
                'discount_amount' => $itemData['discount_amount'] ?? 0,
                'tax_amount' => $itemData['tax_amount'] ?? 0,
                'line_total' => $lineTotal,
                'notes' => $itemData['notes'] ?? null,
            ]);

            $subtotal += $lineTotal;
        }

        $salesOrder->update([
            'subtotal' => $subtotal,
            'total_amount' => $subtotal + $salesOrder->tax_amount - $salesOrder->discount_amount,
        ]);
    }

    /**
     * Recalculate order totals
     */
    protected function recalculateTotals(SalesOrder $salesOrder): void
    {
        $subtotal = $salesOrder->items()->sum('line_total');
        $taxAmount = $salesOrder->items()->sum('tax_amount');

        $salesOrder->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $subtotal + $taxAmount - $salesOrder->discount_amount,
        ]);
    }

    /**
     * Submit order for approval
     */
    public function submitForApproval(SalesOrder $salesOrder): SalesOrder
    {
        $currentStatus = $salesOrder->status;

        if (!$currentStatus->canTransitionTo(SalesOrderStatus::PENDING_APPROVAL)) {
            throw new BusinessException("Cannot submit order for approval from {$currentStatus->label()} status.");
        }

        if ($salesOrder->items()->count() === 0) {
            throw new BusinessException('Cannot submit order without items.');
        }

        Log::info('Submitting sales order for approval', [
            'sales_order_id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
        ]);

        $salesOrder->update([
            'status' => SalesOrderStatus::PENDING_APPROVAL->value,
        ]);

        return $salesOrder->fresh();
    }

    /**
     * Approve sales order
     */
    public function approve(SalesOrder $salesOrder): SalesOrder
    {
        $currentStatus = $salesOrder->status;

        if (!$currentStatus->canTransitionTo(SalesOrderStatus::APPROVED)) {
            throw new BusinessException("Cannot approve order from {$currentStatus->label()} status.");
        }

        Log::info('Approving sales order', [
            'sales_order_id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
            'approved_by' => Auth::id(),
        ]);

        $salesOrder->update([
            'status' => SalesOrderStatus::APPROVED->value,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $salesOrder->fresh();
    }

    /**
     * Reject sales order
     * Releases any reserved stock if order was confirmed
     */
    public function reject(SalesOrder $salesOrder, ?string $reason = null): SalesOrder
    {
        $currentStatus = $salesOrder->status;

        if (!$currentStatus->canTransitionTo(SalesOrderStatus::REJECTED)) {
            throw new BusinessException("Cannot reject order from {$currentStatus->label()} status.");
        }

        Log::info('Rejecting sales order', [
            'sales_order_id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
            'reason' => $reason,
        ]);

        DB::beginTransaction();

        try {
            // Release reserved stock if order was confirmed
            if ($currentStatus === SalesOrderStatus::CONFIRMED) {
                $this->releaseStockForOrder($salesOrder);
            }

            $salesOrder->update([
                'status' => SalesOrderStatus::REJECTED->value,
                'internal_notes' => $reason
                    ? $salesOrder->internal_notes . "\nRejection reason: " . $reason
                    : $salesOrder->internal_notes,
            ]);

            DB::commit();

            return $salesOrder->fresh();

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject sales order', [
                'sales_order_id' => $salesOrder->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Confirm sales order (after approval)
     * Automatically reserves stock for all items
     */
    public function confirm(SalesOrder $salesOrder): SalesOrder
    {
        $currentStatus = $salesOrder->status;

        if (!$currentStatus->canTransitionTo(SalesOrderStatus::CONFIRMED)) {
            throw new BusinessException("Cannot confirm order from {$currentStatus->label()} status.");
        }

        Log::info('Confirming sales order', [
            'sales_order_id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
        ]);

        DB::beginTransaction();

        try {
            $salesOrder->update([
                'status' => SalesOrderStatus::CONFIRMED->value,
            ]);

            // Automatically reserve stock for all items
            $this->reserveStockForOrder($salesOrder);

            DB::commit();

            Log::info('Sales order confirmed and stock reserved', [
                'sales_order_id' => $salesOrder->id,
            ]);

            return $salesOrder->fresh(['customer', 'items.product']);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to confirm sales order', [
                'sales_order_id' => $salesOrder->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Mark order as shipped
     */
    public function markAsShipped(SalesOrder $salesOrder): SalesOrder
    {
        $currentStatus = $salesOrder->status;

        if (!$currentStatus->canTransitionTo(SalesOrderStatus::SHIPPED)) {
            throw new BusinessException("Cannot mark order as shipped from {$currentStatus->label()} status.");
        }

        // Check if all items are shipped via delivery notes
        $totalOrdered = $salesOrder->items()->sum('quantity_ordered');
        $totalShipped = $salesOrder->items()->sum('quantity_shipped');

        if ($totalShipped < $totalOrdered) {
            throw new BusinessException('Cannot mark as shipped: not all items have been shipped.');
        }

        Log::info('Marking sales order as shipped', [
            'sales_order_id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
        ]);

        $salesOrder->update([
            'status' => SalesOrderStatus::SHIPPED->value,
        ]);

        return $salesOrder->fresh();
    }

    /**
     * Mark order as delivered
     */
    public function markAsDelivered(SalesOrder $salesOrder): SalesOrder
    {
        $currentStatus = $salesOrder->status;

        if (!$currentStatus->canTransitionTo(SalesOrderStatus::DELIVERED)) {
            throw new BusinessException("Cannot mark order as delivered from {$currentStatus->label()} status.");
        }

        Log::info('Marking sales order as delivered', [
            'sales_order_id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
        ]);

        $salesOrder->update([
            'status' => SalesOrderStatus::DELIVERED->value,
        ]);

        return $salesOrder->fresh();
    }

    /**
     * Cancel sales order
     * Releases any reserved stock if order was confirmed
     */
    public function cancel(SalesOrder $salesOrder, ?string $reason = null): SalesOrder
    {
        $currentStatus = $salesOrder->status;

        if (!$currentStatus->canTransitionTo(SalesOrderStatus::CANCELLED)) {
            throw new BusinessException("Cannot cancel order from {$currentStatus->label()} status.");
        }

        Log::info('Cancelling sales order', [
            'sales_order_id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
            'reason' => $reason,
        ]);

        DB::beginTransaction();

        try {
            // Release reserved stock if order was confirmed
            if ($currentStatus === SalesOrderStatus::CONFIRMED) {
                $this->releaseStockForOrder($salesOrder);
            }

            $salesOrder->update([
                'status' => SalesOrderStatus::CANCELLED->value,
                'internal_notes' => $reason
                    ? $salesOrder->internal_notes . "\nCancellation reason: " . $reason
                    : $salesOrder->internal_notes,
            ]);

            DB::commit();

            return $salesOrder->fresh();

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel sales order', [
                'sales_order_id' => $salesOrder->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete sales order (only draft)
     */
    public function delete(SalesOrder $salesOrder): bool
    {
        if ($salesOrder->status !== SalesOrderStatus::DRAFT) {
            throw new BusinessException('Only draft orders can be deleted.');
        }

        Log::info('Deleting sales order', [
            'sales_order_id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
        ]);

        $salesOrder->items()->delete();
        return $salesOrder->delete();
    }

    /**
     * Reserve stock for all items in a confirmed sales order
     */
    protected function reserveStockForOrder(SalesOrder $salesOrder): void
    {
        if (!$salesOrder->warehouse_id) {
            Log::warning('Cannot reserve stock: sales order has no warehouse', [
                'sales_order_id' => $salesOrder->id,
            ]);
            return;
        }

        $salesOrder->load('items.product');

        foreach ($salesOrder->items as $item) {
            try {
                $this->stockService->reserveStock(
                    $item->product_id,
                    $salesOrder->warehouse_id,
                    $item->quantity_ordered,
                    null, // lot_number
                    Stock::OPERATION_SALE,
                    false // skipQualityCheck
                );

                Log::info('Stock reserved for sales order item', [
                    'sales_order_id' => $salesOrder->id,
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity_ordered,
                ]);
            } catch (BusinessException $e) {
                Log::error('Failed to reserve stock for sales order item', [
                    'sales_order_id' => $salesOrder->id,
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other items even if one fails
            }
        }
    }

    /**
     * Release reserved stock for all items in a sales order
     */
    protected function releaseStockForOrder(SalesOrder $salesOrder): void
    {
        if (!$salesOrder->warehouse_id) {
            Log::warning('Cannot release stock: sales order has no warehouse', [
                'sales_order_id' => $salesOrder->id,
            ]);
            return;
        }

        $salesOrder->load('items.product');

        foreach ($salesOrder->items as $item) {
            try {
                $this->stockService->releaseReservation(
                    $item->product_id,
                    $salesOrder->warehouse_id,
                    $item->quantity_ordered,
                    null // lot_number
                );

                Log::info('Stock reservation released for sales order item', [
                    'sales_order_id' => $salesOrder->id,
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity_ordered,
                ]);
            } catch (BusinessException $e) {
                Log::error('Failed to release stock reservation for sales order item', [
                    'sales_order_id' => $salesOrder->id,
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other items even if one fails
            }
        }
    }

    /**
     * Generate order number
     */
    public function generateOrderNumber(): string
    {
        $companyId = Auth::user()->company_id;
        $year = now()->format('Y');
        $companyIdPadded = str_pad($companyId, 3, '0', STR_PAD_LEFT);
        $prefix = "SO-{$year}-{$companyIdPadded}-";

        $lastOrder = SalesOrder::withTrashed()
            ->where('company_id', $companyId)
            ->where('order_number', 'like', "{$prefix}%")
            ->orderByRaw("CAST(SUBSTRING(order_number FROM '[0-9]+$') AS INTEGER) DESC")
            ->first();

        if ($lastOrder && preg_match('/(\d+)$/', $lastOrder->order_number, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get order statistics
     */
    public function getStatistics(array $filters = []): array
    {
        $query = SalesOrder::query();

        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $query->whereBetween('order_date', [$filters['from_date'], $filters['to_date']]);
        }

        return [
            'total_orders' => $query->clone()->count(),
            'draft_orders' => $query->clone()->where('status', SalesOrderStatus::DRAFT->value)->count(),
            'pending_approval' => $query->clone()->where('status', SalesOrderStatus::PENDING_APPROVAL->value)->count(),
            'confirmed_orders' => $query->clone()->where('status', SalesOrderStatus::CONFIRMED->value)->count(),
            'shipped_orders' => $query->clone()->where('status', SalesOrderStatus::SHIPPED->value)->count(),
            'delivered_orders' => $query->clone()->where('status', SalesOrderStatus::DELIVERED->value)->count(),
            'cancelled_orders' => $query->clone()->where('status', SalesOrderStatus::CANCELLED->value)->count(),
            'total_revenue' => $query->clone()->where('status', SalesOrderStatus::DELIVERED->value)->sum('total_amount'),
        ];
    }

    /**
     * Get available statuses for dropdown
     */
    public function getStatuses(): array
    {
        return array_map(fn($status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'color' => $status->color(),
        ], SalesOrderStatus::cases());
    }
}
