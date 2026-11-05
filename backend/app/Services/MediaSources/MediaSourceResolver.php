<?php

namespace App\Services\MediaSources;

use App\Enums\MediaSource;
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

    /** @var array<string, MediaSource> */
    private array $sources = [
        MediaType::Movie->value => MediaSource::Tmdb,
        MediaType::TvShow->value => MediaSource::Tmdb,
        MediaType::Game->value => MediaSource::GamesApi,
        MediaType::Book->value => MediaSource::BooksApi,
    ];

    public function __construct(private readonly Container $container) {}

    public function forType(MediaType $type): MediaSourceClient
    {
        $class = $this->clients[$type->value]
            ?? throw new RuntimeException("No MediaSourceClient registered for media type [{$type->value}].");

        return $this->container->make($class);
    }

    /**
     * Which MediaSource backs a type, without instantiating or calling its
     * client. Lets a caller check "is this already cataloged?" before
     * paying for an external API round trip.
     */
    public function sourceForType(MediaType $type): MediaSource
    {
        return $this->sources[$type->value]
            ?? throw new RuntimeException("No MediaSource registered for media type [{$type->value}].");
    }
}
