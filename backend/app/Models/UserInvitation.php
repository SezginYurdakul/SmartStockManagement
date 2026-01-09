<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserInvitation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'email',
        'token',
        'invited_by',
        'role_ids',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'role_ids' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            if (empty($invitation->token)) {
                $invitation->token = Str::random(64);
            }
        });
    }

    /**
     * Get the company that this invitation belongs to
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who sent the invitation
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Check if invitation is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if invitation is already accepted
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Check if invitation is valid (not expired and not accepted)
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isAccepted();
    }

    /**
     * Mark invitation as accepted
     */
    public function markAsAccepted(): void
    {
        $this->update(['accepted_at' => now()]);
    }

    /**
     * Scope: Get valid invitations
     */
    public function scopeValid($query)
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope: Get expired invitations
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
            ->whereNull('accepted_at');
    }

    /**
     * Scope: Get accepted invitations
     */
    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at');
    }
}
