<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerService;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\CustomerListResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    /**
     * Display a listing of customers
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'customer_group_id', 'is_active']);
        $perPage = $request->get('per_page', 15);

        $customers = $this->customerService->getCustomers($filters, $perPage);

        return CustomerListResource::collection($customers);
    }

    /**
     * Get all active customers for dropdowns
     */
    public function list(): JsonResponse
    {
        $customers = $this->customerService->getActiveCustomers();

        return response()->json([
            'data' => $customers,
        ]);
    }

    /**
     * Store a newly created customer
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'code' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:50',
            'billing_address' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $customer = $this->customerService->create($validated);

        return response()->json([
            'message' => 'Customer created successfully',
            'data' => CustomerResource::make($customer),
        ], 201);
    }

    /**
     * Display the specified customer
     */
    public function show(Customer $customer): JsonResource
    {
        return CustomerResource::make(
            $this->customerService->getCustomer($customer)
        );
    }

    /**
     * Update the specified customer
     */
    public function update(Request $request, Customer $customer): JsonResource
    {
        $validated = $request->validate([
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('customers')->where(function ($query) use ($customer) {
                    return $query->where('company_id', $customer->company_id);
                })->ignore($customer->id),
            ],
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:50',
            'billing_address' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $customer = $this->customerService->update($customer, $validated);

        return CustomerResource::make($customer)
            ->additional(['message' => 'Customer updated successfully']);
    }

    /**
     * Remove the specified customer
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $this->customerService->delete($customer);

        return response()->json([
            'message' => 'Customer deleted successfully',
        ]);
    }

    /**
     * Get customer statistics
     */
    public function statistics(Customer $customer): JsonResponse
    {
        $stats = $this->customerService->getStatistics($customer);

        return response()->json([
            'data' => $stats,
        ]);
    }
}
