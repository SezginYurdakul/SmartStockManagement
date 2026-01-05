<?php

namespace App\Services;

use App\Models\WorkCenter;
use App\Models\WorkCenterCalendar;
use App\Models\WorkOrder;
use App\Models\WorkOrderOperation;
use App\Enums\CalendarDayType;
use App\Enums\WorkOrderStatus;
use App\Enums\OperationStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class CapacityService
{
    // =========================================
    // Calendar Management
    // =========================================

    /**
     * Generate calendar entries for a work center
     */
    public function generateCalendar(
        WorkCenter $workCenter,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $holidays = []
    ): int {
        return WorkCenterCalendar::generateForWorkCenter(
            $workCenter,
            $startDate,
            $endDate,
            $holidays
        );
    }

    /**
     * Generate calendars for all active work centers
     */
    public function generateAllCalendars(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $holidays = []
    ): array {
        $companyId = Auth::user()->company_id;
        $results = [];

        $workCenters = WorkCenter::where('company_id', $companyId)
            ->active()
            ->get();

        foreach ($workCenters as $workCenter) {
            $count = $this->generateCalendar($workCenter, $startDate, $endDate, $holidays);
            $results[$workCenter->id] = [
                'work_center' => $workCenter->name,
                'entries_created' => $count,
            ];
        }

        return $results;
    }

    /**
     * Update calendar entry
     */
    public function updateCalendarEntry(WorkCenterCalendar $entry, array $data): WorkCenterCalendar
    {
        $entry->update($data);
        return $entry->fresh();
    }

    /**
     * Set holiday for a date range
     */
    public function setHoliday(
        int $workCenterId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string $reason
    ): int {
        return WorkCenterCalendar::where('work_center_id', $workCenterId)
            ->dateRange($startDate, $endDate)
            ->update([
                'day_type' => CalendarDayType::HOLIDAY,
                'available_hours' => 0,
                'notes' => $reason,
            ]);
    }

    /**
     * Set maintenance for a date
     */
    public function setMaintenance(
        int $workCenterId,
        \DateTimeInterface $date,
        float $reducedHours,
        string $reason
    ): WorkCenterCalendar {
        $entry = WorkCenterCalendar::where('work_center_id', $workCenterId)
            ->forDate($date)
            ->first();

        if (!$entry) {
            $workCenter = WorkCenter::findOrFail($workCenterId);
            $entry = WorkCenterCalendar::create([
                'company_id' => $workCenter->company_id,
                'work_center_id' => $workCenterId,
                'calendar_date' => $date,
                'day_type' => CalendarDayType::MAINTENANCE,
                'available_hours' => $reducedHours,
                'notes' => $reason,
            ]);
        } else {
            $entry->update([
                'day_type' => CalendarDayType::MAINTENANCE,
                'capacity_override' => $reducedHours,
                'notes' => $reason,
            ]);
        }

        return $entry->fresh();
    }

    // =========================================
    // Capacity Calculations
    // =========================================

    /**
     * Get available capacity for a work center
     */
    public function getAvailableCapacity(
        WorkCenter $workCenter,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        // Get calendar-based available hours
        $totalAvailable = WorkCenterCalendar::getTotalAvailableHours(
            $workCenter->id,
            $startDate,
            $endDate
        );

        // If no calendar entries, calculate from work center defaults
        if ($totalAvailable == 0) {
            $days = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
            // Assume 5 working days per week
            $workingDays = floor($days * 5 / 7);
            $totalAvailable = $workingDays * $workCenter->effective_capacity;
        }

        // Get scheduled load
        $scheduledLoad = $this->getScheduledLoad($workCenter, $startDate, $endDate);

        return [
            'work_center_id' => $workCenter->id,
            'work_center_name' => $workCenter->name,
            'period' => [
                'start' => Carbon::parse($startDate)->toDateString(),
                'end' => Carbon::parse($endDate)->toDateString(),
            ],
            'total_available_hours' => round($totalAvailable, 2),
            'scheduled_hours' => round($scheduledLoad, 2),
            'remaining_hours' => round($totalAvailable - $scheduledLoad, 2),
            'utilization_percent' => $totalAvailable > 0
                ? round(($scheduledLoad / $totalAvailable) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get scheduled load for a work center
     */
    public function getScheduledLoad(
        WorkCenter $workCenter,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): float {
        return WorkOrderOperation::where('work_center_id', $workCenter->id)
            ->whereHas('workOrder', function ($q) use ($startDate, $endDate) {
                $q->whereIn('status', [
                    WorkOrderStatus::RELEASED,
                    WorkOrderStatus::IN_PROGRESS,
                ])
                ->where(function ($q2) use ($startDate, $endDate) {
                    $q2->whereBetween('planned_start_date', [$startDate, $endDate])
                       ->orWhereBetween('planned_end_date', [$startDate, $endDate]);
                });
            })
            ->whereIn('status', [OperationStatus::PENDING, OperationStatus::IN_PROGRESS])
            ->get()
            ->sum(function ($op) {
                // Convert minutes to hours
                $setupTime = $op->actual_setup_time ?? $op->planned_setup_time ?? 0;
                $runTime = $op->actual_run_time ?? $op->planned_run_time ?? 0;
                return ($setupTime + $runTime) / 60;
            });
    }

    /**
     * Get capacity overview for all work centers
     */
    public function getCapacityOverview(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): Collection {
        $companyId = Auth::user()->company_id;

        $workCenters = WorkCenter::where('company_id', $companyId)
            ->active()
            ->get();

        return $workCenters->map(function ($workCenter) use ($startDate, $endDate) {
            return $this->getAvailableCapacity($workCenter, $startDate, $endDate);
        });
    }

    /**
     * Get daily capacity breakdown for a work center
     */
    public function getDailyCapacity(
        WorkCenter $workCenter,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): Collection {
        $days = collect();
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $dateString = $date->toDateString();

            // Get calendar entry
            $calendarEntry = WorkCenterCalendar::where('work_center_id', $workCenter->id)
                ->forDate($dateString)
                ->first();

            // Calculate available hours
            $availableHours = 0;
            $dayType = CalendarDayType::WORKING;

            if ($calendarEntry) {
                $availableHours = $calendarEntry->effective_hours;
                $dayType = $calendarEntry->day_type;
            } elseif (!$date->isWeekend()) {
                // Default to work center capacity if no calendar entry
                $availableHours = $workCenter->effective_capacity;
            }

            // Calculate scheduled hours for this day
            $scheduledHours = $this->getDailyScheduledLoad($workCenter->id, $date);

            $days->push([
                'date' => $dateString,
                'day_name' => $date->format('l'),
                'day_type' => $dayType->value,
                'available_hours' => round($availableHours, 2),
                'scheduled_hours' => round($scheduledHours, 2),
                'remaining_hours' => round(max(0, $availableHours - $scheduledHours), 2),
                'utilization_percent' => $availableHours > 0
                    ? round(($scheduledHours / $availableHours) * 100, 1)
                    : 0,
                'is_overloaded' => $scheduledHours > $availableHours,
            ]);
        }

        return $days;
    }

    /**
     * Get scheduled load for a specific day
     */
    protected function getDailyScheduledLoad(int $workCenterId, Carbon $date): float
    {
        return WorkOrderOperation::where('work_center_id', $workCenterId)
            ->whereHas('workOrder', function ($q) use ($date) {
                $q->whereIn('status', [
                    WorkOrderStatus::RELEASED,
                    WorkOrderStatus::IN_PROGRESS,
                ])
                ->whereDate('planned_start_date', '<=', $date)
                ->whereDate('planned_end_date', '>=', $date);
            })
            ->whereIn('status', [OperationStatus::PENDING, OperationStatus::IN_PROGRESS])
            ->get()
            ->sum(function ($op) {
                $setupTime = $op->actual_setup_time ?? $op->planned_setup_time ?? 0;
                $runTime = $op->actual_run_time ?? $op->planned_run_time ?? 0;
                return ($setupTime + $runTime) / 60;
            });
    }

    // =========================================
    // Capacity Planning / CRP
    // =========================================

    /**
     * Check if capacity is available for a work order
     */
    public function checkCapacityForWorkOrder(WorkOrder $workOrder): array
    {
        $issues = [];
        $hasCapacity = true;

        if (!$workOrder->routing) {
            return [
                'has_capacity' => true,
                'issues' => [],
                'message' => 'No routing defined - capacity check skipped',
            ];
        }

        $startDate = $workOrder->planned_start_date ?? today();
        $endDate = $workOrder->planned_end_date ?? today()->addDays(7);

        foreach ($workOrder->operations as $operation) {
            if (!$operation->work_center_id) {
                continue;
            }

            $workCenter = $operation->workCenter;
            $requiredHours = ($operation->planned_setup_time + $operation->planned_run_time) / 60;

            $capacity = $this->getAvailableCapacity($workCenter, $startDate, $endDate);

            if ($capacity['remaining_hours'] < $requiredHours) {
                $hasCapacity = false;
                $issues[] = [
                    'operation' => $operation->name,
                    'work_center' => $workCenter->name,
                    'required_hours' => round($requiredHours, 2),
                    'available_hours' => $capacity['remaining_hours'],
                    'shortage' => round($requiredHours - $capacity['remaining_hours'], 2),
                ];
            }
        }

        return [
            'has_capacity' => $hasCapacity,
            'issues' => $issues,
            'message' => $hasCapacity
                ? 'Sufficient capacity available'
                : 'Capacity constraints detected',
        ];
    }

    /**
     * Find next available slot for required hours
     */
    public function findNextAvailableSlot(
        WorkCenter $workCenter,
        float $requiredHours,
        ?\DateTimeInterface $startFrom = null
    ): ?array {
        $startDate = $startFrom ? Carbon::parse($startFrom) : today();
        $maxDays = 90; // Look ahead 90 days max

        for ($i = 0; $i < $maxDays; $i++) {
            $checkDate = $startDate->copy()->addDays($i);

            // Skip weekends if no calendar
            if ($checkDate->isWeekend()) {
                $calendarEntry = WorkCenterCalendar::where('work_center_id', $workCenter->id)
                    ->forDate($checkDate)
                    ->first();

                if (!$calendarEntry || !$calendarEntry->isAvailable()) {
                    continue;
                }
            }

            $capacity = $this->getAvailableCapacity($workCenter, $checkDate, $checkDate);

            if ($capacity['remaining_hours'] >= $requiredHours) {
                return [
                    'date' => $checkDate->toDateString(),
                    'available_hours' => $capacity['remaining_hours'],
                    'days_from_now' => $i,
                ];
            }
        }

        return null;
    }

    /**
     * Get work center load report
     */
    public function getLoadReport(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $companyId = Auth::user()->company_id;

        $workCenters = WorkCenter::where('company_id', $companyId)
            ->active()
            ->with(['workOrderOperations' => function ($q) use ($startDate, $endDate) {
                $q->whereHas('workOrder', function ($q2) use ($startDate, $endDate) {
                    $q2->whereIn('status', [
                        WorkOrderStatus::RELEASED,
                        WorkOrderStatus::IN_PROGRESS,
                    ])
                    ->whereBetween('planned_start_date', [$startDate, $endDate]);
                });
            }])
            ->get();

        $report = [
            'period' => [
                'start' => Carbon::parse($startDate)->toDateString(),
                'end' => Carbon::parse($endDate)->toDateString(),
            ],
            'summary' => [
                'total_work_centers' => $workCenters->count(),
                'overloaded_count' => 0,
                'underutilized_count' => 0,
                'optimal_count' => 0,
            ],
            'work_centers' => [],
        ];

        foreach ($workCenters as $workCenter) {
            $capacity = $this->getAvailableCapacity($workCenter, $startDate, $endDate);

            $status = 'optimal';
            if ($capacity['utilization_percent'] > 100) {
                $status = 'overloaded';
                $report['summary']['overloaded_count']++;
            } elseif ($capacity['utilization_percent'] < 50) {
                $status = 'underutilized';
                $report['summary']['underutilized_count']++;
            } else {
                $report['summary']['optimal_count']++;
            }

            $report['work_centers'][] = [
                'id' => $workCenter->id,
                'code' => $workCenter->code,
                'name' => $workCenter->name,
                'type' => $workCenter->work_center_type->value,
                'capacity' => $capacity,
                'status' => $status,
            ];
        }

        return $report;
    }

    /**
     * Get bottleneck analysis
     */
    public function getBottleneckAnalysis(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $overview = $this->getCapacityOverview($startDate, $endDate);

        $bottlenecks = $overview
            ->filter(fn($wc) => $wc['utilization_percent'] > 85)
            ->sortByDesc('utilization_percent')
            ->values();

        $recommendations = [];

        foreach ($bottlenecks as $bottleneck) {
            if ($bottleneck['utilization_percent'] > 100) {
                $recommendations[] = [
                    'work_center' => $bottleneck['work_center_name'],
                    'severity' => 'critical',
                    'message' => "Work center is overloaded at {$bottleneck['utilization_percent']}% utilization",
                    'suggestions' => [
                        'Consider overtime or additional shifts',
                        'Reschedule some work orders',
                        'Outsource to subcontractors',
                    ],
                ];
            } elseif ($bottleneck['utilization_percent'] > 90) {
                $recommendations[] = [
                    'work_center' => $bottleneck['work_center_name'],
                    'severity' => 'warning',
                    'message' => "Work center is near capacity at {$bottleneck['utilization_percent']}% utilization",
                    'suggestions' => [
                        'Monitor closely for delays',
                        'Avoid scheduling additional work if possible',
                    ],
                ];
            }
        }

        return [
            'period' => [
                'start' => Carbon::parse($startDate)->toDateString(),
                'end' => Carbon::parse($endDate)->toDateString(),
            ],
            'bottlenecks' => $bottlenecks->toArray(),
            'recommendations' => $recommendations,
        ];
    }
}
