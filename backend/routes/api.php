<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LogEntryController;
use App\Http\Controllers\MediaItemController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\UserMediaController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return UserResource::make($request->user());
})->middleware('auth:sanctum');

Route::post('/invitations', [InvitationController::class, 'store'])->middleware('auth:sanctum');
Route::get('/invitations/{token}', [InvitationController::class, 'show']);
Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->middleware('auth:sanctum');
Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->middleware('auth:sanctum');

// Cataloging from an external source is open to any authenticated member;
// only manual entry is role-gated (see StoreManualMediaItemRequest). The
// search endpoint carries its own rate limit, independent of manual entry,
// purely to stop a runaway client from burning external API quota — not as
// abuse defense against a hostile user. See
// docs/adr/0001-media-source-integration-architecture.md.
Route::get('/media-items/search', [MediaItemController::class, 'search'])->middleware(['auth:sanctum', 'throttle:30,1']);
Route::post('/media-items', [MediaItemController::class, 'store'])->middleware('auth:sanctum');
Route::post('/media-items/manual', [MediaItemController::class, 'storeManual'])->middleware('auth:sanctum');
// Must stay registered after the /media-items/search literal route above,
// or {mediaItem} would greedily capture "search" as an id.
Route::get('/media-items/{mediaItem}', [MediaItemController::class, 'show'])->middleware('auth:sanctum');
Route::get('/media-items/{mediaItem}/log-entries', [MediaItemController::class, 'logEntries'])->middleware('auth:sanctum');
Route::get('/media-items/{mediaItem}/episodes', [MediaItemController::class, 'episodes'])->middleware('auth:sanctum');

// Shelving and logging apply equally regardless of role — ownership-only
// authorization, no permission gate. See
// docs/planning/06-roles-permissions.md and
// docs/adr/0003-shelf-status-and-logging-are-decoupled.md.
Route::get('/user-media', [UserMediaController::class, 'index'])->middleware('auth:sanctum');
Route::post('/user-media', [UserMediaController::class, 'store'])->middleware('auth:sanctum');
Route::patch('/user-media/{userMedia}', [UserMediaController::class, 'update'])->middleware('auth:sanctum');
Route::delete('/user-media/{userMedia}', [UserMediaController::class, 'destroy'])->middleware('auth:sanctum');

Route::post('/log-entries', [LogEntryController::class, 'store'])->middleware('auth:sanctum');

// Comments/Reactions attach to a Log Entry but only make sense when it has a
// Review (enforced in the Form Request, not the route); visibility is
// enforced in the controller via UserPolicy::viewPrivateContent, same gate
// as any other content on the review's author. See
// docs/planning/06-roles-permissions.md and GLOSSARY.md "Comment"/"Reaction".
Route::get('/log-entries/{logEntry}/comments', [CommentController::class, 'index'])->middleware('auth:sanctum');
Route::post('/log-entries/{logEntry}/comments', [CommentController::class, 'store'])->middleware('auth:sanctum');
Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->middleware('auth:sanctum');

Route::get('/log-entries/{logEntry}/reactions', [ReactionController::class, 'index'])->middleware('auth:sanctum');
Route::post('/log-entries/{logEntry}/reactions', [ReactionController::class, 'store'])->middleware('auth:sanctum');
Route::delete('/log-entries/{logEntry}/reactions/{emoji}', [ReactionController::class, 'destroy'])->middleware('auth:sanctum');

// Friend requests apply equally regardless of role, no permission gate. See
// docs/planning/06-roles-permissions.md and GLOSSARY.md "Friendship".
Route::get('/friendships', [FriendshipController::class, 'index'])->middleware('auth:sanctum');
Route::post('/friendships', [FriendshipController::class, 'store'])->middleware('auth:sanctum');
Route::post('/friendships/{friendship}/accept', [FriendshipController::class, 'accept'])->middleware('auth:sanctum');
Route::post('/friendships/{friendship}/decline', [FriendshipController::class, 'decline'])->middleware('auth:sanctum');
Route::delete('/friendships/{friendship}', [FriendshipController::class, 'destroy'])->middleware('auth:sanctum');

Route::patch('/profile/visibility', [ProfileController::class, 'updateVisibility'])->middleware('auth:sanctum');

// Must stay registered after /members/search, or {user} would greedily
// capture "search" as an id. Discoverability isn't gated by Profile
// Visibility; only a member's shelf/diary/friend-list content is.
Route::get('/members/search', [MemberController::class, 'search'])->middleware('auth:sanctum');
Route::get('/members/{user}', [MemberController::class, 'show'])->middleware('auth:sanctum');

Route::get('/activity/feed', [ActivityController::class, 'feed'])->middleware('auth:sanctum');
Route::get('/activity/new-from-friends', [ActivityController::class, 'newFromFriends'])->middleware('auth:sanctum');

// See GLOSSARY.md "Notification" and docs/adr/0007-notifications-via-domain-events-and-queued-listeners.md.
Route::get('/notifications', [NotificationController::class, 'index'])->middleware('auth:sanctum');
Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->middleware('auth:sanctum');
Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->middleware('auth:sanctum');
Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->middleware('auth:sanctum');
