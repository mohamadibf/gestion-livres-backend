<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Book */
class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'published_year' => $this->published_year,
            'genre' => $this->genre,
            'description' => $this->description,
            'page_count' => $this->page_count,
            'language' => $this->language,
            'publisher' => $this->publisher,
            'shelf_mark' => $this->shelf_mark,
            'physical_condition' => $this->physical_condition,
            'keywords' => $this->keywords ?? [],
            'loanable' => $this->loanable,
            'image_path' => $this->image_path,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
