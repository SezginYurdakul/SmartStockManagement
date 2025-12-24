<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class SupplierService
{
    /**
     * Get paginated suppliers with filters
     */
    public function getSuppliers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Supplier::query()
            ->with(['creator']);

        // Search
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Active filter
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        // Country filter
        if (!empty($filters['country'])) {
            $query->byCountry($filters['country']);
        }

        // Rating filter
        if (!empty($filters['min_rating'])) {
            $query->byRating((int) $filters['min_rating']);
        }

        // Currency filter
        if (!empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get all active suppliers for dropdowns
     */
    public function getActiveSuppliers(): Collection
    {
        return Supplier::active()
            ->orderBy('name')
            ->get(['id', 'supplier_code', 'name', 'currency']);
    }

    /**
     * Get supplier with relationships
     */
    public function getSupplier(Supplier $supplier): Supplier
    {
        return $supplier->load(['creator', 'products']);
    }

    /**
     * Create a new supplier
     */
    public function create(array $data): Supplier
    {
        Log::info('Creating new supplier', [
            'name' => $data['name'] ?? null,
            'supplier_code' => $data['supplier_code'] ?? null,
        ]);

        try {
            $data['company_id'] = Auth::user()->company_id;
            $data['created_by'] = Auth::id();

            $supplier = Supplier::create($data);

            Log::info('Supplier created successfully', [
                'supplier_id' => $supplier->id,
                'supplier_code' => $supplier->supplier_code,
            ]);

            return $supplier;

        } catch (Exception $e) {
            Log::error('Failed to create supplier', [
                'name' => $data['name'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw new BusinessException("Failed to create supplier: {$e->getMessage()}");
        }
    }

    /**
     * Update supplier
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        Log::info('Updating supplier', [
            'supplier_id' => $supplier->id,
            'changes' => array_keys($data),
        ]);

        try {
            $supplier->update($data);

            Log::info('Supplier updated successfully', [
                'supplier_id' => $supplier->id,
            ]);

            return $supplier->fresh();

        } catch (Exception $e) {
            Log::error('Failed to update supplier', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);

            throw new BusinessException("Failed to update supplier: {$e->getMessage()}");
        }
    }

    /**
     * Delete supplier (soft delete)
     */
    public function delete(Supplier $supplier): bool
    {
        // Check if supplier has pending orders
        $pendingOrders = $supplier->purchaseOrders()
            ->whereIn('status', ['pending_approval', 'approved', 'sent', 'partially_received'])
            ->count();

        if ($pendingOrders > 0) {
            throw new BusinessException("Cannot delete supplier with {$pendingOrders} pending orders.");
        }

        Log::info('Deleting supplier', [
            'supplier_id' => $supplier->id,
            'supplier_code' => $supplier->supplier_code,
        ]);

        return $supplier->delete();
    }

    /**
     * Toggle supplier active status
     */
    public function toggleActive(Supplier $supplier): Supplier
    {
        $newStatus = !$supplier->is_active;

        Log::info('Toggling supplier active status', [
            'supplier_id' => $supplier->id,
            'new_status' => $newStatus,
        ]);

        $supplier->update(['is_active' => $newStatus]);

        return $supplier->fresh();
    }

    /**
     * Attach products to supplier
     */
    public function attachProducts(Supplier $supplier, array $products): void
    {
        Log::info('Attaching products to supplier', [
            'supplier_id' => $supplier->id,
            'product_count' => count($products),
        ]);

        foreach ($products as $product) {
            $supplier->products()->syncWithoutDetaching([
                $product['product_id'] => [
                    'supplier_sku' => $product['supplier_sku'] ?? null,
                    'unit_price' => $product['unit_price'] ?? null,
                    'currency' => $product['currency'] ?? $supplier->currency,
                    'minimum_order_qty' => $product['minimum_order_qty'] ?? null,
                    'lead_time_days' => $product['lead_time_days'] ?? null,
                    'is_preferred' => $product['is_preferred'] ?? false,
                    'is_active' => $product['is_active'] ?? true,
                ],
            ]);
        }
    }

    /**
     * Update supplier product
     */
    public function updateProduct(Supplier $supplier, int $productId, array $data): void
    {
        Log::info('Updating supplier product', [
            'supplier_id' => $supplier->id,
            'product_id' => $productId,
        ]);

        $supplier->products()->updateExistingPivot($productId, $data);
    }

    /**
     * Detach product from supplier
     */
    public function detachProduct(Supplier $supplier, int $productId): void
    {
        Log::info('Detaching product from supplier', [
            'supplier_id' => $supplier->id,
            'product_id' => $productId,
        ]);

        $supplier->products()->detach($productId);
    }

    /**
     * Get suppliers for a specific product
     */
    public function getSuppliersForProduct(int $productId): Collection
    {
        return Supplier::active()
            ->whereHas('products', function ($query) use ($productId) {
                $query->where('product_id', $productId)
                    ->where('supplier_products.is_active', true);
            })
            ->with(['products' => function ($query) use ($productId) {
                $query->where('product_id', $productId);
            }])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get preferred supplier for a product
     */
    public function getPreferredSupplier(int $productId): ?Supplier
    {
        return Supplier::active()
            ->whereHas('products', function ($query) use ($productId) {
                $query->where('product_id', $productId)
                    ->where('supplier_products.is_preferred', true)
                    ->where('supplier_products.is_active', true);
            })
            ->first();
    }

    /**
     * Get supplier statistics
     */
    public function getStatistics(Supplier $supplier): array
    {
        $orders = $supplier->purchaseOrders();

        return [
            'total_orders' => $orders->count(),
            'total_amount' => $orders->whereNotIn('status', ['cancelled', 'draft'])->sum('total_amount'),
            'pending_orders' => $orders->clone()->whereIn('status', ['pending_approval', 'approved', 'sent'])->count(),
            'received_orders' => $orders->clone()->whereIn('status', ['received', 'partially_received'])->count(),
            'products_count' => $supplier->products()->count(),
            'average_lead_time' => $supplier->lead_time_days,
        ];
    }

    /**
     * Generate next supplier code
     */
    public function generateSupplierCode(): string
    {
        $companyId = Auth::user()->company_id;

        // Include soft-deleted records to avoid duplicate codes
        $lastSupplier = Supplier::withTrashed()
            ->where('company_id', $companyId)
            ->orderByRaw("CAST(SUBSTRING(supplier_code FROM '[0-9]+') AS INTEGER) DESC")
            ->first();

        if ($lastSupplier && preg_match('/(\d+)/', $lastSupplier->supplier_code, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return 'SUP-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
