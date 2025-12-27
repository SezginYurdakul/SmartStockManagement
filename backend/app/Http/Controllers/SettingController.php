<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * List all settings (optionally filtered by group)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Setting::query();

        if ($request->has('group')) {
            $query->byGroup($request->group);
        }

        $settings = $query->orderBy('group')->orderBy('key')->get();

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Get a specific setting by group.key
     */
    public function show(string $group, string $key): JsonResponse
    {
        $value = Setting::get("{$group}.{$key}");

        if ($value === null) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $value,
        ]);
    }

    /**
     * Get all settings for a group
     */
    public function group(string $group): JsonResponse
    {
        $settings = Setting::getGroup($group);

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Create or update a setting
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group' => 'required|string|max:50',
            'key' => 'required|string|max:100',
            'value' => 'required',
            'description' => 'nullable|string|max:255',
        ]);

        $setting = Setting::set(
            "{$validated['group']}.{$validated['key']}",
            $validated['value'],
            $validated['description'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Setting saved successfully',
            'data' => $setting,
        ], 201);
    }

    /**
     * Update a setting value
     */
    public function update(Request $request, string $group, string $key): JsonResponse
    {
        $setting = Setting::where('group', $group)->where('key', $key)->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found',
            ], 404);
        }

        $validated = $request->validate([
            'value' => 'required',
            'description' => 'nullable|string|max:255',
        ]);

        $setting->update([
            'value' => $validated['value'],
            'description' => $validated['description'] ?? $setting->description,
        ]);

        // Clear cache
        Setting::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully',
            'data' => $setting->fresh(),
        ]);
    }

    /**
     * Delete a setting (non-system only)
     */
    public function destroy(string $group, string $key): JsonResponse
    {
        $setting = Setting::where('group', $group)->where('key', $key)->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found',
            ], 404);
        }

        if ($setting->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'System settings cannot be deleted',
            ], 403);
        }

        $setting->delete();
        Setting::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Setting deleted successfully',
        ]);
    }

    /**
     * Get available groups
     */
    public function groups(): JsonResponse
    {
        $groups = Setting::select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group');

        return response()->json([
            'success' => true,
            'data' => $groups,
        ]);
    }
}
