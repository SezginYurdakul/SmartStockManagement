<?php

namespace App\Http\Controllers;

use App\Models\CustomerGroup;
use App\Services\CustomerGroupService;
use App\Services\CustomerGroupPriceService;
use App\Http\Resources\CustomerGroupResource;
use App\Http\Resources\CustomerGroupListResource;
use App\Http\Resources\CustomerGroupPriceResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class CustomerGroupController extends Controller
{
    public function __construct(
        protected CustomerGroupService $customerGroupService,
        protected CustomerGroupPriceService $priceService
    ) {}

    /**
     * Display a listing of customer groups
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'is_active']);
        $perPage = $request->get('per_page', 15);

        $customerGroups = $this->customerGroupService->getCustomerGroups($filters, $perPage);

        return CustomerGroupListResource::collection($customerGroups);
    }

    /**
     * Get all active customer groups for dropdowns
     */
    public function list(): JsonResponse
    {
        $groups = $this->customerGroupService->getActiveGroups();

        return response()->json([
            'data' => $groups,
        ]);
    }

    /**
     * Store a newly created customer group
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'credit_limit' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $customerGroup = $this->customerGroupService->create($validated);

        return response()->json([
            'message' => 'Customer group created successfully',
            'data' => CustomerGroupResource::make($customerGroup),
        ], 201);
    }

    /**
     * Display the specified customer group
     */
    public function show(CustomerGroup $customerGroup): JsonResource
    {
        return CustomerGroupResource::make(
            $this->customerGroupService->getCustomerGroup($customerGroup)
        );
    }

    /**
     * Update the specified customer group
     */
    public function update(Request $request, CustomerGroup $customerGroup): JsonResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('customer_groups')->where(function ($query) use ($customerGroup) {
                    return $query->where('company_id', $customerGroup->company_id);
                })->ignore($customerGroup->id),
            ],
            'description' => 'nullable|string',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'credit_limit' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $customerGroup = $this->customerGroupService->update($customerGroup, $validated);

        return CustomerGroupResource::make($customerGroup)
            ->additional(['message' => 'Customer group updated successfully']);
    }

    /**
     * Remove the specified customer group
     */
    public function destroy(CustomerGroup $customerGroup): JsonResponse
    {
        $this->customerGroupService->delete($customerGroup);

        return response()->json([
            'message' => 'Customer group deleted successfully',
        ]);
    }

    /**
     * Get prices for a customer group
     */
    public function prices(Request $request, CustomerGroup $customerGroup): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'active_only']);
        $perPage = $request->get('per_page', 15);

        $prices = $this->priceService->getPricesForGroup($customerGroup, $filters, $perPage);

        return CustomerGroupPriceResource::collection($prices);
    }

    /**
     * Set price for a product in customer group
     */
    public function setPrice(Request $request, CustomerGroup $customerGroup): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'price' => 'required|numeric|min:0',
            'currency_id' => 'nullable|exists:currencies,id',
            'min_quantity' => 'nullable|numeric|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after:valid_from',
            'is_active' => 'boolean',
        ]);

        $validated['customer_group_id'] = $customerGroup->id;
        $price = $this->priceService->setPrice($validated);

        return response()->json([
            'message' => 'Price set successfully',
            'data' => CustomerGroupPriceResource::make($price),
        ]);
    }

    /**
     * Bulk set prices for customer group
     */
    public function bulkSetPrices(Request $request, CustomerGroup $customerGroup): JsonResponse
    {
        $validated = $request->validate([
            'prices' => 'required|array|min:1',
            'prices.*.product_id' => 'required|exists:products,id',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.currency_id' => 'nullable|exists:currencies,id',
            'prices.*.min_quantity' => 'nullable|numeric|min:1',
            'prices.*.valid_from' => 'nullable|date',
            'prices.*.valid_until' => 'nullable|date',
            'prices.*.is_active' => 'boolean',
        ]);

        $prices = $this->priceService->bulkSetPrices($customerGroup, $validated['prices']);

        return response()->json([
            'message' => count($prices) . ' prices set successfully',
        ]);
    }

    /**
     * Delete a group price
     */
    public function deletePrice(CustomerGroup $customerGroup, int $priceId): JsonResponse
    {
        $price = $customerGroup->groupPrices()->findOrFail($priceId);
        $this->priceService->delete($price);

        return response()->json([
            'message' => 'Price deleted successfully',
        ]);
    }
}
