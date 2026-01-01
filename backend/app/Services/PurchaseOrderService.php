<?php

namespace App\Services;

use App\Enums\PoStatus;
use App\Exceptions\BusinessException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PurchaseOrderService
{
    /**
     * Get paginated purchase orders with filters
     */
    public function getPurchaseOrders(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->with(['supplier', 'warehouse', 'creator']);

        // Search by order number
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('order_number', 'ilike', "%{$filters['search']}%")
                  ->orWhereHas('supplier', function ($sq) use ($filters) {
                      $sq->where('name', 'ilike', "%{$filters['search']}%");
                  });
            });
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->status($filters['status']);
        }

        // Supplier filter
        if (!empty($filters['supplier_id'])) {
            $query->forSupplier($filters['supplier_id']);
        }

        // Warehouse filter
        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        // Date range
        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $query->dateRange($filters['from_date'], $filters['to_date']);
        }

        // Overdue filter
        if (!empty($filters['overdue'])) {
            $query->overdue();
        }

        return $query->latest('order_date')->paginate($perPage);
    }

    /**
     * Get purchase order with relationships
     */
    public function getPurchaseOrder(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $purchaseOrder->load([
            'supplier',
            'warehouse',
            'items.product',
            'items.unitOfMeasure',
            'creator',
            'approver',
            'goodsReceivedNotes',
        ]);
    }

    /**
     * Create a new purchase order
     */
    public function create(array $data): PurchaseOrder
    {
        Log::info('Creating new purchase order', [
            'supplier_id' => $data['supplier_id'],
            'warehouse_id' => $data['warehouse_id'],
        ]);

        DB::beginTransaction();

        try {
            $companyId = Auth::user()->company_id;

            // Generate order number if not provided
            if (empty($data['order_number'])) {
                $data['order_number'] = $this->generateOrderNumber();
            }

            // Get supplier defaults
            $supplier = Supplier::find($data['supplier_id']);
            if ($supplier) {
                $data['currency'] = $data['currency'] ?? $supplier->currency;
                $data['payment_terms'] = $data['payment_terms'] ?? "{$supplier->payment_terms_days} days";
                $data['payment_due_days'] = $data['payment_due_days'] ?? $supplier->payment_terms_days;
            }

            // Create purchase order
            $purchaseOrder = PurchaseOrder::create([
                'company_id' => $companyId,
                'order_number' => $data['order_number'],
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'order_date' => $data['order_date'] ?? now(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => $data['status'] ?? PoStatus::DRAFT->value,
                'currency' => $data['currency'] ?? 'USD',
                'exchange_rate' => $data['exchange_rate'] ?? 1.0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'shipping_cost' => $data['shipping_cost'] ?? 0,
                'other_charges' => $data['other_charges'] ?? 0,
                'payment_terms' => $data['payment_terms'] ?? null,
                'payment_due_days' => $data['payment_due_days'] ?? null,
                'shipping_method' => $data['shipping_method'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'meta_data' => $data['meta_data'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Add items
            if (!empty($data['items'])) {
                $this->addItems($purchaseOrder, $data['items']);
            }

            DB::commit();

            Log::info('Purchase order created successfully', [
                'purchase_order_id' => $purchaseOrder->id,
                'order_number' => $purchaseOrder->order_number,
            ]);

            return $purchaseOrder->fresh(['supplier', 'warehouse', 'items.product']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create purchase order', [
                'supplier_id' => $data['supplier_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update purchase order
     */
    public function update(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        if (!$purchaseOrder->canBeEdited()) {
            throw new BusinessException('Purchase order cannot be edited in current status.');
        }

        Log::info('Updating purchase order', [
            'purchase_order_id' => $purchaseOrder->id,
            'changes' => array_keys($data),
        ]);

        DB::beginTransaction();

        try {
            $purchaseOrder->update([
                'supplier_id' => $data['supplier_id'] ?? $purchaseOrder->supplier_id,
                'warehouse_id' => $data['warehouse_id'] ?? $purchaseOrder->warehouse_id,
                'order_date' => $data['order_date'] ?? $purchaseOrder->order_date,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? $purchaseOrder->expected_delivery_date,
                'currency' => $data['currency'] ?? $purchaseOrder->currency,
                'exchange_rate' => $data['exchange_rate'] ?? $purchaseOrder->exchange_rate,
                'discount_amount' => $data['discount_amount'] ?? $purchaseOrder->discount_amount,
                'shipping_cost' => $data['shipping_cost'] ?? $purchaseOrder->shipping_cost,
                'other_charges' => $data['other_charges'] ?? $purchaseOrder->other_charges,
                'payment_terms' => $data['payment_terms'] ?? $purchaseOrder->payment_terms,
                'payment_due_days' => $data['payment_due_days'] ?? $purchaseOrder->payment_due_days,
                'shipping_method' => $data['shipping_method'] ?? $purchaseOrder->shipping_method,
                'shipping_address' => $data['shipping_address'] ?? $purchaseOrder->shipping_address,
                'notes' => $data['notes'] ?? $purchaseOrder->notes,
                'internal_notes' => $data['internal_notes'] ?? $purchaseOrder->internal_notes,
                'meta_data' => $data['meta_data'] ?? $purchaseOrder->meta_data,
                'updated_by' => Auth::id(),
            ]);

            // Update items if provided
            if (isset($data['items'])) {
                $this->syncItems($purchaseOrder, $data['items']);
            }

            DB::commit();

            Log::info('Purchase order updated successfully', [
                'purchase_order_id' => $purchaseOrder->id,
            ]);

            return $purchaseOrder->fresh(['supplier', 'warehouse', 'items.product']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to update purchase order', [
                'purchase_order_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Add items to purchase order
     */
    public function addItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        $lineNumber = $purchaseOrder->items()->max('line_number') ?? 0;

        foreach ($items as $item) {
            $lineNumber++;

            PurchaseOrderItem::create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $item['product_id'],
                'line_number' => $lineNumber,
                'description' => $item['description'] ?? null,
                'quantity_ordered' => $item['quantity_ordered'],
                'uom_id' => $item['uom_id'],
                'unit_price' => $item['unit_price'],
                'discount_percentage' => $item['discount_percentage'] ?? 0,
                'tax_percentage' => $item['tax_percentage'] ?? 0,
                'expected_delivery_date' => $item['expected_delivery_date'] ?? $purchaseOrder->expected_delivery_date,
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    /**
     * Sync items (replace all)
     */
    public function syncItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        // Delete existing items
        $purchaseOrder->items()->delete();

        // Add new items
        $this->addItems($purchaseOrder, $items);
    }

    /**
     * Update a single item
     */
    public function updateItem(PurchaseOrderItem $item, array $data): PurchaseOrderItem
    {
        if (!$item->purchaseOrder->canBeEdited()) {
            throw new BusinessException('Purchase order cannot be edited in current status.');
        }

        $item->update($data);

        return $item->fresh();
    }

    /**
     * Delete a single item
     */
    public function deleteItem(PurchaseOrderItem $item): void
    {
        if (!$item->purchaseOrder->canBeEdited()) {
            throw new BusinessException('Purchase order cannot be edited in current status.');
        }

        $item->delete();
    }

    /**
     * Submit for approval
     */
    public function submitForApproval(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $currentStatus = $purchaseOrder->status_enum;
        $targetStatus = PoStatus::PENDING_APPROVAL;

        if (!$currentStatus || !in_array($targetStatus, $currentStatus->allowedTransitions())) {
            throw new BusinessException('Only draft orders can be submitted for approval.');
        }

        if ($purchaseOrder->items()->count() === 0) {
            throw new BusinessException('Cannot submit an order without items.');
        }

        Log::info('Submitting purchase order for approval', [
            'purchase_order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
        ]);

        $purchaseOrder->update([
            'status' => $targetStatus->value,
            'updated_by' => Auth::id(),
        ]);

        return $purchaseOrder->fresh();
    }

    /**
     * Approve purchase order
     */
    public function approve(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $currentStatus = $purchaseOrder->status_enum;
        $targetStatus = PoStatus::APPROVED;

        if (!$currentStatus || !in_array($targetStatus, $currentStatus->allowedTransitions())) {
            throw new BusinessException('Purchase order cannot be approved in current status.');
        }

        Log::info('Approving purchase order', [
            'purchase_order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
            'approved_by' => Auth::id(),
        ]);

        $purchaseOrder->update([
            'status' => $targetStatus->value,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        return $purchaseOrder->fresh();
    }

    /**
     * Reject (back to draft)
     */
    public function reject(PurchaseOrder $purchaseOrder, ?string $reason = null): PurchaseOrder
    {
        $currentStatus = $purchaseOrder->status_enum;
        $targetStatus = PoStatus::DRAFT;

        if (!$currentStatus || !in_array($targetStatus, $currentStatus->allowedTransitions())) {
            throw new BusinessException('Only pending orders can be rejected.');
        }

        Log::info('Rejecting purchase order', [
            'purchase_order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
            'reason' => $reason,
        ]);

        $purchaseOrder->update([
            'status' => $targetStatus->value,
            'internal_notes' => $reason ? "Rejected: {$reason}\n" . $purchaseOrder->internal_notes : $purchaseOrder->internal_notes,
            'updated_by' => Auth::id(),
        ]);

        return $purchaseOrder->fresh();
    }

    /**
     * Mark as sent to supplier
     */
    public function markAsSent(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $currentStatus = $purchaseOrder->status_enum;
        $targetStatus = PoStatus::SENT;

        if (!$currentStatus || !in_array($targetStatus, $currentStatus->allowedTransitions())) {
            throw new BusinessException('Purchase order cannot be sent in current status.');
        }

        Log::info('Marking purchase order as sent', [
            'purchase_order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
        ]);

        $purchaseOrder->update([
            'status' => $targetStatus->value,
            'updated_by' => Auth::id(),
        ]);

        return $purchaseOrder->fresh();
    }

    /**
     * Cancel purchase order
     */
    public function cancel(PurchaseOrder $purchaseOrder, ?string $reason = null): PurchaseOrder
    {
        $currentStatus = $purchaseOrder->status_enum;
        $targetStatus = PoStatus::CANCELLED;

        if (!$currentStatus || !in_array($targetStatus, $currentStatus->allowedTransitions())) {
            throw new BusinessException('Purchase order cannot be cancelled in current status.');
        }

        Log::info('Cancelling purchase order', [
            'purchase_order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
            'reason' => $reason,
        ]);

        $purchaseOrder->update([
            'status' => $targetStatus->value,
            'internal_notes' => $reason ? "Cancelled: {$reason}\n" . $purchaseOrder->internal_notes : $purchaseOrder->internal_notes,
            'updated_by' => Auth::id(),
        ]);

        return $purchaseOrder->fresh();
    }

    /**
     * Close purchase order
     */
    public function close(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $currentStatus = $purchaseOrder->status_enum;
        $targetStatus = PoStatus::CLOSED;

        if (!$currentStatus || !in_array($targetStatus, $currentStatus->allowedTransitions())) {
            throw new BusinessException('Purchase order cannot be closed in current status.');
        }

        Log::info('Closing purchase order', [
            'purchase_order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
        ]);

        $purchaseOrder->update([
            'status' => $targetStatus->value,
            'updated_by' => Auth::id(),
        ]);

        return $purchaseOrder->fresh();
    }

    /**
     * Delete purchase order (soft delete)
     */
    public function delete(PurchaseOrder $purchaseOrder): bool
    {
        $currentStatus = $purchaseOrder->status_enum;

        if (!$currentStatus || !in_array($currentStatus, [PoStatus::DRAFT, PoStatus::CANCELLED])) {
            throw new BusinessException('Only draft or cancelled orders can be deleted.');
        }

        Log::info('Deleting purchase order', [
            'purchase_order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
        ]);

        return $purchaseOrder->delete();
    }

    /**
     * Generate next order number
     */
    public function generateOrderNumber(): string
    {
        $companyId = Auth::user()->company_id;
        $year = now()->format('Y');
        $prefix = "PO-{$year}-";

        // Include soft-deleted records to avoid duplicate order numbers
        $lastOrder = PurchaseOrder::withTrashed()
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
     * Get pending orders for a supplier
     */
    public function getPendingOrdersForSupplier(int $supplierId): Collection
    {
        return PurchaseOrder::forSupplier($supplierId)
            ->pending()
            ->with(['items.product'])
            ->get();
    }

    /**
     * Get overdue orders
     */
    public function getOverdueOrders(): Collection
    {
        return PurchaseOrder::overdue()
            ->with(['supplier', 'items'])
            ->get();
    }

    /**
     * Get statistics
     */
    public function getStatistics(array $filters = []): array
    {
        $query = PurchaseOrder::query();

        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $query->dateRange($filters['from_date'], $filters['to_date']);
        }

        return [
            'total_orders' => $query->clone()->count(),
            'draft_orders' => $query->clone()->status(PoStatus::DRAFT->value)->count(),
            'pending_approval' => $query->clone()->status(PoStatus::PENDING_APPROVAL->value)->count(),
            'sent_orders' => $query->clone()->status(PoStatus::SENT->value)->count(),
            'received_orders' => $query->clone()->whereIn('status', [
                PoStatus::RECEIVED->value,
                PoStatus::PARTIALLY_RECEIVED->value,
            ])->count(),
            'total_amount' => $query->clone()->whereNotIn('status', [
                PoStatus::DRAFT->value,
                PoStatus::CANCELLED->value,
            ])->sum('total_amount'),
            'overdue_orders' => PurchaseOrder::overdue()->count(),
        ];
    }
}
