<?php

namespace App\Enums;

/**
 * Named by capability, not vendor, so swapping the vendor behind GamesApi or
 * BooksApi (see docs/adr/0001-media-source-integration-architecture.md) only
 * touches a service binding, never this enum or stored media_items rows.
 */
enum MediaSource: string
{
    case Tmdb = 'tmdb';
    case GamesApi = 'games_api';
    case BooksApi = 'books_api';
    case Manual = 'manual';
}
