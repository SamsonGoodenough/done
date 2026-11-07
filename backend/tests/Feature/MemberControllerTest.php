<?php

namespace Tests\Feature;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\Friendship;
use App\Models\MediaItem;
use App\Models\User;
use App\Models\UserMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mediaItem(): MediaItem
    {
        return MediaItem::create([
            'type' => MediaType::Movie,
            'source' => MediaSource::Tmdb,
            'external_id' => (string) fake()->unique()->randomNumber(6),
            'title' => fake()->sentence(3),
        ]);
    }

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/members/search')->assertUnauthorized();
    }

    public function test_search_excludes_the_caller_and_returns_no_relationship_by_default(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($viewer)->getJson('/api/members/search')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($viewer->id));
        $this->assertTrue($ids->contains($other->id));
        $this->assertNull($this->firstRelationshipStatus($response, $other->id));
    }

    private function firstRelationshipStatus($response, int $id): ?string
    {
        $row = collect($response->json('data'))->firstWhere('id', $id);

        return $row['relationship_status'] ?? null;
    }

    public function test_search_filters_by_name(): void
    {
        $viewer = User::factory()->create();
        User::factory()->create(['name' => 'Zelda Match']);
        User::factory()->create(['name' => 'Someone Else']);

        $response = $this->actingAs($viewer)->getJson('/api/members/search?query=zelda')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Zelda Match', $response->json('data.0.name'));
    }

    public function test_search_reports_pending_sent_pending_received_and_friends(): void
    {
        $viewer = User::factory()->create();
        $sentTo = User::factory()->create();
        $receivedFrom = User::factory()->create();
        $friend = User::factory()->create();

        Friendship::create(['requester_id' => $viewer->id, 'addressee_id' => $sentTo->id, 'status' => 'pending']);
        Friendship::create(['requester_id' => $receivedFrom->id, 'addressee_id' => $viewer->id, 'status' => 'pending']);
        Friendship::create(['requester_id' => $viewer->id, 'addressee_id' => $friend->id, 'status' => 'accepted', 'responded_at' => now()]);

        $response = $this->actingAs($viewer)->getJson('/api/members/search')->assertOk();

        $this->assertSame('pending_sent', $this->firstRelationshipStatus($response, $sentTo->id));
        $this->assertSame('pending_received', $this->firstRelationshipStatus($response, $receivedFrom->id));
        $this->assertSame('friends', $this->firstRelationshipStatus($response, $friend->id));
    }

    public function test_show_requires_authentication(): void
    {
        $target = User::factory()->create();
        $this->getJson("/api/members/{$target->id}")->assertUnauthorized();
    }

    public function test_show_always_exposes_basic_info_for_a_private_non_friend(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create(['profile_visibility' => 'private', 'name' => 'Private Person']);

        $response = $this->actingAs($viewer)->getJson("/api/members/{$target->id}")->assertOk();

        $response->assertJsonPath('data.name', 'Private Person')
            ->assertJsonPath('data.profile_visibility', 'private')
            ->assertJsonPath('data.can_view_content', false);
        $this->assertArrayNotHasKey('shelf', $response->json('data'));
    }

    public function test_show_exposes_content_for_a_public_profile_to_a_non_friend(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create(['profile_visibility' => 'public']);
        UserMedia::create(['user_id' => $target->id, 'media_item_id' => $this->mediaItem()->id, 'status' => 'planned']);

        $response = $this->actingAs($viewer)->getJson("/api/members/{$target->id}")->assertOk();

        $response->assertJsonPath('data.can_view_content', true);
        $this->assertCount(1, $response->json('data.shelf'));
    }

    public function test_show_exposes_content_for_a_private_profile_to_an_accepted_friend(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create(['profile_visibility' => 'private']);
        Friendship::create(['requester_id' => $viewer->id, 'addressee_id' => $target->id, 'status' => 'accepted', 'responded_at' => now()]);
        UserMedia::create(['user_id' => $target->id, 'media_item_id' => $this->mediaItem()->id, 'status' => 'planned']);

        $response = $this->actingAs($viewer)->getJson("/api/members/{$target->id}")->assertOk();

        $response->assertJsonPath('data.can_view_content', true)->assertJsonPath('data.relationship_status', 'friends');
        $this->assertCount(1, $response->json('data.shelf'));
    }

    public function test_show_exposes_own_content_regardless_of_visibility(): void
    {
        $user = User::factory()->create(['profile_visibility' => 'private']);
        UserMedia::create(['user_id' => $user->id, 'media_item_id' => $this->mediaItem()->id, 'status' => 'planned']);

        $response = $this->actingAs($user)->getJson("/api/members/{$user->id}")->assertOk();

        $response->assertJsonPath('data.can_view_content', true);
        $this->assertCount(1, $response->json('data.shelf'));
    }

    public function test_show_friend_list_is_gated_the_same_as_shelf_for_a_private_profile(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create(['profile_visibility' => 'private']);
        $targetsFriend = User::factory()->create();
        Friendship::create(['requester_id' => $target->id, 'addressee_id' => $targetsFriend->id, 'status' => 'accepted', 'responded_at' => now()]);

        $notFriendsResponse = $this->actingAs($viewer)->getJson("/api/members/{$target->id}")->assertOk();
        $this->assertArrayNotHasKey('friends', $notFriendsResponse->json('data'));

        Friendship::create(['requester_id' => $viewer->id, 'addressee_id' => $target->id, 'status' => 'accepted', 'responded_at' => now()]);

        // Now that the viewer is also one of $target's friends, the list
        // correctly includes both: the viewer themselves and $targetsFriend.
        $friendsResponse = $this->actingAs($viewer)->getJson("/api/members/{$target->id}")->assertOk();
        $friendIds = collect($friendsResponse->json('data.friends'))->pluck('id');
        $this->assertCount(2, $friendIds);
        $this->assertTrue($friendIds->contains($targetsFriend->id));
        $this->assertTrue($friendIds->contains($viewer->id));
    }
}
