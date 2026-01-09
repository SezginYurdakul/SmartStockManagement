<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserInvitationMail;
use Illuminate\Support\Str;
use Exception;

class InvitationService
{
    /**
     * Default expiration days for invitations
     */
    private const DEFAULT_EXPIRATION_DAYS = 7;

    /**
     * Send invitation to a user
     */
    public function sendInvitation(array $data): UserInvitation
    {
        $inviter = Auth::user();
        $companyId = $inviter->company_id;

        if (!$companyId) {
            throw new BusinessException('User must belong to a company to send invitations.');
        }

        $email = $data['email'];
        $roleIds = $data['role_ids'] ?? [];
        $expirationDays = $data['expiration_days'] ?? self::DEFAULT_EXPIRATION_DAYS;

        // Check if user already exists (including soft deleted)
        $existingUser = User::withTrashed()->where('email', $email)->first();
        
        if ($existingUser) {
            if ($existingUser->deleted_at === null) {
                throw new BusinessException('A user with this email already exists.');
            }
            
            // User is soft deleted - check if they belong to the same company
            if ($existingUser->company_id !== $companyId) {
                throw new BusinessException('A user with this email was previously deactivated in another company.');
            }
            
            // User belongs to same company - we can restore them instead
            throw new BusinessException('A user with this email was previously deactivated. Please restore the user instead of sending an invitation.');
        }

        // Check if there's a pending invitation for this email
        $pendingInvitation = UserInvitation::where('email', $email)
            ->where('company_id', $companyId)
            ->valid()
            ->first();

        if ($pendingInvitation) {
            throw new BusinessException('An invitation has already been sent to this email address.');
        }

        Log::info('Sending user invitation', [
            'email' => $email,
            'company_id' => $companyId,
            'invited_by' => $inviter->id,
        ]);

        DB::beginTransaction();

        try {
            // Cancel any expired invitations for this email
            UserInvitation::where('email', $email)
                ->where('company_id', $companyId)
                ->expired()
                ->delete();

            $invitation = UserInvitation::create([
                'company_id' => $companyId,
                'email' => $email,
                'token' => Str::random(64),
                'invited_by' => $inviter->id,
                'role_ids' => $roleIds,
                'expires_at' => now()->addDays($expirationDays),
            ]);

            // Send invitation email
            $this->sendInvitationEmail($invitation);

            DB::commit();

            Log::info('Invitation sent successfully', [
                'invitation_id' => $invitation->id,
                'email' => $email,
            ]);

            return $invitation->load(['company', 'inviter']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to send invitation', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Accept invitation and create user account
     */
    public function acceptInvitation(string $token, array $data): User
    {
        $invitation = UserInvitation::where('token', $token)->first();

        if (!$invitation) {
            throw new BusinessException('Invalid invitation token.');
        }

        if ($invitation->isExpired()) {
            throw new BusinessException('This invitation has expired. Please request a new invitation.');
        }

        if ($invitation->isAccepted()) {
            throw new BusinessException('This invitation has already been accepted.');
        }

        // Validate email matches
        if ($invitation->email !== $data['email']) {
            throw new BusinessException('Email does not match the invitation.');
        }

        // Check if user already exists
        $existingUser = User::where('email', $invitation->email)->first();
        if ($existingUser) {
            throw new BusinessException('A user with this email already exists.');
        }

        Log::info('Accepting invitation', [
            'invitation_id' => $invitation->id,
            'email' => $invitation->email,
        ]);

        DB::beginTransaction();

        try {
            // Create user
            $user = User::create([
                'company_id' => $invitation->company_id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $invitation->email,
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);

            // Assign roles if provided
            if (!empty($invitation->role_ids)) {
                $user->roles()->attach($invitation->role_ids);
            }

            // Mark invitation as accepted
            $invitation->markAsAccepted();

            DB::commit();

            // Load relationships
            $user->load('roles');

            Log::info('Invitation accepted and user created', [
                'user_id' => $user->id,
                'invitation_id' => $invitation->id,
            ]);

            return $user;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to accept invitation', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Resend invitation
     */
    public function resendInvitation(int $invitationId): UserInvitation
    {
        $invitation = UserInvitation::findOrFail($invitationId);

        // Check permissions (inviter or admin)
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user || !($user instanceof User)) {
            throw new BusinessException('User must be authenticated to resend invitations.');
        }
        
        if ($invitation->invited_by !== $user->id && !$user->hasPermission('users.create')) {
            throw new BusinessException('You do not have permission to resend this invitation.');
        }

        if ($invitation->isAccepted()) {
            throw new BusinessException('Cannot resend an already accepted invitation.');
        }

        // Extend expiration if needed
        if ($invitation->isExpired()) {
            $invitation->update([
                'expires_at' => now()->addDays(self::DEFAULT_EXPIRATION_DAYS),
            ]);
        }

        // Send email
        $this->sendInvitationEmail($invitation);

        Log::info('Invitation resent', [
            'invitation_id' => $invitation->id,
        ]);

        return $invitation->fresh(['company', 'inviter']);
    }

    /**
     * Cancel invitation
     */
    public function cancelInvitation(int $invitationId): bool
    {
        $invitation = UserInvitation::findOrFail($invitationId);

        // Check permissions
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user || !($user instanceof User)) {
            throw new BusinessException('User must be authenticated to cancel invitations.');
        }
        
        if ($invitation->invited_by !== $user->id && !$user->hasPermission('users.delete')) {
            throw new BusinessException('You do not have permission to cancel this invitation.');
        }

        if ($invitation->isAccepted()) {
            throw new BusinessException('Cannot cancel an already accepted invitation.');
        }

        Log::info('Cancelling invitation', [
            'invitation_id' => $invitation->id,
        ]);

        return $invitation->delete();
    }

    /**
     * Get invitation by token (public endpoint)
     */
    public function getInvitationByToken(string $token): ?UserInvitation
    {
        $invitation = UserInvitation::where('token', $token)
            ->with(['company', 'inviter'])
            ->first();

        if (!$invitation) {
            return null;
        }

        return $invitation;
    }

    /**
     * Send invitation email
     */
    private function sendInvitationEmail(UserInvitation $invitation): void
    {
        try {
            // Load relationships for email
            $invitation->load(['company', 'inviter']);

            // Send email using Mailable
            Mail::to($invitation->email)
                ->send(new UserInvitationMail($invitation));

            Log::info('Invitation email sent', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send invitation email', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw - invitation is still created
            // Email can be resent later
        }
    }
}
