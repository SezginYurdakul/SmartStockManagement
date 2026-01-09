<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    protected $invitationService;

    public function __construct(InvitationService $invitationService)
    {
        $this->invitationService = $invitationService;
    }

    /**
     * Send invitation to a user
     * Required permission: users.create (checked by middleware)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role_ids' => 'required|array|min:1',
            'role_ids.*' => 'required|integer|exists:roles,id',
            'expiration_days' => 'sometimes|integer|min:1|max:30',
        ]);

        try {
            $invitation = $this->invitationService->sendInvitation($validated);

            return response()->json([
                'message' => 'Invitation sent successfully',
                'data' => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'token' => $invitation->token, // Include token for testing
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'invited_by' => [
                        'id' => $invitation->inviter->id,
                        'name' => $invitation->inviter->full_name,
                    ],
                ],
            ], 201);

        } catch (\App\Exceptions\BusinessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get invitation details by token (public endpoint)
     * Supports both path parameter and query parameter: /accept/{token} or /accept?token=...
     */
    public function show(Request $request, ?string $token = null): JsonResponse
    {
        // Get token from path parameter or query parameter
        $token = $token ?? $request->query('token');
        
        if (!$token) {
            return response()->json([
                'message' => 'Invitation token is required.',
            ], 400);
        }

        $invitation = $this->invitationService->getInvitationByToken($token);

        if (!$invitation) {
            return response()->json([
                'message' => 'Invalid invitation token.',
            ], 404);
        }

        if ($invitation->isExpired()) {
            return response()->json([
                'message' => 'This invitation has expired.',
                'error' => 'invitation_expired',
            ], 410);
        }

        if ($invitation->isAccepted()) {
            return response()->json([
                'message' => 'This invitation has already been accepted.',
                'error' => 'invitation_accepted',
            ], 410);
        }

        return response()->json([
            'data' => [
                'email' => $invitation->email,
                'company' => [
                    'id' => $invitation->company->id,
                    'name' => $invitation->company->name,
                ],
                'invited_by' => [
                    'name' => $invitation->inviter->full_name,
                ],
                'expires_at' => $invitation->expires_at->toISOString(),
            ],
        ]);
    }

    /**
     * Accept invitation and create user account (public endpoint)
     * Supports both path parameter and query parameter: /accept/{token} or /accept?token=...
     */
    public function accept(Request $request, ?string $token = null): JsonResponse
    {
        // Get token from path parameter or query parameter
        $token = $token ?? $request->query('token');
        
        if (!$token) {
            return response()->json([
                'message' => 'Invitation token is required.',
            ], 400);
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $user = $this->invitationService->acceptInvitation($token, $validated);

            // Create token for immediate login
            $authToken = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Account created successfully',
                'user' => UserResource::make($user),
                'access_token' => $authToken,
                'token_type' => 'Bearer',
            ], 201);

        } catch (\App\Exceptions\BusinessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Resend invitation
     * Permission: users.create OR user is the inviter
     */
    public function resend(Request $request, int $id): JsonResponse
    {
        // Permission check is done in service (allows inviter to resend their own invitations)
        // No need to check here, service will handle it
        
        try {
            $invitation = $this->invitationService->resendInvitation($id);

            return response()->json([
                'message' => 'Invitation resent successfully',
                'data' => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'expires_at' => $invitation->expires_at->toISOString(),
                ],
            ]);

        } catch (\App\Exceptions\BusinessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel invitation
     * Required permission: users.delete
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('users.delete')) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to cancel invitations.',
            ], 403);
        }

        try {
            $this->invitationService->cancelInvitation($id);

            return response()->json([
                'message' => 'Invitation cancelled successfully',
            ]);

        } catch (\App\Exceptions\BusinessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * List invitations (for company admin)
     * Required permission: users.view
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('users.view')) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to view invitations.',
            ], 403);
        }

        $companyId = $request->user()->company_id;
        $perPage = $request->input('per_page', 15);

        $invitations = \App\Models\UserInvitation::where('company_id', $companyId)
            ->with(['inviter', 'company'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $invitations->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'invited_by' => [
                        'id' => $invitation->inviter->id,
                        'name' => $invitation->inviter->full_name,
                    ],
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'accepted_at' => $invitation->accepted_at?->toISOString(),
                    'is_expired' => $invitation->isExpired(),
                    'is_accepted' => $invitation->isAccepted(),
                    'is_valid' => $invitation->isValid(),
                    'created_at' => $invitation->created_at->toISOString(),
                ];
            }),
            'meta' => [
                'current_page' => $invitations->currentPage(),
                'last_page' => $invitations->lastPage(),
                'per_page' => $invitations->perPage(),
                'total' => $invitations->total(),
            ],
        ]);
    }
}
