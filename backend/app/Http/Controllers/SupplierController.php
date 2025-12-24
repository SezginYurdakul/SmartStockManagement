<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\SupplierService;
use App\Http\Resources\SupplierResource;
use App\Http\Resources\SupplierListResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function __construct(
        protected SupplierService $supplierService
    ) {}

    /**
     * Display a listing of suppliers
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'is_active',
            'country',
            'min_rating',
            'currency',
        ]);
        $perPage = $request->get('per_page', 15);

        $suppliers = $this->supplierService->getSuppliers($filters, $perPage);

        return SupplierListResource::collection($suppliers);
    }

    /**
     * Get all active suppliers for dropdowns
     */
    public function list(): JsonResponse
    {
        $suppliers = $this->supplierService->getActiveSuppliers();

        return response()->json([
            'data' => $suppliers,
        ]);
    }

    /**
     * Store a newly created supplier
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_code' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'contact_person' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'credit_limit' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:100',
            'bank_iban' => 'nullable|string|max:50',
            'bank_swift' => 'nullable|string|max:20',
            'lead_time_days' => 'nullable|integer|min:0',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'shipping_method' => 'nullable|string|max:100',
            'rating' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string',
            'meta_data' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        // Generate supplier code if not provided
        if (empty($validated['supplier_code'])) {
            $validated['supplier_code'] = $this->supplierService->generateSupplierCode();
        }

        $supplier = $this->supplierService->create($validated);

        return response()->json([
            'message' => 'Supplier created successfully',
            'data' => new SupplierResource($supplier),
        ], 201);
    }

    /**
     * Display the specified supplier
     */
    public function show(Supplier $supplier): JsonResource
    {
        return new SupplierResource(
            $this->supplierService->getSupplier($supplier)
        );
    }

    /**
     * Update the specified supplier
     */
    public function update(Request $request, Supplier $supplier): JsonResource
    {
        $validated = $request->validate([
            'supplier_code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('suppliers')->where(function ($query) use ($supplier) {
                    return $query->where('company_id', $supplier->company_id);
                })->ignore($supplier->id),
            ],
            'name' => 'sometimes|required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'contact_person' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'credit_limit' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:100',
            'bank_iban' => 'nullable|string|max:50',
            'bank_swift' => 'nullable|string|max:20',
            'lead_time_days' => 'nullable|integer|min:0',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'shipping_method' => 'nullable|string|max:100',
            'rating' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string',
            'meta_data' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $supplier = $this->supplierService->update($supplier, $validated);

        return (new SupplierResource($supplier))
            ->additional(['message' => 'Supplier updated successfully']);
    }

    /**
     * Remove the specified supplier
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->supplierService->delete($supplier);

        return response()->json([
            'message' => 'Supplier deleted successfully',
        ]);
    }

    /**
     * Toggle supplier active status
     */
    public function toggleActive(Supplier $supplier): JsonResponse
    {
        $supplier = $this->supplierService->toggleActive($supplier);

        return response()->json([
            'message' => 'Supplier status updated successfully',
            'data' => new SupplierResource($supplier),
        ]);
    }

    /**
     * Get supplier statistics
     */
    public function statistics(Supplier $supplier): JsonResponse
    {
        $stats = $this->supplierService->getStatistics($supplier);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Attach products to supplier
     */
    public function attachProducts(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.supplier_sku' => 'nullable|string|max:100',
            'products.*.unit_price' => 'nullable|numeric|min:0',
            'products.*.currency' => 'nullable|string|size:3',
            'products.*.minimum_order_qty' => 'nullable|numeric|min:0',
            'products.*.lead_time_days' => 'nullable|integer|min:0',
            'products.*.is_preferred' => 'nullable|boolean',
            'products.*.is_active' => 'nullable|boolean',
        ]);

        $this->supplierService->attachProducts($supplier, $validated['products']);

        return response()->json([
            'message' => count($validated['products']) . ' products attached successfully',
            'data' => new SupplierResource($supplier->fresh(['products'])),
        ]);
    }

    /**
     * Update a supplier product
     */
    public function updateProduct(Request $request, Supplier $supplier, int $productId): JsonResponse
    {
        $validated = $request->validate([
            'supplier_sku' => 'nullable|string|max:100',
            'unit_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'minimum_order_qty' => 'nullable|numeric|min:0',
            'lead_time_days' => 'nullable|integer|min:0',
            'is_preferred' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $this->supplierService->updateProduct($supplier, $productId, $validated);

        return response()->json([
            'message' => 'Supplier product updated successfully',
        ]);
    }

    /**
     * Detach a product from supplier
     */
    public function detachProduct(Supplier $supplier, int $productId): JsonResponse
    {
        $this->supplierService->detachProduct($supplier, $productId);

        return response()->json([
            'message' => 'Product detached from supplier successfully',
        ]);
    }

    /**
     * Get suppliers for a specific product
     */
    public function forProduct(int $productId): JsonResponse
    {
        $suppliers = $this->supplierService->getSuppliersForProduct($productId);

        return response()->json([
            'data' => SupplierListResource::collection($suppliers),
        ]);
    }
}
