<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class Product extends Model
{
    use HasFactory;
    use HasNeighbors;

    protected $fillable = [
        'name',
        'description',
        'brand',
        'category',
        'nutritional_info',
        'ingredients',
        'price',
        'weight',
        'embedding',
    ];

    protected $casts = [
        'nutritional_info' => 'array',
        'ingredients' => 'array',
        'price' => 'decimal:2',
        'weight' => 'decimal:2',
    ];

    public function getEmbeddingVector(): ?Vector
    {
        return $this->embedding ? new Vector($this->embedding) : null;
    }

    public function getSearchableText(): string
    {
        return collect([
            $this->name,
            $this->description,
            $this->brand,
            $this->category,
            implode(', ', $this->ingredients ?? []),
        ])->filter()->implode("\n");
    }
}