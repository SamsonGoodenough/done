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

        Mail::to($invitation->email)->queue(new InvitationMail($invitation, $rawToken));

        return InvitationResource::make($invitation);
    }

    public function show(string $token): InvitationResource
    {
        $invitation = $this->findPendingOrFail($token);

        return InvitationResource::make($invitation);
    }

    public function accept(string $token, AcceptInvitationRequest $request): UserResource
    {
        $invitation = $this->findPendingOrFail($token);

        $user = User::create([
            'name' => $request->string('name')->value(),
            'email' => $invitation->email,
            'password' => $request->string('password')->value(),
        ])->refresh();
        $user->assignRole($invitation->role->value);

        $invitation->forceFill(['accepted_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return UserResource::make($user);
    }

    public function resend(Invitation $invitation): InvitationResource
    {
        $this->authorize('manage', $invitation);

        if (! $invitation->isPending()) {
            abort(410, 'This invitation is no longer pending: it was already accepted or has expired.');
        }

        $rawToken = Invitation::generateRawToken();

        $invitation->forceFill([
            'token' => Invitation::hashToken($rawToken),
            'expires_at' => now()->addDays(7),
        ])->save();

        Mail::to($invitation->email)->queue(new InvitationMail($invitation, $rawToken));

        return InvitationResource::make($invitation);
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

        if ($invitation->accepted_at !== null) {
            abort(410, 'This invitation has already been used.');
        }

        if ($invitation->expires_at->isPast()) {
            abort(410, 'This invitation has expired.');
        }

        return $invitation;
    }
}
