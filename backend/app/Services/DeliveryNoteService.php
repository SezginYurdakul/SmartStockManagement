<?php

namespace App\Services;

use App\Enums\DeliveryNoteStatus;
use App\Enums\SalesOrderStatus;
use App\Exceptions\BusinessException;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class DeliveryNoteService
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Get paginated delivery notes with filters
     */
    public function getDeliveryNotes(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DeliveryNote::query()
            ->with(['salesOrder.customer', 'warehouse', 'createdBy']);

        // Search
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('delivery_number', 'ilike', "%{$filters['search']}%")
                  ->orWhereHas('salesOrder', function ($sq) use ($filters) {
                      $sq->where('order_number', 'ilike', "%{$filters['search']}%");
                  });
            });
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Sales order filter
        if (!empty($filters['sales_order_id'])) {
            $query->where('sales_order_id', $filters['sales_order_id']);
        }

        // Warehouse filter
        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        // Date range
        if (!empty($filters['from_date'])) {
            $query->where('delivery_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('delivery_date', '<=', $filters['to_date']);
        }

        return $query->latest('delivery_date')->paginate($perPage);
    }

    /**
     * Get single delivery note with relations
     */
    public function getDeliveryNote(DeliveryNote $deliveryNote): DeliveryNote
    {
        return $deliveryNote->load([
            'salesOrder.customer',
            'warehouse',
            'items.product',
            'items.salesOrderItem',
            'createdBy',
        ]);
    }

    /**
     * Create delivery note from sales order
     */
    public function createFromSalesOrder(SalesOrder $salesOrder, array $data): DeliveryNote
    {
        // Check order status
        if (!in_array($salesOrder->status, [SalesOrderStatus::CONFIRMED, SalesOrderStatus::SHIPPED])) {
            throw new BusinessException('Can only create delivery notes for confirmed or partially shipped orders.');
        }

        Log::info('Creating delivery note from sales order', [
            'sales_order_id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
        ]);

        DB::beginTransaction();

        try {
            $companyId = Auth::user()->company_id;
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);

            $deliveryNote = DeliveryNote::create([
                'company_id' => $companyId,
                'sales_order_id' => $salesOrder->id,
                'customer_id' => $salesOrder->customer_id,
                'warehouse_id' => $warehouse->id,
                'delivery_number' => $this->generateDeliveryNumber(),
                'delivery_date' => $data['delivery_date'] ?? now(),
                'status' => DeliveryNoteStatus::DRAFT->value,
                'shipping_address' => $data['shipping_address'] ?? $salesOrder->shipping_address,
                'carrier' => $data['carrier'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Add items
            if (!empty($data['items'])) {
                $this->addItems($deliveryNote, $data['items'], $salesOrder);
            }

            DB::commit();

            Log::info('Delivery note created', [
                'delivery_note_id' => $deliveryNote->id,
                'delivery_number' => $deliveryNote->delivery_number,
            ]);

            return $deliveryNote->fresh(['salesOrder.customer', 'items.product', 'warehouse']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create delivery note', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Add items to delivery note
     */
    protected function addItems(DeliveryNote $deliveryNote, array $items, SalesOrder $salesOrder): void
    {
        foreach ($items as $itemData) {
            $salesOrderItem = SalesOrderItem::where('sales_order_id', $salesOrder->id)
                ->where('id', $itemData['sales_order_item_id'])
                ->firstOrFail();

            $quantity = $itemData['quantity'];
            $remainingQty = $salesOrderItem->quantity_ordered - $salesOrderItem->quantity_shipped;

            if ($quantity > $remainingQty) {
                throw new BusinessException(
                    "Cannot ship {$quantity} units. Only {$remainingQty} units remaining for product."
                );
            }

            DeliveryNoteItem::create([
                'delivery_note_id' => $deliveryNote->id,
                'sales_order_item_id' => $salesOrderItem->id,
                'product_id' => $salesOrderItem->product_id,
                'quantity_shipped' => $quantity,
                'lot_number' => $itemData['lot_number'] ?? null,
                'serial_number' => $itemData['serial_number'] ?? null,
                'notes' => $itemData['notes'] ?? null,
            ]);
        }
    }

    /**
     * Update delivery note
     */
    public function update(DeliveryNote $deliveryNote, array $data): DeliveryNote
    {
        if (!$deliveryNote->canBeEdited()) {
            throw new BusinessException('Delivery note cannot be edited in current status.');
        }

        Log::info('Updating delivery note', [
            'delivery_note_id' => $deliveryNote->id,
            'changes' => array_keys($data),
        ]);

        $deliveryNote->update([
            'delivery_date' => $data['delivery_date'] ?? $deliveryNote->delivery_date,
            'shipping_address' => $data['shipping_address'] ?? $deliveryNote->shipping_address,
            'carrier' => $data['carrier'] ?? $deliveryNote->carrier,
            'tracking_number' => $data['tracking_number'] ?? $deliveryNote->tracking_number,
            'notes' => $data['notes'] ?? $deliveryNote->notes,
        ]);

        return $deliveryNote->fresh();
    }

    /**
     * Confirm delivery note (ready for shipping)
     */
    public function confirm(DeliveryNote $deliveryNote): DeliveryNote
    {
        $currentStatus = $deliveryNote->status;

        if (!$currentStatus->canTransitionTo(DeliveryNoteStatus::CONFIRMED)) {
            throw new BusinessException("Cannot confirm delivery note from {$currentStatus->label()} status.");
        }

        if ($deliveryNote->items()->count() === 0) {
            throw new BusinessException('Cannot confirm delivery note without items.');
        }

        Log::info('Confirming delivery note', [
            'delivery_note_id' => $deliveryNote->id,
            'delivery_number' => $deliveryNote->delivery_number,
        ]);

        $deliveryNote->update([
            'status' => DeliveryNoteStatus::CONFIRMED->value,
        ]);

        return $deliveryNote->fresh();
    }

    /**
     * Ship delivery note - deduct stock and update sales order
     */
    public function ship(DeliveryNote $deliveryNote): DeliveryNote
    {
        $currentStatus = $deliveryNote->status;

        if (!$currentStatus->canTransitionTo(DeliveryNoteStatus::SHIPPED)) {
            throw new BusinessException("Cannot ship delivery note from {$currentStatus->label()} status.");
        }

        Log::info('Shipping delivery note', [
            'delivery_note_id' => $deliveryNote->id,
            'delivery_number' => $deliveryNote->delivery_number,
        ]);

        DB::beginTransaction();

        try {
            // Deduct stock for each item
            foreach ($deliveryNote->items as $item) {
                $this->stockService->issueStock([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $deliveryNote->warehouse_id,
                    'quantity' => $item->quantity_shipped,
                    'operation_type' => 'sale',
                    'transaction_type' => 'sales_order',
                    'reference_type' => DeliveryNote::class,
                    'reference_id' => $deliveryNote->id,
                    'reference_number' => $deliveryNote->delivery_number,
                    'lot_number' => $item->lot_number,
                    'notes' => "Delivery Note: {$deliveryNote->delivery_number}",
                ]);

                // Update sales order item shipped quantity
                $item->salesOrderItem->increment('quantity_shipped', $item->quantity_shipped);
            }

            $deliveryNote->update([
                'status' => DeliveryNoteStatus::SHIPPED->value,
                'shipped_at' => now(),
            ]);

            // Update sales order status if needed
            $this->updateSalesOrderStatus($deliveryNote->salesOrder);

            DB::commit();

            Log::info('Delivery note shipped', [
                'delivery_note_id' => $deliveryNote->id,
                'delivery_number' => $deliveryNote->delivery_number,
            ]);

            return $deliveryNote->fresh();

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to ship delivery note', [
                'delivery_note_id' => $deliveryNote->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Mark delivery note as delivered
     */
    public function markAsDelivered(DeliveryNote $deliveryNote): DeliveryNote
    {
        $currentStatus = $deliveryNote->status;

        if (!$currentStatus->canTransitionTo(DeliveryNoteStatus::DELIVERED)) {
            throw new BusinessException("Cannot mark as delivered from {$currentStatus->label()} status.");
        }

        Log::info('Marking delivery note as delivered', [
            'delivery_note_id' => $deliveryNote->id,
            'delivery_number' => $deliveryNote->delivery_number,
        ]);

        $deliveryNote->update([
            'status' => DeliveryNoteStatus::DELIVERED->value,
            'delivered_at' => now(),
        ]);

        // Update sales order status
        $this->updateSalesOrderStatus($deliveryNote->salesOrder);

        return $deliveryNote->fresh();
    }

    /**
     * Update sales order status based on delivery notes
     */
    protected function updateSalesOrderStatus(SalesOrder $salesOrder): void
    {
        $salesOrder->refresh();

        $totalOrdered = $salesOrder->items()->sum('quantity_ordered');
        $totalShipped = $salesOrder->items()->sum('quantity_shipped');

        if ($totalShipped >= $totalOrdered) {
            // All items shipped
            $allDelivered = $salesOrder->deliveryNotes()
                ->where('status', '!=', DeliveryNoteStatus::DELIVERED->value)
                ->doesntExist();

            if ($allDelivered && $salesOrder->status->canTransitionTo(SalesOrderStatus::DELIVERED)) {
                $salesOrder->update(['status' => SalesOrderStatus::DELIVERED->value]);
            } elseif ($salesOrder->status->canTransitionTo(SalesOrderStatus::SHIPPED)) {
                $salesOrder->update(['status' => SalesOrderStatus::SHIPPED->value]);
            }
        }
    }

    /**
     * Cancel delivery note
     */
    public function cancel(DeliveryNote $deliveryNote, ?string $reason = null): DeliveryNote
    {
        if ($deliveryNote->status === DeliveryNoteStatus::SHIPPED) {
            throw new BusinessException('Cannot cancel shipped delivery note. Stock has already been deducted.');
        }

        if ($deliveryNote->status === DeliveryNoteStatus::DELIVERED) {
            throw new BusinessException('Cannot cancel delivered delivery note.');
        }

        Log::info('Cancelling delivery note', [
            'delivery_note_id' => $deliveryNote->id,
            'delivery_number' => $deliveryNote->delivery_number,
            'reason' => $reason,
        ]);

        $deliveryNote->update([
            'notes' => $reason
                ? $deliveryNote->notes . "\nCancellation reason: " . $reason
                : $deliveryNote->notes,
        ]);

        $deliveryNote->delete();

        return $deliveryNote;
    }

    /**
     * Delete delivery note (only draft)
     */
    public function delete(DeliveryNote $deliveryNote): bool
    {
        if ($deliveryNote->status !== DeliveryNoteStatus::DRAFT) {
            throw new BusinessException('Only draft delivery notes can be deleted.');
        }

        Log::info('Deleting delivery note', [
            'delivery_note_id' => $deliveryNote->id,
            'delivery_number' => $deliveryNote->delivery_number,
        ]);

        $deliveryNote->items()->delete();
        return $deliveryNote->delete();
    }

    /**
     * Generate delivery number
     */
    public function generateDeliveryNumber(): string
    {
        $companyId = Auth::user()->company_id;
        $year = now()->format('Y');
        $prefix = "DN-{$year}-";

        $lastNote = DeliveryNote::withTrashed()
            ->where('company_id', $companyId)
            ->where('delivery_number', 'like', "{$prefix}%")
            ->orderByRaw("CAST(SUBSTRING(delivery_number FROM '[0-9]+$') AS INTEGER) DESC")
            ->first();

        if ($lastNote && preg_match('/(\d+)$/', $lastNote->delivery_number, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
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
        ], DeliveryNoteStatus::cases());
    }
}
