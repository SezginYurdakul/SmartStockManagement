<?php

namespace App\Http\Controllers;

use App\Models\WorkCenter;
use App\Models\WorkCenterCalendar;
use App\Models\WorkOrder;
use App\Services\CapacityService;
use App\Http\Resources\WorkCenterCalendarResource;
use App\Enums\CalendarDayType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CapacityController extends Controller
{
    public function __construct(
        protected CapacityService $capacityService
    ) {}

    // =========================================
    // Calendar Management
    // =========================================

    /**
     * Get calendar entries for a work center
     */
    public function calendar(Request $request, WorkCenter $workCenter): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $entries = WorkCenterCalendar::where('work_center_id', $workCenter->id)
            ->dateRange($validated['start_date'], $validated['end_date'])
            ->orderBy('calendar_date')
            ->get();

        return WorkCenterCalendarResource::collection($entries);
    }

    /**
     * Generate calendar entries
     */
    public function generateCalendar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'work_center_id' => 'nullable|exists:work_centers,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'holidays' => 'nullable|array',
            'holidays.*.date' => 'required|date',
            'holidays.*.reason' => 'required|string|max:255',
        ]);

        $holidays = [];
        if (!empty($validated['holidays'])) {
            foreach ($validated['holidays'] as $holiday) {
                $holidays[$holiday['date']] = $holiday['reason'];
            }
        }

        if (!empty($validated['work_center_id'])) {
            $workCenter = WorkCenter::findOrFail($validated['work_center_id']);
            $count = $this->capacityService->generateCalendar(
                $workCenter,
                Carbon::parse($validated['start_date']),
                Carbon::parse($validated['end_date']),
                $holidays
            );

            return response()->json([
                'message' => "Generated {$count} calendar entries",
                'entries_created' => $count,
            ]);
        }

        $results = $this->capacityService->generateAllCalendars(
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date']),
            $holidays
        );

        return response()->json([
            'message' => 'Calendar entries generated',
            'data' => $results,
        ]);
    }

    /**
     * Update a calendar entry
     */
    public function updateCalendarEntry(Request $request, WorkCenterCalendar $calendar): JsonResponse
    {
        $validated = $request->validate([
            'shift_start' => 'nullable|date_format:H:i:s',
            'shift_end' => 'nullable|date_format:H:i:s',
            'break_hours' => 'nullable|numeric|min:0|max:12',
            'available_hours' => 'nullable|numeric|min:0|max:24',
            'efficiency_override' => 'nullable|numeric|min:0|max:200',
            'capacity_override' => 'nullable|numeric|min:0|max:24',
            'day_type' => ['nullable', Rule::enum(CalendarDayType::class)],
            'notes' => 'nullable|string|max:500',
        ]);

        $entry = $this->capacityService->updateCalendarEntry($calendar, $validated);

        return response()->json([
            'message' => 'Calendar entry updated',
            'data' => WorkCenterCalendarResource::make($entry),
        ]);
    }

    /**
     * Set holiday for a date range
     */
    public function setHoliday(Request $request, WorkCenter $workCenter): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:255',
        ]);

        $count = $this->capacityService->setHoliday(
            $workCenter->id,
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date']),
            $validated['reason']
        );

        return response()->json([
            'message' => "{$count} days marked as holiday",
            'updated_count' => $count,
        ]);
    }

    /**
     * Set maintenance for a date
     */
    public function setMaintenance(Request $request, WorkCenter $workCenter): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'reduced_hours' => 'required|numeric|min:0|max:24',
            'reason' => 'required|string|max:255',
        ]);

        $entry = $this->capacityService->setMaintenance(
            $workCenter->id,
            Carbon::parse($validated['date']),
            $validated['reduced_hours'],
            $validated['reason']
        );

        return response()->json([
            'message' => 'Maintenance scheduled',
            'data' => WorkCenterCalendarResource::make($entry),
        ]);
    }

    // =========================================
    // Capacity Analysis
    // =========================================

    /**
     * Get capacity overview for all work centers
     */
    public function overview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $overview = $this->capacityService->getCapacityOverview(
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date'])
        );

        return response()->json([
            'data' => $overview,
        ]);
    }

    /**
     * Get capacity for a specific work center
     */
    public function workCenterCapacity(Request $request, WorkCenter $workCenter): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $capacity = $this->capacityService->getAvailableCapacity(
            $workCenter,
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date'])
        );

        return response()->json([
            'data' => $capacity,
        ]);
    }

    /**
     * Get daily capacity breakdown for a work center
     */
    public function dailyCapacity(Request $request, WorkCenter $workCenter): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $daily = $this->capacityService->getDailyCapacity(
            $workCenter,
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date'])
        );

        return response()->json([
            'data' => $daily,
        ]);
    }

    /**
     * Check capacity for a work order
     */
    public function checkWorkOrderCapacity(WorkOrder $workOrder): JsonResponse
    {
        $result = $this->capacityService->checkCapacityForWorkOrder($workOrder);

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Find next available slot
     */
    public function findSlot(Request $request, WorkCenter $workCenter): JsonResponse
    {
        $validated = $request->validate([
            'required_hours' => 'required|numeric|min:0.1',
            'start_from' => 'nullable|date',
        ]);

        $slot = $this->capacityService->findNextAvailableSlot(
            $workCenter,
            $validated['required_hours'],
            isset($validated['start_from']) ? Carbon::parse($validated['start_from']) : null
        );

        if (!$slot) {
            return response()->json([
                'message' => 'No available slot found within 90 days',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => $slot,
        ]);
    }

    // =========================================
    // Reports
    // =========================================

    /**
     * Get load report
     */
    public function loadReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $report = $this->capacityService->getLoadReport(
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date'])
        );

        return response()->json([
            'data' => $report,
        ]);
    }

    /**
     * Get bottleneck analysis
     */
    public function bottleneckAnalysis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $analysis = $this->capacityService->getBottleneckAnalysis(
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date'])
        );

        return response()->json([
            'data' => $analysis,
        ]);
    }

    /**
     * Get day types
     */
    public function dayTypes(): JsonResponse
    {
        return response()->json([
            'data' => CalendarDayType::options(),
        ]);
    }
}
