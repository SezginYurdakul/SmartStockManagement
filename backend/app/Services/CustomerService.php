<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CustomerService
{
    /**
     * Get paginated customers with filters
     */
    public function getCustomers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Customer::query()
            ->with(['customerGroup']);

        // Search
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'ilike', "%{$filters['search']}%")
                  ->orWhere('customer_code', 'ilike', "%{$filters['search']}%")
                  ->orWhere('email', 'ilike', "%{$filters['search']}%")
                  ->orWhere('tax_id', 'ilike', "%{$filters['search']}%");
            });
        }

        // Customer group filter
        if (!empty($filters['customer_group_id'])) {
            $query->where('customer_group_id', $filters['customer_group_id']);
        }

        // Active filter
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Get all active customers for dropdown
     */
    public function getActiveCustomers(): Collection
    {
        return Customer::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'customer_code', 'customer_group_id']);
    }

    /**
     * Get single customer with relations
     */
    public function getCustomer(Customer $customer): Customer
    {
        return $customer->load(['customerGroup', 'salesOrders' => function ($q) {
            $q->latest()->limit(10);
        }]);
    }

    /**
     * Create new customer
     */
    public function create(array $data): Customer
    {
        Log::info('Creating customer', [
            'name' => $data['name'],
            'code' => $data['code'] ?? $data['customer_code'] ?? null,
        ]);

        DB::beginTransaction();

        try {
            $companyId = Auth::user()->company_id;

            // Generate code if not provided
            $customerCode = $data['customer_code'] ?? $data['code'] ?? null;
            if (empty($customerCode)) {
                $customerCode = $this->generateCustomerCode();
            }

            $customer = Customer::create([
                'company_id' => $companyId,
                'customer_group_id' => $data['customer_group_id'] ?? null,
                'customer_code' => $customerCode,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'tax_id' => $data['tax_id'] ?? $data['tax_number'] ?? null,
                'address' => $data['address'] ?? $data['billing_address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'] ?? null,
                'contact_person' => $data['contact_person'] ?? null,
                'payment_terms_days' => $data['payment_terms_days'] ?? 30,
                'credit_limit' => $data['credit_limit'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            Log::info('Customer created', [
                'customer_id' => $customer->id,
                'customer_code' => $customer->customer_code,
            ]);

            return $customer->load('customerGroup');

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create customer', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update customer
     */
    public function update(Customer $customer, array $data): Customer
    {
        Log::info('Updating customer', [
            'customer_id' => $customer->id,
            'changes' => array_keys($data),
        ]);

        $customer->update([
            'customer_group_id' => $data['customer_group_id'] ?? $customer->customer_group_id,
            'name' => $data['name'] ?? $customer->name,
            'email' => $data['email'] ?? $customer->email,
            'phone' => $data['phone'] ?? $customer->phone,
            'tax_number' => $data['tax_number'] ?? $customer->tax_number,
            'billing_address' => $data['billing_address'] ?? $customer->billing_address,
            'shipping_address' => $data['shipping_address'] ?? $customer->shipping_address,
            'city' => $data['city'] ?? $customer->city,
            'state' => $data['state'] ?? $customer->state,
            'postal_code' => $data['postal_code'] ?? $customer->postal_code,
            'country' => $data['country'] ?? $customer->country,
            'contact_person' => $data['contact_person'] ?? $customer->contact_person,
            'payment_terms_days' => $data['payment_terms_days'] ?? $customer->payment_terms_days,
            'credit_limit' => $data['credit_limit'] ?? $customer->credit_limit,
            'notes' => $data['notes'] ?? $customer->notes,
            'is_active' => $data['is_active'] ?? $customer->is_active,
        ]);

        return $customer->fresh('customerGroup');
    }

    /**
     * Delete customer (soft delete)
     */
    public function delete(Customer $customer): bool
    {
        // Check for pending orders
        if ($customer->salesOrders()->whereNotIn('status', ['delivered', 'cancelled'])->exists()) {
            throw new BusinessException('Cannot delete customer with pending orders.');
        }

        Log::info('Deleting customer', [
            'customer_id' => $customer->id,
            'customer_code' => $customer->customer_code,
        ]);

        return $customer->delete();
    }

    /**
     * Generate customer code
     */
    public function generateCustomerCode(): string
    {
        $companyId = Auth::user()->company_id;
        $prefix = 'CUS-';

        $lastCustomer = Customer::withTrashed()
            ->where('company_id', $companyId)
            ->where('customer_code', 'like', "{$prefix}%")
            ->orderByRaw("CAST(SUBSTRING(customer_code FROM '[0-9]+$') AS INTEGER) DESC")
            ->first();

        if ($lastCustomer && preg_match('/(\d+)$/', $lastCustomer->customer_code, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get customer statistics
     */
    public function getStatistics(Customer $customer): array
    {
        $orders = $customer->salesOrders();

        return [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->where('status', 'delivered')->sum('total_amount'),
            'pending_orders' => $orders->whereNotIn('status', ['delivered', 'cancelled'])->count(),
            'last_order_date' => $orders->latest()->value('created_at'),
        ];
    }
}
