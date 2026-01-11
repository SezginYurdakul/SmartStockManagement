<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

/**
 * Blameable Trait
 *
 * Automatically sets created_by, updated_by, and deleted_by fields
 * when models are created, updated, or deleted.
 *
 * Usage:
 * 1. Add `use Blameable;` to your model
 * 2. Ensure the model's table has created_by, updated_by, deleted_by columns
 * 3. Add these fields to the $fillable array
 */
trait Blameable
{
    /**
     * Boot the trait
     */
    protected static function bootBlameable(): void
    {
        // Set created_by when creating
        static::creating(function ($model) {
            if (Auth::check() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });

        // Set updated_by when updating
        static::updating(function ($model) {
            if (Auth::check() && in_array('updated_by', $model->getFillable())) {
                $model->updated_by = Auth::id();
            }
        });

        // Set deleted_by when soft deleting
        static::deleting(function ($model) {
            if (Auth::check() && method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                // Soft delete
                if (in_array('deleted_by', $model->getFillable())) {
                    $model->deleted_by = Auth::id();
                    $model->saveQuietly(); // Save without triggering events
                }
            } elseif (Auth::check() && !method_exists($model, 'isForceDeleting')) {
                // Hard delete (no soft deletes)
                if (in_array('deleted_by', $model->getFillable())) {
                    $model->deleted_by = Auth::id();
                    $model->saveQuietly(); // Save before deletion
                }
            }
        });
    }

    /**
     * Get the user who created this model
     */
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Get the user who last updated this model
     */
    public function updater()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    /**
     * Get the user who deleted this model
     */
    public function deleter()
    {
        return $this->belongsTo(\App\Models\User::class, 'deleted_by');
    }
}
