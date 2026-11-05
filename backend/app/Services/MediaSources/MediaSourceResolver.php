<?php

namespace App\Services\MediaSources;

use App\Enums\MediaType;
use App\Services\MediaSources\Providers\IgdbClient;
use App\Services\MediaSources\Providers\OpenLibraryClient;
use App\Services\MediaSources\Providers\TmdbClient;
use Illuminate\Container\Container;
use RuntimeException;

/**
 * The one place that knows which vendor backs each MediaType. Swapping a
 * vendor (e.g. a future games_api move off IGDB) only means changing this
 * map, never MediaSourceClient callers or the MediaSource enum. See
 * docs/adr/0001-media-source-integration-architecture.md.
 */
class MediaSourceResolver
{
    /** @var array<string, class-string<MediaSourceClient>> */
    private array $clients = [
        MediaType::Movie->value => TmdbClient::class,
        MediaType::TvShow->value => TmdbClient::class,
        MediaType::Game->value => IgdbClient::class,
        MediaType::Book->value => OpenLibraryClient::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function forType(MediaType $type): MediaSourceClient
    {
        $class = $this->clients[$type->value]
            ?? throw new RuntimeException("No MediaSourceClient registered for media type [{$type->value}].");

        return $this->container->make($class);
    }
}
