<?php

namespace App\Observers;

use App\Models\CompanyCalendar;
use App\Services\AuditLogService;
use App\Services\MrpCacheService;
use Illuminate\Support\Facades\Log;

/**
 * Company Calendar Observer
 * Automatically invalidates MRP cache when company calendar changes
 * (affects working day calculations) and logs audit events for compliance
 */
class CompanyCalendarObserver
{
    public function __construct(
        protected MrpCacheService $cacheService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Handle the CompanyCalendar "created" event.
     */
    public function created(CompanyCalendar $calendar): void
    {
        $this->invalidateCache($calendar);
        
        // Audit logging
        $this->auditLogService->logCreation(
            $calendar,
            "Company Calendar created: {$calendar->calendar_date} - {$calendar->day_type}"
        );
    }

    /**
     * Handle the CompanyCalendar "updated" event.
     */
    public function updated(CompanyCalendar $calendar): void
    {
        // Invalidate if working day related fields changed
        if ($calendar->wasChanged([
            'day_type',
            'shift_start',
            'shift_end',
            'working_hours',
            'calendar_date',
        ])) {
            $this->invalidateCache($calendar);
        }
        
        // Audit logging
        $this->auditLogService->logUpdate(
            $calendar,
            "Company Calendar updated: {$calendar->calendar_date} - {$calendar->day_type}"
        );
    }

    /**
     * Handle the CompanyCalendar "deleted" event.
     */
    public function deleted(CompanyCalendar $calendar): void
    {
        $this->invalidateCache($calendar);
        
        // Audit logging
        $this->auditLogService->logDeletion(
            $calendar,
            "Company Calendar deleted: {$calendar->calendar_date} - {$calendar->day_type}"
        );
    }

    /**
     * Invalidate MRP cache for the company
     */
    protected function invalidateCache(CompanyCalendar $calendar): void
    {
        try {
            // Calendar changes affect working day calculations
            // Invalidate all MRP caches for this company
            $this->cacheService->invalidateCompanyCache($calendar->company_id);
            
            Log::info('MRP cache invalidated due to company calendar change', [
                'calendar_id' => $calendar->id,
                'company_id' => $calendar->company_id,
                'date' => $calendar->calendar_date,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to invalidate MRP cache', [
                'calendar_id' => $calendar->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
