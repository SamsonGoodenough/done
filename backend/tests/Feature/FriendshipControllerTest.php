<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendshipControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/friendships')->assertUnauthorized();
    }

    public function test_store_sends_a_pending_request(): void
    {
        $requester = User::factory()->create();
        $addressee = User::factory()->create();

        $response = $this->actingAs($requester)->postJson('/api/friendships', [
            'addressee_id' => $addressee->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseHas('friendships', [
            'requester_id' => $requester->id,
            'addressee_id' => $addressee->id,
            'status' => 'pending',
        ]);
    }

    public function test_store_rejects_a_request_to_yourself(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/friendships', [
            'addressee_id' => $user->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('addressee_id');
    }

    public function test_a_crossing_request_auto_accepts_the_existing_one(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $first = $this->actingAs($a)->postJson('/api/friendships', ['addressee_id' => $b->id])
            ->assertCreated()->json('data.id');

        $response = $this->actingAs($b)->postJson('/api/friendships', ['addressee_id' => $a->id]);

        $response->assertOk()->assertJsonPath('data.id', $first)->assertJsonPath('data.status', 'accepted');
        $this->assertDatabaseCount('friendships', 1);
        $this->assertDatabaseHas('friendships', ['id' => $first, 'status' => 'accepted']);
    }

    public function test_accept_requires_being_the_addressee(): void
    {
        $requester = User::factory()->create();
        $addressee = User::factory()->create();
        $friendship = Friendship::create([
            'requester_id' => $requester->id,
            'addressee_id' => $addressee->id,
            'status' => 'pending',
        ]);

        $this->actingAs($requester)
            ->postJson("/api/friendships/{$friendship->id}/accept")
            ->assertForbidden();
    }

    public function test_addressee_can_accept_a_pending_request(): void
    {
        $requester = User::factory()->create();
        $addressee = User::factory()->create();
        $friendship = Friendship::create([
            'requester_id' => $requester->id,
            'addressee_id' => $addressee->id,
            'status' => 'pending',
        ]);

        $this->actingAs($addressee)
            ->postJson("/api/friendships/{$friendship->id}/accept")
            ->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('friendships', ['id' => $friendship->id, 'status' => 'accepted']);
    }

    public function test_cannot_accept_an_already_accepted_friendship(): void
    {
        $requester = User::factory()->create();
        $addressee = User::factory()->create();
        $friendship = Friendship::create([
            'requester_id' => $requester->id,
            'addressee_id' => $addressee->id,
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        $this->actingAs($addressee)
            ->postJson("/api/friendships/{$friendship->id}/accept")
            ->assertStatus(409);
    }

    public function test_addressee_can_decline_a_pending_request_and_re_request_immediately(): void
    {
        $requester = User::factory()->create();
        $addressee = User::factory()->create();
        $friendship = Friendship::create([
            'requester_id' => $requester->id,
            'addressee_id' => $addressee->id,
            'status' => 'pending',
        ]);

        $this->actingAs($addressee)
            ->postJson("/api/friendships/{$friendship->id}/decline")
            ->assertNoContent();

        $this->assertSoftDeleted('friendships', ['id' => $friendship->id, 'status' => 'declined']);

        // No cooldown: a fresh request from the same requester works right away.
        $response = $this->actingAs($requester)->postJson('/api/friendships', [
            'addressee_id' => $addressee->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.id', $friendship->id)->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseCount('friendships', 1);
    }

    public function test_requester_cannot_decline_their_own_outgoing_request(): void
    {
        $requester = User::factory()->create();
        $addressee = User::factory()->create();
        $friendship = Friendship::create([
            'requester_id' => $requester->id,
            'addressee_id' => $addressee->id,
            'status' => 'pending',
        ]);

        $this->actingAs($requester)
            ->postJson("/api/friendships/{$friendship->id}/decline")
            ->assertForbidden();
    }

    public function test_either_party_can_unfriend_an_accepted_friendship(): void
    {
        $requester = User::factory()->create();
        $addressee = User::factory()->create();
        $friendship = Friendship::create([
            'requester_id' => $requester->id,
            'addressee_id' => $addressee->id,
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        $this->actingAs($addressee)
            ->deleteJson("/api/friendships/{$friendship->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('friendships', ['id' => $friendship->id, 'status' => 'accepted']);
    }

    public function test_cannot_unfriend_a_pending_request(): void
    {
        $requester = User::factory()->create();
        $addressee = User::factory()->create();
        $friendship = Friendship::create([
            'requester_id' => $requester->id,
            'addressee_id' => $addressee->id,
            'status' => 'pending',
        ]);

        $this->actingAs($addressee)
            ->deleteJson("/api/friendships/{$friendship->id}")
            ->assertStatus(409);
    }

    public function test_an_outsider_cannot_unfriend_someone_elses_friendship(): void
    {
        $requester = User::factory()->create();
        $addressee = User::factory()->create();
        $outsider = User::factory()->create();
        $friendship = Friendship::create([
            'requester_id' => $requester->id,
            'addressee_id' => $addressee->id,
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        $this->actingAs($outsider)
            ->deleteJson("/api/friendships/{$friendship->id}")
            ->assertForbidden();
    }

    public function test_index_returns_friendships_in_either_direction_filterable_by_status(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $third = User::factory()->create();

        Friendship::create(['requester_id' => $user->id, 'addressee_id' => $other->id, 'status' => 'pending']);
        Friendship::create(['requester_id' => $third->id, 'addressee_id' => $user->id, 'status' => 'accepted', 'responded_at' => now()]);

        $all = $this->actingAs($user)->getJson('/api/friendships')->assertOk();
        $this->assertCount(2, $all->json('data'));

        $pending = $this->actingAs($user)->getJson('/api/friendships?status=pending')->assertOk();
        $this->assertCount(1, $pending->json('data'));
    }
}
