<?php

namespace Tests\Feature;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Enums\UserRole;
use App\Models\MediaItem;
use App\Models\User;
use App\Services\MediaSources\Exceptions\MediaNotFoundException;
use App\Services\MediaSources\Exceptions\MediaSourceUnavailableException;
use App\Services\MediaSources\MediaDetails;
use App\Services\MediaSources\MediaSourceClient;
use App\Services\MediaSources\Providers\TmdbClient;
use App\Services\MediaSources\SearchResult;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaItemControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function manager(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Manager->value);

        return $user;
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Member->value);

        return $user;
    }

    /** Binds a fake client in place of the real TmdbClient for Movie/TvShow dispatch. */
    private function fakeMovieClient(MediaSourceClient $client): void
    {
        $this->app->instance(TmdbClient::class, $client);
    }

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/media-items/search?type=movie&query=matrix')
            ->assertUnauthorized();
    }

    public function test_search_flags_results_already_in_the_catalog(): void
    {
        $existing = MediaItem::create([
            'type' => MediaType::Movie,
            'source' => MediaSource::Tmdb,
            'external_id' => '603',
            'title' => 'The Matrix',
        ]);

        $this->fakeMovieClient(new class implements MediaSourceClient
        {
            public function search(string $query, MediaType $type): array
            {
                return [
                    new SearchResult(MediaType::Movie, MediaSource::Tmdb, '603', 'The Matrix', '1999-03-31', null, null),
                    new SearchResult(MediaType::Movie, MediaSource::Tmdb, '604', 'The Matrix Reloaded', '2003-05-15', null, null),
                ];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                throw new \RuntimeException('not used in this test');
            }
        });

        $response = $this->actingAs($this->member())
            ->getJson('/api/media-items/search?type=movie&query=matrix')
            ->assertOk();

        $response->assertJsonPath('data.0.external_id', '603');
        $response->assertJsonPath('data.0.media_item_id', $existing->id);
        $response->assertJsonPath('data.1.external_id', '604');
        $response->assertJsonPath('data.1.media_item_id', null);
    }

    public function test_store_from_source_catalogs_a_new_item(): void
    {
        $this->fakeMovieClient(new class implements MediaSourceClient
        {
            public function search(string $query, MediaType $type): array
            {
                return [];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                return new MediaDetails(
                    MediaType::Movie,
                    MediaSource::Tmdb,
                    $externalId,
                    'The Matrix',
                    '1999-03-31',
                    'https://image.tmdb.org/t/p/w500/poster.jpg',
                    'A hacker discovers reality is a simulation.',
                    ['runtime' => 136, 'genres' => ['Action', 'Science Fiction']],
                );
            }
        });

        $response = $this->actingAs($this->member())->postJson('/api/media-items', [
            'type' => 'movie',
            'external_id' => '603',
        ]);

        $response->assertCreated()->assertJsonPath('data.title', 'The Matrix');
        $this->assertDatabaseHas('media_items', ['source' => 'tmdb', 'external_id' => '603']);
    }

    public function test_store_from_source_returns_an_already_cataloged_item_without_duplicating(): void
    {
        $existing = MediaItem::create([
            'type' => MediaType::Movie,
            'source' => MediaSource::Tmdb,
            'external_id' => '603',
            'title' => 'The Matrix',
        ]);

        $this->fakeMovieClient(new class implements MediaSourceClient
        {
            public function search(string $query, MediaType $type): array
            {
                return [];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                return new MediaDetails(MediaType::Movie, MediaSource::Tmdb, $externalId, 'The Matrix', null, null, null, []);
            }
        });

        $response = $this->actingAs($this->member())->postJson('/api/media-items', [
            'type' => 'movie',
            'external_id' => '603',
        ]);

        $response->assertOk()->assertJsonPath('data.id', $existing->id);
        $this->assertDatabaseCount('media_items', 1);
    }

    public function test_store_from_source_maps_not_found_to_404(): void
    {
        $this->fakeMovieClient(new class implements MediaSourceClient
        {
            public function search(string $query, MediaType $type): array
            {
                return [];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                throw MediaNotFoundException::forExternalId(MediaSource::Tmdb, $externalId);
            }
        });

        $this->actingAs($this->member())
            ->postJson('/api/media-items', ['type' => 'movie', 'external_id' => 'does-not-exist'])
            ->assertNotFound();
    }

    public function test_store_from_source_maps_unavailable_to_503(): void
    {
        $this->fakeMovieClient(new class implements MediaSourceClient
        {
            public function search(string $query, MediaType $type): array
            {
                return [];
            }

            public function details(string $externalId, MediaType $type): MediaDetails
            {
                throw MediaSourceUnavailableException::for(MediaSource::Tmdb);
            }
        });

        $this->actingAs($this->member())
            ->postJson('/api/media-items', ['type' => 'movie', 'external_id' => '603'])
            ->assertStatus(503);
    }

    public function test_member_cannot_manually_add_a_media_item(): void
    {
        $this->actingAs($this->member())->postJson('/api/media-items/manual', [
            'type' => 'book',
            'title' => 'An Obscure Zine',
        ])->assertForbidden();
    }

    public function test_manager_can_manually_add_a_media_item_with_minimal_fields(): void
    {
        $response = $this->actingAs($this->manager())->postJson('/api/media-items/manual', [
            'type' => 'book',
            'title' => 'An Obscure Zine',
        ]);

        $response->assertCreated()->assertJsonPath('data.title', 'An Obscure Zine');
        $this->assertDatabaseHas('media_items', [
            'title' => 'An Obscure Zine',
            'source' => 'manual',
            'external_id' => null,
        ]);
    }

    public function test_admin_can_manually_add_a_media_item(): void
    {
        $this->actingAs($this->admin())->postJson('/api/media-items/manual', [
            'type' => 'game',
            'title' => 'An Obscure Prototype',
        ])->assertCreated();
    }

    public function test_manual_entry_requires_a_title(): void
    {
        $this->actingAs($this->manager())
            ->postJson('/api/media-items/manual', ['type' => 'book'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }
}
