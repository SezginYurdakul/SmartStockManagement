<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockDebt;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockAlertService
{
    /**
     * Get negative stock alerts
     */
    public function getNegativeStockAlerts(): Collection
    {
        $companyId = Auth::user()->company_id;
        
        return Stock::where('company_id', $companyId)
            ->where('quantity_on_hand', '<', 0)
            ->with(['product', 'warehouse'])
            ->get()
            ->map(function ($stock) {
                $product = $stock->product;
                $policy = $product->negative_stock_policy ?? 'NEVER';
                
                return [
                    'stock_id' => $stock->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'warehouse_id' => $stock->warehouse_id,
                    'warehouse_name' => $stock->warehouse->name,
                    'negative_quantity' => abs($stock->quantity_on_hand),
                    'policy' => $policy,
                    'severity' => $this->calculateSeverity($stock, $product),
                    'outstanding_debt' => $this->getOutstandingDebt($stock),
                ];
            });
    }

    /**
     * Get weekly negative stock report
     */
    public function getWeeklyNegativeStockReport(): array
    {
        $companyId = Auth::user()->company_id;
        
        $negativeStocks = Stock::where('company_id', $companyId)
            ->where('quantity_on_hand', '<', 0)
            ->with(['product.productType', 'warehouse'])
            ->get()
            ->groupBy('product.product_type_id');
        
        return [
            'total_items' => $negativeStocks->flatten()->count(),
            'by_category' => $negativeStocks->map(function ($stocks, $typeId) {
                return [
                    'category_id' => $typeId,
                    'category_name' => $stocks->first()->product->productType?->name,
                    'count' => $stocks->count(),
                    'total_negative_quantity' => $stocks->sum(function ($s) {
                        return abs($s->quantity_on_hand);
                    }),
                    'items' => $stocks->map(function ($stock) {
                        return [
                            'product_id' => $stock->product_id,
                            'product_name' => $stock->product->name,
                            'warehouse_id' => $stock->warehouse_id,
                            'warehouse_name' => $stock->warehouse->name,
                            'negative_quantity' => abs($stock->quantity_on_hand),
                            'policy' => $stock->product->negative_stock_policy,
                            'days_negative' => $this->getDaysNegative($stock),
                        ];
                    }),
                ];
            }),
        ];
    }

    /**
     * Check long-term negative stock (outstanding for more than threshold days)
     */
    public function checkLongTermNegativeStock(int $thresholdDays = 7): Collection
    {
        $cutoffDate = now()->subDays($thresholdDays);
        
        return StockDebt::whereColumn('reconciled_quantity', '<', 'quantity')
            ->where('created_at', '<', $cutoffDate)
            ->with(['product', 'warehouse'])
            ->get()
            ->map(function ($debt) {
                return [
                    'debt_id' => $debt->id,
                    'product_id' => $debt->product_id,
                    'product_name' => $debt->product->name,
                    'warehouse_id' => $debt->warehouse_id,
                    'warehouse_name' => $debt->warehouse->name,
                    'outstanding_quantity' => $debt->quantity - $debt->reconciled_quantity,
                    'days_outstanding' => $debt->created_at->diffInDays(now()),
                    'severity' => $this->calculateDebtSeverity($debt),
                ];
            });
    }

    /**
     * Calculate severity for negative stock
     */
    protected function calculateSeverity(Stock $stock, Product $product): string
    {
        $negativeQty = abs($stock->quantity_on_hand);
        $policy = $product->negative_stock_policy ?? 'NEVER';
        
        if ($policy === 'NEVER') {
            return 'critical'; // Should never happen
        }
        
        if ($policy === 'LIMITED') {
            $limit = $product->negative_stock_limit ?? 0;
            $percentage = $limit > 0 ? ($negativeQty / $limit) * 100 : 0;
            
            if ($percentage >= 90) {
                return 'critical';
            } elseif ($percentage >= 70) {
                return 'high';
            } elseif ($percentage >= 50) {
                return 'medium';
            }
            return 'low';
        }
        
        // ALLOWED policy - check days negative
        $daysNegative = $this->getDaysNegative($stock);
        if ($daysNegative > 14) {
            return 'critical';
        } elseif ($daysNegative > 7) {
            return 'high';
        } elseif ($daysNegative > 3) {
            return 'medium';
        }
        
        return 'low';
    }

    /**
     * Calculate severity for stock debt
     */
    protected function calculateDebtSeverity(StockDebt $debt): string
    {
        $daysOutstanding = $debt->created_at->diffInDays(now());
        
        if ($daysOutstanding > 14) {
            return 'critical';
        } elseif ($daysOutstanding > 7) {
            return 'high';
        } elseif ($daysOutstanding > 3) {
            return 'medium';
        }
        
        return 'low';
    }

    /**
     * Get outstanding debt for stock
     */
    protected function getOutstandingDebt(Stock $stock): float
    {
        return StockDebt::where('company_id', $stock->company_id)
            ->where('product_id', $stock->product_id)
            ->where('warehouse_id', $stock->warehouse_id)
            ->whereColumn('reconciled_quantity', '<', 'quantity')
            ->sum(DB::raw('quantity - reconciled_quantity'));
    }

    /**
     * Get days stock has been negative
     */
    protected function getDaysNegative(Stock $stock): int
    {
        $oldestDebt = StockDebt::where('company_id', $stock->company_id)
            ->where('product_id', $stock->product_id)
            ->where('warehouse_id', $stock->warehouse_id)
            ->whereColumn('reconciled_quantity', '<', 'quantity')
            ->orderBy('created_at', 'asc')
            ->first();
        
        if ($oldestDebt) {
            return $oldestDebt->created_at->diffInDays(now());
        }
        
        return 0;
    }
}
