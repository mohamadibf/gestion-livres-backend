<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{

    protected $fillable = [
        'title',
        'author',
        'isbn',
        'published_year',
        'genre',
        'description',
        'image_path',
        'page_count',
        'language',
        'publisher'
    ];

    protected $casts = [
        'published_year' => 'integer',
        'page_count' => 'integer'
    ];
}

