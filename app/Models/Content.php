<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class Content extends Model
{
    use HasFactory;
    use HasNeighbors;

    protected $fillable = [
        'title',
        'body',
        'type',
        'category',
        'tags',
        'meta_description',
        'status',
        'embedding',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function getEmbeddingVector(): ?Vector
    {
        return $this->embedding ? new Vector($this->embedding) : null;
    }

    public function getSearchableText(): string
    {
        return collect([
            $this->title,
            $this->body,
            $this->type,
            $this->category,
            $this->meta_description,
            implode(', ', $this->tags ?? []),
        ])->filter()->implode("\n");
    }

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }
}