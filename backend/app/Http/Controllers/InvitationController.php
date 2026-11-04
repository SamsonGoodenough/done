<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\SendInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Http\Resources\UserResource;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class InvitationController extends Controller
{
    public function store(SendInvitationRequest $request): InvitationResource
    {
        $rawToken = Invitation::generateRawToken();

        $invitation = Invitation::create([
            'email' => $request->string('email')->value(),
            'token' => Invitation::hashToken($rawToken),
            'role' => UserRole::from($request->string('role')->value()),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);
        // Avoids a round trip for InvitationResource's invited_by: we already
        // have the inviter in hand, it's just the request's authenticated user.
        $invitation->setRelation('inviter', $request->user());

        Mail::to($invitation->email)->queue(new InvitationMail($invitation, $rawToken));

        return InvitationResource::make($invitation);
    }

    public function show(string $token): InvitationResource
    {
        $invitation = $this->findPendingOrFail($token)->load('inviter');

        return InvitationResource::make($invitation);
    }

    public function accept(string $token, AcceptInvitationRequest $request): UserResource
    {
        $invitation = $this->findPendingOrFail($token);

        $user = DB::transaction(function () use ($invitation, $request) {
            $user = User::create([
                'name' => $request->string('name')->value(),
                'email' => $invitation->email,
                'password' => $request->string('password')->value(),
            ])->refresh();
            $user->assignRole($invitation->role->value);

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return UserResource::make($user);
    }

    public function resend(Invitation $invitation): InvitationResource
    {
        $this->authorize('manage', $invitation);

        // Deliberately checks hasBeenAccepted() only, not isPending(): an
        // expired-but-unaccepted invitation is exactly the case resend
        // exists for (see docs/planning/06-roles-permissions.md, "before
        // it's accepted" has no expiry condition).
        if ($invitation->hasBeenAccepted()) {
            abort(410, 'This invitation has already been used.');
        }

        $rawToken = Invitation::generateRawToken();

        $invitation->forceFill([
            'token' => Invitation::hashToken($rawToken),
            'expires_at' => now()->addDays(7),
        ])->save();

        Mail::to($invitation->email)->queue(new InvitationMail($invitation, $rawToken));

        return InvitationResource::make($invitation->load('inviter'));
    }

    public function destroy(Invitation $invitation): JsonResponse
    {
        $this->authorize('manage', $invitation);

        $invitation->delete();

        return response()->json(status: 204);
    }

    private function findPendingOrFail(string $token): Invitation
    {
        $invitation = Invitation::findByRawToken($token);

        abort_if($invitation === null, 404, 'Invitation not found.');

        if ($invitation->hasBeenAccepted()) {
            abort(410, 'This invitation has already been used.');
        }

        if ($invitation->hasExpired()) {
            abort(410, 'This invitation has expired.');
        }

        return $invitation;
    }
}
