<?php

namespace App\Services;

use App\Jobs\LogAuditEvent;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditLogService
{
    /**
     * Log an event
     */
    public function log(
        string $eventType,
        Model $entity,
        ?array $changes = null,
        ?string $description = null,
        ?array $metadata = null
    ): ?AuditLog {
        $user = Auth::user();
        
        // Determine company_id
        $companyId = $user->company_id ?? $entity->company_id ?? null;
        
        if (!$companyId) {
            Log::warning('Cannot create audit log: no company_id available', [
                'event_type' => $eventType,
                'entity_type' => get_class($entity),
                'entity_id' => $entity->id ?? null,
            ]);
            return null;
        }

        $auditData = [
            'company_id' => $companyId,
            'event_type' => $eventType,
            'entity_type' => get_class($entity),
            'entity_id' => $entity->id,
            'user_id' => $user->id ?? null,
            'user_name' => $user->full_name ?? null,
            'user_email' => $user->email ?? null,
            'occurred_at' => now(),
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_id' => request()->header('X-Request-ID'),
            'description' => $description ?? $this->generateDescription($eventType, $entity),
            'metadata' => $metadata,
        ];

        // Check if sync logging is enabled
        if (config('audit.sync', false)) {
            // Synchronous logging (for development/test or critical events)
            return AuditLog::create($auditData);
        }

        // Asynchronous logging (production default)
        // Use afterCommit to ensure transaction is committed before logging
        DB::afterCommit(function () use ($auditData) {
            LogAuditEvent::dispatch($auditData);
        });

        return null; // Async logging doesn't return the log immediately
    }

    /**
     * Log creation
     */
    public function logCreation(Model $entity, ?string $description = null): ?AuditLog
    {
        return $this->log('created', $entity, null, $description);
    }

    /**
     * Log update with field changes
     */
    public function logUpdate(Model $entity, ?string $description = null): ?AuditLog
    {
        $changes = $this->extractChanges($entity);
        
        if (empty($changes)) {
            return null; // No changes, no log
        }
        
        return $this->log('updated', $entity, $changes, $description);
    }

    /**
     * Log deletion
     */
    public function logDeletion(Model $entity, ?string $description = null): ?AuditLog
    {
        // Store entity data before deletion
        $metadata = $this->extractEntityData($entity);
        
        return $this->log('deleted', $entity, null, $description, $metadata);
    }

    /**
     * Log custom event (approval, rejection, etc.)
     */
    public function logEvent(
        string $eventType,
        Model $entity,
        ?string $description = null,
        ?array $metadata = null
    ): ?AuditLog {
        return $this->log($eventType, $entity, null, $description, $metadata);
    }

    /**
     * Get audit trail for an entity
     */
    public function getAuditTrail(string $entityType, int $entityId)
    {
        return AuditLog::forEntity($entityType, $entityId)
            ->orderBy('occurred_at')
            ->get();
    }

    /**
     * Extract changes from model
     */
    protected function extractChanges(Model $entity): array
    {
        $changes = [];
        $original = $entity->getOriginal();
        $dirty = $entity->getDirty();
        
        // Fields to exclude from audit
        // Timestamps are excluded (automatically managed by Laravel)
        // Blameable fields are excluded (automatically managed by Blameable trait)
        // Note: created_by is NOT excluded because it's only set at creation, not during updates
        $excludedFields = [
            'updated_at', 
            'created_at', 
            'deleted_at',
            'updated_by',  // Managed by Blameable trait - automatically set on every update
            'deleted_by',  // Managed by Blameable trait - automatically set on deletion
        ];
        $sensitiveFields = ['password', 'api_key', 'secret', 'token', 'remember_token'];
        
        foreach ($dirty as $key => $newValue) {
            // Skip timestamps and hidden fields
            if (in_array($key, $excludedFields) || 
                in_array($key, $entity->getHidden())) {
                continue;
            }
            
            // Mask sensitive fields
            if (in_array($key, $sensitiveFields)) {
                $changes[$key] = [
                    'old' => '***MASKED***',
                    'new' => '***MASKED***',
                ];
                continue;
            }
            
            $changes[$key] = [
                'old' => $original[$key] ?? null,
                'new' => $newValue,
            ];
        }
        
        return $changes;
    }

    /**
     * Extract entity data for deletion logs
     */
    protected function extractEntityData(Model $entity): array
    {
        $data = [];
        $fillable = $entity->getFillable();
        $hidden = $entity->getHidden();
        $sensitiveFields = ['password', 'api_key', 'secret', 'token', 'remember_token'];
        
        foreach ($fillable as $field) {
            if (!in_array($field, $hidden) && !in_array($field, $sensitiveFields)) {
                $value = $entity->getAttribute($field);
                if ($value !== null) {
                    $data[$field] = $value;
                }
            }
        }
        
        return $data;
    }

    /**
     * Generate default description
     */
    protected function generateDescription(string $eventType, Model $entity): string
    {
        $entityName = class_basename($entity);
        
        return match($eventType) {
            'created' => "Created {$entityName}",
            'updated' => "Updated {$entityName}",
            'deleted' => "Deleted {$entityName}",
            default => ucfirst($eventType) . " {$entityName}",
        };
    }

    /**
     * Check if event is critical (should be logged synchronously)
     */
    protected function isCriticalEvent(string $eventType, Model $entity): bool
    {
        $criticalEvents = config('audit.critical_events', [
            'deleted',
            'approved',
            'rejected',
            'stock_adjusted',
        ]);
        
        $criticalEntities = config('audit.critical_entities', [
            \App\Models\WorkOrder::class,
            \App\Models\PurchaseOrder::class,
            \App\Models\StockMovement::class,
        ]);
        
        return in_array($eventType, $criticalEvents) || 
               in_array(get_class($entity), $criticalEntities);
    }
}
