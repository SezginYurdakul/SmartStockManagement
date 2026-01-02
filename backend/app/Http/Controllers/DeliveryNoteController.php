<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Models\SalesOrder;
use App\Services\DeliveryNoteService;
use App\Http\Resources\DeliveryNoteResource;
use App\Http\Resources\DeliveryNoteListResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteController extends Controller
{
    public function __construct(
        protected DeliveryNoteService $deliveryNoteService
    ) {}

    /**
     * Display a listing of delivery notes
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'status',
            'sales_order_id',
            'warehouse_id',
            'from_date',
            'to_date',
        ]);
        $perPage = $request->get('per_page', 15);

        $deliveryNotes = $this->deliveryNoteService->getDeliveryNotes($filters, $perPage);

        return DeliveryNoteListResource::collection($deliveryNotes);
    }

    /**
     * Store a newly created delivery note
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'delivery_date' => 'nullable|date',
            'shipping_address' => 'nullable|string',
            'carrier' => 'nullable|string|max:255',
            'tracking_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.sales_order_item_id' => 'required|exists:sales_order_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.lot_number' => 'nullable|string|max:100',
            'items.*.serial_numbers' => 'nullable|array',
            'items.*.notes' => 'nullable|string',
        ]);

        $salesOrder = SalesOrder::findOrFail($validated['sales_order_id']);
        $deliveryNote = $this->deliveryNoteService->createFromSalesOrder($salesOrder, $validated);

        return response()->json([
            'message' => 'Delivery note created successfully',
            'data' => DeliveryNoteResource::make($deliveryNote),
        ], 201);
    }

    /**
     * Display the specified delivery note
     */
    public function show(DeliveryNote $deliveryNote): JsonResource
    {
        return DeliveryNoteResource::make(
            $this->deliveryNoteService->getDeliveryNote($deliveryNote)
        );
    }

    /**
     * Update the specified delivery note
     */
    public function update(Request $request, DeliveryNote $deliveryNote): JsonResource
    {
        $validated = $request->validate([
            'delivery_date' => 'nullable|date',
            'shipping_address' => 'nullable|string',
            'carrier' => 'nullable|string|max:255',
            'tracking_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $deliveryNote = $this->deliveryNoteService->update($deliveryNote, $validated);

        return DeliveryNoteResource::make($deliveryNote)
            ->additional(['message' => 'Delivery note updated successfully']);
    }

    /**
     * Remove the specified delivery note
     */
    public function destroy(DeliveryNote $deliveryNote): JsonResponse
    {
        $this->deliveryNoteService->delete($deliveryNote);

        return response()->json([
            'message' => 'Delivery note deleted successfully',
        ]);
    }

    /**
     * Confirm delivery note (ready for shipping)
     */
    public function confirm(DeliveryNote $deliveryNote): JsonResponse
    {
        $deliveryNote = $this->deliveryNoteService->confirm($deliveryNote);

        return response()->json([
            'message' => 'Delivery note confirmed',
            'data' => DeliveryNoteResource::make($deliveryNote),
        ]);
    }

    /**
     * Ship delivery note (deduct stock)
     */
    public function ship(DeliveryNote $deliveryNote): JsonResponse
    {
        $deliveryNote = $this->deliveryNoteService->ship($deliveryNote);

        return response()->json([
            'message' => 'Delivery note shipped. Stock has been deducted.',
            'data' => DeliveryNoteResource::make($deliveryNote),
        ]);
    }

    /**
     * Mark delivery note as delivered
     */
    public function markAsDelivered(DeliveryNote $deliveryNote): JsonResponse
    {
        $deliveryNote = $this->deliveryNoteService->markAsDelivered($deliveryNote);

        return response()->json([
            'message' => 'Delivery note marked as delivered',
            'data' => DeliveryNoteResource::make($deliveryNote),
        ]);
    }

    /**
     * Cancel delivery note
     */
    public function cancel(Request $request, DeliveryNote $deliveryNote): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $deliveryNote = $this->deliveryNoteService->cancel($deliveryNote, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Delivery note cancelled',
        ]);
    }

    /**
     * Get available statuses
     */
    public function statuses(): JsonResponse
    {
        $statuses = $this->deliveryNoteService->getStatuses();

        return response()->json([
            'data' => $statuses,
        ]);
    }

    /**
     * Get delivery notes for a sales order
     */
    public function forSalesOrder(SalesOrder $salesOrder): JsonResponse
    {
        $deliveryNotes = $salesOrder->deliveryNotes()
            ->with(['warehouse', 'items.product'])
            ->get();

        return response()->json([
            'data' => DeliveryNoteListResource::collection($deliveryNotes),
        ]);
    }
}
