<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class OverDeliveryToleranceController extends Controller
{
    /**
     * Get default over-delivery tolerance for current company
     */
    public function show(Request $request): JsonResponse
    {
        $companyId = Auth::user()->company_id;
        $key = $this->getSettingKey($companyId);
        
        $value = Setting::get($key, 0);
        
        // Convert array to float if needed (Setting model casts value as array)
        $tolerance = is_array($value) ? (float) ($value[0] ?? 0) : (float) $value;
        
        return response()->json([
            'success' => true,
            'data' => [
                'company_id' => $companyId,
                'tolerance_percentage' => $tolerance,
                'description' => 'Default over-delivery tolerance percentage for this company',
            ],
        ]);
    }

    /**
     * Update default over-delivery tolerance for current company
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tolerance_percentage' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        $companyId = Auth::user()->company_id;
        $key = $this->getSettingKey($companyId);
        
        // Setting model casts value as array, so we need to store as array
        // Store as [value] to work with array cast
        $setting = Setting::where('group', 'delivery')
            ->where('key', "default_over_delivery_tolerance.{$companyId}")
            ->first();

        if ($setting) {
            $setting->update([
                'value' => $validated['tolerance_percentage'], // Laravel will auto-cast to array
                'description' => $validated['description'] ?? $setting->description,
            ]);
        } else {
            $setting = Setting::create([
                'group' => 'delivery',
                'key' => "default_over_delivery_tolerance.{$companyId}",
                'value' => $validated['tolerance_percentage'], // Laravel will auto-cast to array
                'description' => $validated['description'] ?? 'Default over-delivery tolerance percentage for this company',
                'is_system' => false,
            ]);
        }

        // Clear cache
        Cache::tags(['settings'])->forget("settings.delivery.default_over_delivery_tolerance.{$companyId}");
        Cache::tags(['settings'])->forget("settings.delivery");

        return response()->json([
            'success' => true,
            'message' => 'Over-delivery tolerance updated successfully',
            'data' => [
                'company_id' => $companyId,
                'tolerance_percentage' => (float) $validated['tolerance_percentage'],
                'description' => $setting->description,
            ],
        ]);
    }

    /**
     * Get tolerance settings for all levels (Company, Product, Category, System)
     */
    public function levels(Request $request): JsonResponse
    {
        $companyId = Auth::user()->company_id;
        
        // Get company-level setting
        $companyKey = $this->getSettingKey($companyId);
        $companyValue = Setting::get($companyKey, null);
        $companyTolerance = is_array($companyValue) ? (float) ($companyValue[0] ?? null) : (float) $companyValue;
        if ($companyTolerance == 0 && $companyValue !== 0) {
            $companyTolerance = null;
        }

        // Get system default (global, no company_id)
        $systemKey = 'delivery.default_over_delivery_tolerance';
        $systemValue = Setting::get($systemKey, 0);
        $systemTolerance = is_array($systemValue) ? (float) ($systemValue[0] ?? 0) : (float) $systemValue;

        return response()->json([
            'success' => true,
            'data' => [
                'company' => [
                    'tolerance_percentage' => $companyTolerance,
                    'level' => 'company',
                    'description' => 'Company-specific default tolerance',
                ],
                'system' => [
                    'tolerance_percentage' => $systemTolerance,
                    'level' => 'system',
                    'description' => 'System-wide default tolerance (fallback)',
                ],
                'fallback_order' => [
                    '1' => 'Order Item Level (most specific)',
                    '2' => 'Product Level',
                    '3' => 'Category Level',
                    '4' => 'Company Level',
                    '5' => 'System Level (least specific)',
                ],
            ],
        ]);
    }

    /**
     * Get setting key for company-specific tolerance
     */
    protected function getSettingKey(int $companyId): string
    {
        return "delivery.default_over_delivery_tolerance.{$companyId}";
    }
}
