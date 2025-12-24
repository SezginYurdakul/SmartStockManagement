<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class GoodsReceivedNoteService
{
    /**
     * Get paginated GRNs with filters
     */
    public function getGoodsReceivedNotes(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = GoodsReceivedNote::query()
            ->with(['purchaseOrder', 'supplier', 'warehouse', 'receivedBy']);

        // Search by GRN number
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('grn_number', 'ilike', "%{$filters['search']}%")
                  ->orWhere('delivery_note_number', 'ilike', "%{$filters['search']}%")
                  ->orWhere('invoice_number', 'ilike', "%{$filters['search']}%");
            });
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->status($filters['status']);
        }

        // Purchase order filter
        if (!empty($filters['purchase_order_id'])) {
            $query->where('purchase_order_id', $filters['purchase_order_id']);
        }

        // Supplier filter
        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        // Warehouse filter
        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        // Date range
        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $query->dateRange($filters['from_date'], $filters['to_date']);
        }

        // Pending inspection filter
        if (!empty($filters['pending_inspection'])) {
            $query->pendingInspection();
        }

        return $query->latest('received_date')->paginate($perPage);
    }

    /**
     * Get GRN with relationships
     */
    public function getGoodsReceivedNote(GoodsReceivedNote $grn): GoodsReceivedNote
    {
        return $grn->load([
            'purchaseOrder',
            'supplier',
            'warehouse',
            'items.product',
            'items.unitOfMeasure',
            'items.purchaseOrderItem',
            'receivedBy',
            'inspectedBy',
            'creator',
        ]);
    }

    /**
     * Create a new GRN
     */
    public function create(array $data): GoodsReceivedNote
    {
        Log::info('Creating new GRN', [
            'purchase_order_id' => $data['purchase_order_id'],
        ]);

        DB::beginTransaction();

        try {
            $companyId = Auth::user()->company_id;
            $purchaseOrder = PurchaseOrder::findOrFail($data['purchase_order_id']);

            // Validate PO can receive goods
            if (!$purchaseOrder->canReceiveGoods()) {
                throw new BusinessException('Purchase order cannot receive goods in current status.');
            }

            // Generate GRN number if not provided
            if (empty($data['grn_number'])) {
                $data['grn_number'] = $this->generateGrnNumber();
            }

            // Create GRN
            $grn = GoodsReceivedNote::create([
                'company_id' => $companyId,
                'grn_number' => $data['grn_number'],
                'purchase_order_id' => $data['purchase_order_id'],
                'supplier_id' => $purchaseOrder->supplier_id,
                'warehouse_id' => $data['warehouse_id'] ?? $purchaseOrder->warehouse_id,
                'received_date' => $data['received_date'] ?? now(),
                'delivery_note_number' => $data['delivery_note_number'] ?? null,
                'delivery_note_date' => $data['delivery_note_date'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'status' => GoodsReceivedNote::STATUS_DRAFT,
                'requires_inspection' => $data['requires_inspection'] ?? false,
                'notes' => $data['notes'] ?? null,
                'meta_data' => $data['meta_data'] ?? null,
                'received_by' => Auth::id(),
                'created_by' => Auth::id(),
            ]);

            // Add items
            if (!empty($data['items'])) {
                $this->addItems($grn, $data['items']);
            }

            DB::commit();

            Log::info('GRN created successfully', [
                'grn_id' => $grn->id,
                'grn_number' => $grn->grn_number,
            ]);

            return $grn->fresh(['purchaseOrder', 'supplier', 'warehouse', 'items.product']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create GRN', [
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw new BusinessException("Failed to create GRN: {$e->getMessage()}");
        }
    }

    /**
     * Update GRN
     */
    public function update(GoodsReceivedNote $grn, array $data): GoodsReceivedNote
    {
        if (!$grn->canBeEdited()) {
            throw new BusinessException('GRN cannot be edited in current status.');
        }

        Log::info('Updating GRN', [
            'grn_id' => $grn->id,
            'changes' => array_keys($data),
        ]);

        DB::beginTransaction();

        try {
            $grn->update([
                'warehouse_id' => $data['warehouse_id'] ?? $grn->warehouse_id,
                'received_date' => $data['received_date'] ?? $grn->received_date,
                'delivery_note_number' => $data['delivery_note_number'] ?? $grn->delivery_note_number,
                'delivery_note_date' => $data['delivery_note_date'] ?? $grn->delivery_note_date,
                'invoice_number' => $data['invoice_number'] ?? $grn->invoice_number,
                'invoice_date' => $data['invoice_date'] ?? $grn->invoice_date,
                'requires_inspection' => $data['requires_inspection'] ?? $grn->requires_inspection,
                'notes' => $data['notes'] ?? $grn->notes,
                'meta_data' => $data['meta_data'] ?? $grn->meta_data,
            ]);

            // Update items if provided
            if (isset($data['items'])) {
                $this->syncItems($grn, $data['items']);
            }

            DB::commit();

            Log::info('GRN updated successfully', [
                'grn_id' => $grn->id,
            ]);

            return $grn->fresh(['purchaseOrder', 'supplier', 'warehouse', 'items.product']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to update GRN', [
                'grn_id' => $grn->id,
                'error' => $e->getMessage(),
            ]);

            throw new BusinessException("Failed to update GRN: {$e->getMessage()}");
        }
    }

    /**
     * Add items to GRN
     */
    public function addItems(GoodsReceivedNote $grn, array $items): void
    {
        $lineNumber = $grn->items()->max('line_number') ?? 0;

        foreach ($items as $item) {
            $lineNumber++;
            $poItem = PurchaseOrderItem::find($item['purchase_order_item_id']);

            // Validate quantity doesn't exceed remaining
            $remainingQty = $poItem->remaining_quantity ?? $poItem->quantity_ordered;
            if ($item['quantity_received'] > $remainingQty) {
                throw new BusinessException("Quantity received ({$item['quantity_received']}) exceeds remaining quantity ({$remainingQty}) for item {$poItem->product->name}.");
            }

            GoodsReceivedNoteItem::create([
                'goods_received_note_id' => $grn->id,
                'purchase_order_item_id' => $item['purchase_order_item_id'],
                'product_id' => $poItem->product_id,
                'line_number' => $lineNumber,
                'quantity_received' => $item['quantity_received'],
                'quantity_accepted' => $item['quantity_accepted'] ?? $item['quantity_received'],
                'quantity_rejected' => $item['quantity_rejected'] ?? 0,
                'uom_id' => $poItem->uom_id,
                'unit_cost' => $item['unit_cost'] ?? $poItem->unit_price,
                'lot_number' => $item['lot_number'] ?? null,
                'serial_number' => $item['serial_number'] ?? null,
                'expiry_date' => $item['expiry_date'] ?? null,
                'manufacture_date' => $item['manufacture_date'] ?? null,
                'storage_location' => $item['storage_location'] ?? null,
                'bin_location' => $item['bin_location'] ?? null,
                'inspection_status' => $item['inspection_status'] ?? null,
                'inspection_notes' => $item['inspection_notes'] ?? null,
                'rejection_reason' => $item['rejection_reason'] ?? null,
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    /**
     * Sync items (replace all)
     */
    public function syncItems(GoodsReceivedNote $grn, array $items): void
    {
        $grn->items()->delete();
        $this->addItems($grn, $items);
    }

    /**
     * Submit for inspection
     */
    public function submitForInspection(GoodsReceivedNote $grn): GoodsReceivedNote
    {
        if ($grn->status !== GoodsReceivedNote::STATUS_DRAFT) {
            throw new BusinessException('Only draft GRNs can be submitted for inspection.');
        }

        if ($grn->items()->count() === 0) {
            throw new BusinessException('Cannot submit a GRN without items.');
        }

        Log::info('Submitting GRN for inspection', [
            'grn_id' => $grn->id,
            'grn_number' => $grn->grn_number,
        ]);

        $grn->update([
            'status' => GoodsReceivedNote::STATUS_PENDING_INSPECTION,
        ]);

        return $grn->fresh();
    }

    /**
     * Record inspection results
     */
    public function recordInspection(GoodsReceivedNote $grn, array $inspectionData): GoodsReceivedNote
    {
        if ($grn->status !== GoodsReceivedNote::STATUS_PENDING_INSPECTION) {
            throw new BusinessException('GRN is not pending inspection.');
        }

        Log::info('Recording inspection results for GRN', [
            'grn_id' => $grn->id,
            'grn_number' => $grn->grn_number,
            'item_count' => count($inspectionData['items']),
        ]);

        DB::beginTransaction();

        try {
            // Update item inspection results
            foreach ($inspectionData['items'] as $itemData) {
                $item = GoodsReceivedNoteItem::findOrFail($itemData['id']);
                $item->update([
                    'quantity_accepted' => $itemData['quantity_accepted'],
                    'quantity_rejected' => $itemData['quantity_rejected'],
                    'inspection_status' => $itemData['inspection_status'],
                    'inspection_notes' => $itemData['inspection_notes'] ?? null,
                    'rejection_reason' => $itemData['rejection_reason'] ?? null,
                ]);
            }

            // Update GRN status
            $grn->update([
                'status' => GoodsReceivedNote::STATUS_INSPECTED,
                'inspected_by' => Auth::id(),
                'inspected_at' => now(),
                'inspection_notes' => $inspectionData['inspection_notes'] ?? null,
            ]);

            DB::commit();

            Log::info('Inspection results recorded successfully', [
                'grn_id' => $grn->id,
            ]);

            return $grn->fresh(['items']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to record inspection results', [
                'grn_id' => $grn->id,
                'error' => $e->getMessage(),
            ]);

            throw new BusinessException("Failed to record inspection results: {$e->getMessage()}");
        }
    }

    /**
     * Complete GRN and update stock
     */
    public function complete(GoodsReceivedNote $grn): GoodsReceivedNote
    {
        if (!$grn->canBeCompleted()) {
            throw new BusinessException('GRN cannot be completed in current status.');
        }

        Log::info('Completing GRN and updating stock', [
            'grn_id' => $grn->id,
            'grn_number' => $grn->grn_number,
        ]);

        DB::beginTransaction();

        try {
            $grn->load(['items.product', 'purchaseOrder']);

            foreach ($grn->items as $item) {
                if ($item->quantity_accepted <= 0) {
                    continue;
                }

                // Update or create stock record
                $stock = Stock::firstOrNew([
                    'company_id' => $grn->company_id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $grn->warehouse_id,
                    'lot_number' => $item->lot_number,
                    'serial_number' => $item->serial_number,
                ]);

                $quantityBefore = $stock->quantity_on_hand ?? 0;
                $stock->quantity_on_hand = $quantityBefore + $item->quantity_accepted;
                $stock->unit_cost = $item->unit_cost;
                $stock->expiry_date = $item->expiry_date;
                $stock->received_date = $grn->received_date;
                $stock->status = 'available';
                $stock->save();

                // Create stock movement
                StockMovement::create([
                    'company_id' => $grn->company_id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $grn->warehouse_id,
                    'lot_number' => $item->lot_number,
                    'serial_number' => $item->serial_number,
                    'movement_type' => 'receipt',
                    'transaction_type' => 'purchase_order',
                    'reference_type' => GoodsReceivedNote::class,
                    'reference_id' => $grn->id,
                    'reference_number' => $grn->grn_number,
                    'quantity' => $item->quantity_accepted,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $stock->quantity_on_hand,
                    'unit_cost' => $item->unit_cost,
                    'total_cost' => $item->quantity_accepted * $item->unit_cost,
                    'notes' => "Received from PO: {$grn->purchaseOrder->order_number}",
                    'created_by' => Auth::id(),
                    'movement_date' => $grn->received_date,
                ]);

                // Update PO item received quantity
                $poItem = $item->purchaseOrderItem;
                $poItem->update([
                    'quantity_received' => $poItem->quantity_received + $item->quantity_accepted,
                    'actual_delivery_date' => $grn->received_date,
                ]);
            }

            // Update GRN status
            $grn->update([
                'status' => GoodsReceivedNote::STATUS_COMPLETED,
            ]);

            // Update PO status
            $this->updatePurchaseOrderStatus($grn->purchaseOrder);

            DB::commit();

            Log::info('GRN completed successfully', [
                'grn_id' => $grn->id,
                'grn_number' => $grn->grn_number,
            ]);

            return $grn->fresh();

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to complete GRN', [
                'grn_id' => $grn->id,
                'error' => $e->getMessage(),
            ]);

            throw new BusinessException("Failed to complete GRN: {$e->getMessage()}");
        }
    }

    /**
     * Update purchase order status based on received quantities
     */
    protected function updatePurchaseOrderStatus(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->load('items');

        $totalOrdered = $purchaseOrder->items->sum('quantity_ordered');
        $totalReceived = $purchaseOrder->items->sum('quantity_received');
        $totalCancelled = $purchaseOrder->items->sum('quantity_cancelled');

        if ($totalReceived <= 0) {
            return; // No change needed
        }

        $effectiveOrdered = $totalOrdered - $totalCancelled;

        if ($totalReceived >= $effectiveOrdered) {
            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_RECEIVED,
                'actual_delivery_date' => now(),
            ]);
        } else {
            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ]);
        }
    }

    /**
     * Cancel GRN
     */
    public function cancel(GoodsReceivedNote $grn, ?string $reason = null): GoodsReceivedNote
    {
        if ($grn->status === GoodsReceivedNote::STATUS_COMPLETED) {
            throw new BusinessException('Completed GRNs cannot be cancelled.');
        }

        Log::info('Cancelling GRN', [
            'grn_id' => $grn->id,
            'grn_number' => $grn->grn_number,
            'reason' => $reason,
        ]);

        $grn->update([
            'status' => GoodsReceivedNote::STATUS_CANCELLED,
            'notes' => $reason ? "Cancelled: {$reason}\n" . $grn->notes : $grn->notes,
        ]);

        return $grn->fresh();
    }

    /**
     * Delete GRN (soft delete)
     */
    public function delete(GoodsReceivedNote $grn): bool
    {
        if (!in_array($grn->status, [GoodsReceivedNote::STATUS_DRAFT, GoodsReceivedNote::STATUS_CANCELLED])) {
            throw new BusinessException('Only draft or cancelled GRNs can be deleted.');
        }

        Log::info('Deleting GRN', [
            'grn_id' => $grn->id,
            'grn_number' => $grn->grn_number,
        ]);

        return $grn->delete();
    }

    /**
     * Generate GRN number
     */
    public function generateGrnNumber(): string
    {
        $companyId = Auth::user()->company_id;
        $year = now()->format('Y');
        $prefix = "GRN-{$year}-";

        // Include soft-deleted records to avoid duplicate GRN numbers
        $lastGrn = GoodsReceivedNote::withTrashed()
            ->where('company_id', $companyId)
            ->where('grn_number', 'like', "{$prefix}%")
            ->orderByRaw("CAST(SUBSTRING(grn_number FROM '[0-9]+$') AS INTEGER) DESC")
            ->first();

        if ($lastGrn && preg_match('/(\d+)$/', $lastGrn->grn_number, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get GRNs for a purchase order
     */
    public function getGrnsForPurchaseOrder(int $purchaseOrderId): Collection
    {
        return GoodsReceivedNote::where('purchase_order_id', $purchaseOrderId)
            ->with(['items.product', 'receivedBy'])
            ->get();
    }

    /**
     * Get pending inspection GRNs
     */
    public function getPendingInspection(): Collection
    {
        return GoodsReceivedNote::pendingInspection()
            ->with(['purchaseOrder', 'supplier', 'items'])
            ->get();
    }
}
