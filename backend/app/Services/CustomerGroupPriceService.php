<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\CustomerGroup;
use App\Models\CustomerGroupPrice;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CustomerGroupPriceService
{
    /**
     * Get prices for a customer group
     */
    public function getPricesForGroup(CustomerGroup $customerGroup, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CustomerGroupPrice::where('customer_group_id', $customerGroup->id)
            ->with(['product', 'currency']);

        // Search by product
        if (!empty($filters['search'])) {
            $query->whereHas('product', function ($q) use ($filters) {
                $q->where('name', 'ilike', "%{$filters['search']}%")
                  ->orWhere('sku', 'ilike', "%{$filters['search']}%");
            });
        }

        // Active only
        if (!empty($filters['active_only'])) {
            $query->active();
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get price for specific product and customer group
     */
    public function getPriceForProduct(int $productId, int $customerGroupId, float $quantity = 1): ?CustomerGroupPrice
    {
        return CustomerGroupPrice::where('product_id', $productId)
            ->where('customer_group_id', $customerGroupId)
            ->where('min_quantity', '<=', $quantity)
            ->active()
            ->orderBy('min_quantity', 'desc')
            ->first();
    }

    /**
     * Calculate effective price for a product and customer
     */
    public function calculateEffectivePrice(Product $product, ?int $customerGroupId, float $quantity = 1): array
    {
        $basePrice = $product->sale_price ?? $product->cost_price ?? 0;
        $effectivePrice = $basePrice;
        $discountType = null;
        $discountValue = 0;

        if ($customerGroupId) {
            // Check for group-specific price
            $groupPrice = $this->getPriceForProduct($product->id, $customerGroupId, $quantity);

            if ($groupPrice) {
                $effectivePrice = $groupPrice->price;
                $discountType = 'group_price';
                $discountValue = $basePrice - $effectivePrice;
            } else {
                // Apply group discount percentage
                $group = CustomerGroup::find($customerGroupId);
                if ($group && $group->discount_percentage > 0) {
                    $discountValue = $basePrice * ($group->discount_percentage / 100);
                    $effectivePrice = $basePrice - $discountValue;
                    $discountType = 'group_discount';
                }
            }
        }

        return [
            'base_price' => $basePrice,
            'effective_price' => $effectivePrice,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'currency_id' => $product->currency_id,
        ];
    }

    /**
     * Create or update group price
     */
    public function setPrice(array $data): CustomerGroupPrice
    {
        Log::info('Setting customer group price', [
            'customer_group_id' => $data['customer_group_id'],
            'product_id' => $data['product_id'],
        ]);

        DB::beginTransaction();

        try {
            $companyId = Auth::user()->company_id;

            // Check for existing price with same criteria
            $existing = CustomerGroupPrice::where('customer_group_id', $data['customer_group_id'])
                ->where('product_id', $data['product_id'])
                ->where('min_quantity', $data['min_quantity'] ?? 1)
                ->first();

            if ($existing) {
                $existing->update([
                    'price' => $data['price'],
                    'currency_id' => $data['currency_id'] ?? $existing->currency_id,
                    'valid_from' => $data['valid_from'] ?? $existing->valid_from,
                    'valid_until' => $data['valid_until'] ?? $existing->valid_until,
                    'is_active' => $data['is_active'] ?? $existing->is_active,
                ]);

                DB::commit();
                return $existing->fresh(['product', 'customerGroup', 'currency']);
            }

            $groupPrice = CustomerGroupPrice::create([
                'company_id' => $companyId,
                'customer_group_id' => $data['customer_group_id'],
                'product_id' => $data['product_id'],
                'price' => $data['price'],
                'currency_id' => $data['currency_id'] ?? null,
                'min_quantity' => $data['min_quantity'] ?? 1,
                'valid_from' => $data['valid_from'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            DB::commit();

            Log::info('Customer group price created', [
                'group_price_id' => $groupPrice->id,
            ]);

            return $groupPrice->load(['product', 'customerGroup', 'currency']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to set customer group price', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Bulk set prices for a customer group
     */
    public function bulkSetPrices(CustomerGroup $customerGroup, array $prices): Collection
    {
        Log::info('Bulk setting prices for customer group', [
            'customer_group_id' => $customerGroup->id,
            'price_count' => count($prices),
        ]);

        DB::beginTransaction();

        try {
            $results = collect();

            foreach ($prices as $priceData) {
                $priceData['customer_group_id'] = $customerGroup->id;
                $results->push($this->setPrice($priceData));
            }

            DB::commit();

            return $results;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete group price
     */
    public function delete(CustomerGroupPrice $groupPrice): bool
    {
        Log::info('Deleting customer group price', [
            'group_price_id' => $groupPrice->id,
        ]);

        return $groupPrice->delete();
    }
}
