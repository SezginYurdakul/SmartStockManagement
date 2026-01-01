<?php

namespace App\Services;

use App\Models\WorkCenter;
use App\Enums\WorkCenterType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkCenterService
{
    /**
     * Get paginated work centers with filters
     */
    public function getWorkCenters(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = WorkCenter::with(['creator:id,first_name,last_name']);

        // Search
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Active filter
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        // Type filter
        if (!empty($filters['work_center_type'])) {
            $query->where('work_center_type', $filters['work_center_type']);
        }

        return $query->orderBy('code')->paginate($perPage);
    }

    /**
     * Get all active work centers for dropdowns
     */
    public function getActiveWorkCenters(): Collection
    {
        return WorkCenter::active()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'work_center_type', 'cost_per_hour']);
    }

    /**
     * Get work center with relationships
     */
    public function getWorkCenter(WorkCenter $workCenter): WorkCenter
    {
        return $workCenter->load(['creator:id,first_name,last_name']);
    }

    /**
     * Create a new work center
     */
    public function create(array $data): WorkCenter
    {
        Log::info('Creating work center', [
            'code' => $data['code'] ?? null,
            'name' => $data['name'] ?? null,
        ]);

        $data['company_id'] = Auth::user()->company_id;
        $data['created_by'] = Auth::id();

        $workCenter = WorkCenter::create($data);

        Log::info('Work center created', ['id' => $workCenter->id]);

        return $workCenter;
    }

    /**
     * Update work center
     */
    public function update(WorkCenter $workCenter, array $data): WorkCenter
    {
        Log::info('Updating work center', [
            'id' => $workCenter->id,
            'changes' => array_keys($data),
        ]);

        $workCenter->update($data);

        return $workCenter->fresh();
    }

    /**
     * Delete work center
     */
    public function delete(WorkCenter $workCenter): bool
    {
        // Check if work center has active operations
        $activeOperations = $workCenter->workOrderOperations()
            ->whereHas('workOrder', function ($q) {
                $q->whereNotIn('status', ['completed', 'cancelled']);
            })
            ->count();

        if ($activeOperations > 0) {
            throw new \App\Exceptions\BusinessException(
                "Cannot delete work center with {$activeOperations} active operations."
            );
        }

        Log::info('Deleting work center', ['id' => $workCenter->id]);

        return $workCenter->delete();
    }

    /**
     * Toggle work center active status
     */
    public function toggleActive(WorkCenter $workCenter): WorkCenter
    {
        $newStatus = !$workCenter->is_active;

        Log::info('Toggling work center status', [
            'id' => $workCenter->id,
            'new_status' => $newStatus,
        ]);

        $workCenter->update(['is_active' => $newStatus]);

        return $workCenter->fresh();
    }

    /**
     * Generate work center code
     */
    public function generateCode(string $prefix = 'WC'): string
    {
        $companyId = Auth::user()->company_id;

        $lastWC = WorkCenter::withTrashed()
            ->where('company_id', $companyId)
            ->where('code', 'like', "{$prefix}-%")
            ->orderByRaw("CAST(SUBSTRING(code FROM '[0-9]+') AS INTEGER) DESC")
            ->first();

        if ($lastWC && preg_match('/(\d+)/', $lastWC->code, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get work center availability for a date range
     */
    public function getAvailability(WorkCenter $workCenter, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $availableHours = $workCenter->calculateAvailableHours($startDate, $endDate);

        // Calculate scheduled hours
        $scheduledHours = $workCenter->workOrderOperations()
            ->whereHas('workOrder', function ($q) {
                $q->whereNotIn('status', ['completed', 'cancelled']);
            })
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('planned_start', [$startDate, $endDate])
                  ->orWhereBetween('planned_end', [$startDate, $endDate]);
            })
            ->get()
            ->sum(function ($op) {
                $routingOp = $op->routingOperation;
                if ($routingOp) {
                    return ($routingOp->setup_time + ($routingOp->run_time_per_unit * $op->workOrder->quantity_ordered)) / 60;
                }
                return 0;
            });

        return [
            'work_center_id' => $workCenter->id,
            'work_center_name' => $workCenter->name,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'total_available_hours' => round($availableHours, 2),
            'scheduled_hours' => round($scheduledHours, 2),
            'remaining_hours' => round($availableHours - $scheduledHours, 2),
            'utilization_percentage' => $availableHours > 0
                ? round(($scheduledHours / $availableHours) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get work centers by type
     */
    public function getByType(WorkCenterType $type): Collection
    {
        return WorkCenter::active()
            ->ofType($type)
            ->orderBy('name')
            ->get();
    }
}
