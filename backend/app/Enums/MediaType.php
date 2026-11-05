<?php

namespace App\Enums;

enum MediaType: string
{
    case Movie = 'movie';
    case TvShow = 'tv_show';
    case Game = 'game';
    case Book = 'book';
}
