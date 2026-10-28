<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;


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
        'publisher',
        'shelf_mark',
        'physical_condition',
        'keywords',
        'loanable',
    ];

    public const CONDITIONS = ['excellent', 'good', 'restore'];

    protected $casts = [
        'published_year' => 'integer',
        'page_count' => 'integer',
        'keywords' => 'array',
        'loanable' => 'boolean',
    ];
}

