<?php

namespace App\Http\Controllers;

use App\Models\CompanyCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CompanyCalendarController extends Controller
{
    /**
     * List all calendar entries (optionally filtered by date range)
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = Auth::user()->company_id;

        $query = CompanyCalendar::where('company_id', $companyId)
            ->orderBy('calendar_date', 'desc');

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('calendar_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('calendar_date', '<=', $request->end_date);
        }

        // Filter by day type
        if ($request->has('day_type')) {
            $query->where('day_type', $request->day_type);
        }

        // Filter by recurring
        if ($request->has('recurring')) {
            $query->where('is_recurring', filter_var($request->recurring, FILTER_VALIDATE_BOOLEAN));
        }

        $calendars = $query->paginate($request->get('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $calendars,
        ]);
    }

    /**
     * Get a specific calendar entry
     */
    public function show(CompanyCalendar $calendar): JsonResponse
    {
        // Ensure user can only access their company's calendar
        if ($calendar->company_id !== Auth::user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Calendar entry not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $calendar,
        ]);
    }

    /**
     * Create a new calendar entry
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'calendar_date' => 'required|date',
            'day_type' => 'required|in:working,holiday',
            'shift_name' => 'nullable|string|max:50',
            'shift_start' => 'nullable|date_format:H:i:s',
            'shift_end' => 'nullable|date_format:H:i:s',
            'break_hours' => 'nullable|numeric|min:0|max:24',
            'working_hours' => 'nullable|numeric|min:0|max:24',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_recurring' => 'nullable|boolean',
            'recurrence_type' => 'nullable|in:yearly,monthly,weekly',
            'recurrence_pattern' => 'nullable|array',
        ]);

        $companyId = Auth::user()->company_id;

        // Check if entry already exists for this date
        $existing = CompanyCalendar::where('company_id', $companyId)
            ->forDate($validated['calendar_date'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Calendar entry already exists for this date. Use update instead.',
            ], 409);
        }

        $calendar = CompanyCalendar::create([
            'company_id' => $companyId,
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Calendar entry created successfully',
            'data' => $calendar,
        ], 201);
    }

    /**
     * Update a calendar entry
     */
    public function update(Request $request, CompanyCalendar $calendar): JsonResponse
    {
        // Ensure user can only update their company's calendar
        if ($calendar->company_id !== Auth::user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Calendar entry not found',
            ], 404);
        }

        $validated = $request->validate([
            'day_type' => 'sometimes|in:working,holiday',
            'shift_name' => 'nullable|string|max:50',
            'shift_start' => 'nullable|date_format:H:i:s',
            'shift_end' => 'nullable|date_format:H:i:s',
            'break_hours' => 'nullable|numeric|min:0|max:24',
            'working_hours' => 'nullable|numeric|min:0|max:24',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_recurring' => 'nullable|boolean',
            'recurrence_type' => 'nullable|in:yearly,monthly,weekly',
            'recurrence_pattern' => 'nullable|array',
        ]);

        $calendar->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Calendar entry updated successfully',
            'data' => $calendar->fresh(),
        ]);
    }

    /**
     * Delete a calendar entry
     */
    public function destroy(CompanyCalendar $calendar): JsonResponse
    {
        // Ensure user can only delete their company's calendar
        if ($calendar->company_id !== Auth::user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Calendar entry not found',
            ], 404);
        }

        $calendar->delete();

        return response()->json([
            'success' => true,
            'message' => 'Calendar entry deleted successfully',
        ]);
    }

    /**
     * Bulk create calendar entries (for holidays, etc.)
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entries' => 'required|array|min:1',
            'entries.*.calendar_date' => 'required|date',
            'entries.*.day_type' => 'required|in:working,holiday',
            'entries.*.shift_name' => 'nullable|string|max:50',
            'entries.*.shift_start' => 'nullable|date_format:H:i:s',
            'entries.*.shift_end' => 'nullable|date_format:H:i:s',
            'entries.*.break_hours' => 'nullable|numeric|min:0|max:24',
            'entries.*.working_hours' => 'nullable|numeric|min:0|max:24',
            'entries.*.reason' => 'nullable|string|max:255',
            'entries.*.notes' => 'nullable|string',
        ]);

        $companyId = Auth::user()->company_id;
        $created = [];
        $skipped = [];

        DB::beginTransaction();
        try {
            foreach ($validated['entries'] as $entry) {
                // Check if entry already exists
                $existing = CompanyCalendar::where('company_id', $companyId)
                    ->forDate($entry['calendar_date'])
                    ->first();

                if ($existing) {
                    $skipped[] = $entry['calendar_date'];
                    continue;
                }

                $calendar = CompanyCalendar::create([
                    'company_id' => $companyId,
                    ...$entry,
                ]);

                $created[] = $calendar;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Calendar entries created successfully',
                'data' => [
                    'created' => count($created),
                    'skipped' => count($skipped),
                    'entries' => $created,
                    'skipped_dates' => $skipped,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create calendar entries: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get calendar entries for a specific date range (for MRP planning)
     */
    public function getDateRange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $companyId = Auth::user()->company_id;

        $entries = CompanyCalendar::where('company_id', $companyId)
            ->dateRange($validated['start_date'], $validated['end_date'])
            ->orderBy('calendar_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $entries,
        ]);
    }
}
