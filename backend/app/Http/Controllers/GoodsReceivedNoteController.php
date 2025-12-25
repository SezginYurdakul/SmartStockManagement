<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceivedNote;
use App\Services\GoodsReceivedNoteService;
use App\Http\Resources\GoodsReceivedNoteResource;
use App\Http\Resources\GoodsReceivedNoteListResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceivedNoteController extends Controller
{
    public function __construct(
        protected GoodsReceivedNoteService $grnService
    ) {}

    /**
     * Display a listing of GRNs
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'status',
            'purchase_order_id',
            'supplier_id',
            'warehouse_id',
            'from_date',
            'to_date',
            'pending_inspection',
        ]);
        $perPage = $request->get('per_page', 15);

        $grns = $this->grnService->getGoodsReceivedNotes($filters, $perPage);

        return GoodsReceivedNoteListResource::collection($grns);
    }

    /**
     * Store a newly created GRN
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grn_number' => 'nullable|string|max:50',
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'received_date' => 'nullable|date',
            'delivery_note_number' => 'nullable|string|max:100',
            'delivery_note_date' => 'nullable|date',
            'invoice_number' => 'nullable|string|max:100',
            'invoice_date' => 'nullable|date',
            'requires_inspection' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'meta_data' => 'nullable|array',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0.001',
            'items.*.quantity_accepted' => 'nullable|numeric|min:0',
            'items.*.quantity_rejected' => 'nullable|numeric|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.lot_number' => 'nullable|string|max:100',
            'items.*.serial_number' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.manufacture_date' => 'nullable|date',
            'items.*.storage_location' => 'nullable|string|max:100',
            'items.*.bin_location' => 'nullable|string|max:50',
            'items.*.notes' => 'nullable|string',
        ]);

        $grn = $this->grnService->create($validated);

        return response()->json([
            'message' => 'Goods Received Note created successfully',
            'data' => GoodsReceivedNoteResource::make($grn),
        ], 201);
    }

    /**
     * Display the specified GRN
     */
    public function show(GoodsReceivedNote $goodsReceivedNote): JsonResource
    {
        return GoodsReceivedNoteResource::make(
            $this->grnService->getGoodsReceivedNote($goodsReceivedNote)
        );
    }

    /**
     * Update the specified GRN
     */
    public function update(Request $request, GoodsReceivedNote $goodsReceivedNote): JsonResource
    {
        $validated = $request->validate([
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'received_date' => 'nullable|date',
            'delivery_note_number' => 'nullable|string|max:100',
            'delivery_note_date' => 'nullable|date',
            'invoice_number' => 'nullable|string|max:100',
            'invoice_date' => 'nullable|date',
            'requires_inspection' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'meta_data' => 'nullable|array',
            'items' => 'sometimes|array|min:1',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0.001',
            'items.*.quantity_accepted' => 'nullable|numeric|min:0',
            'items.*.quantity_rejected' => 'nullable|numeric|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.lot_number' => 'nullable|string|max:100',
            'items.*.serial_number' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.manufacture_date' => 'nullable|date',
            'items.*.storage_location' => 'nullable|string|max:100',
            'items.*.bin_location' => 'nullable|string|max:50',
            'items.*.notes' => 'nullable|string',
        ]);

        $grn = $this->grnService->update($goodsReceivedNote, $validated);

        return GoodsReceivedNoteResource::make($grn)
            ->additional(['message' => 'Goods Received Note updated successfully']);
    }

    /**
     * Remove the specified GRN
     */
    public function destroy(GoodsReceivedNote $goodsReceivedNote): JsonResponse
    {
        $this->grnService->delete($goodsReceivedNote);

        return response()->json([
            'message' => 'Goods Received Note deleted successfully',
        ]);
    }

    /**
     * Submit for inspection
     */
    public function submitForInspection(GoodsReceivedNote $goodsReceivedNote): JsonResponse
    {
        $grn = $this->grnService->submitForInspection($goodsReceivedNote);

        return response()->json([
            'message' => 'GRN submitted for inspection',
            'data' => GoodsReceivedNoteResource::make($grn),
        ]);
    }

    /**
     * Record inspection results
     */
    public function recordInspection(Request $request, GoodsReceivedNote $goodsReceivedNote): JsonResponse
    {
        $validated = $request->validate([
            'inspection_notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:goods_received_note_items,id',
            'items.*.quantity_accepted' => 'required|numeric|min:0',
            'items.*.quantity_rejected' => 'required|numeric|min:0',
            'items.*.inspection_status' => 'required|in:pending,passed,failed,partial',
            'items.*.inspection_notes' => 'nullable|string',
            'items.*.rejection_reason' => 'nullable|string|max:255',
        ]);

        $grn = $this->grnService->recordInspection($goodsReceivedNote, $validated);

        return response()->json([
            'message' => 'Inspection results recorded',
            'data' => GoodsReceivedNoteResource::make($grn),
        ]);
    }

    /**
     * Complete GRN and update stock
     */
    public function complete(GoodsReceivedNote $goodsReceivedNote): JsonResponse
    {
        $grn = $this->grnService->complete($goodsReceivedNote);

        return response()->json([
            'message' => 'GRN completed and stock updated',
            'data' => GoodsReceivedNoteResource::make($grn),
        ]);
    }

    /**
     * Cancel GRN
     */
    public function cancel(Request $request, GoodsReceivedNote $goodsReceivedNote): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $grn = $this->grnService->cancel($goodsReceivedNote, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'GRN cancelled',
            'data' => GoodsReceivedNoteResource::make($grn),
        ]);
    }

    /**
     * Get GRNs for a purchase order
     */
    public function forPurchaseOrder(int $purchaseOrderId): JsonResponse
    {
        $grns = $this->grnService->getGrnsForPurchaseOrder($purchaseOrderId);

        return response()->json([
            'data' => GoodsReceivedNoteListResource::collection($grns),
        ]);
    }

    /**
     * Get pending inspection GRNs
     */
    public function pendingInspection(): JsonResponse
    {
        $grns = $this->grnService->getPendingInspection();

        return response()->json([
            'data' => GoodsReceivedNoteListResource::collection($grns),
        ]);
    }
}
