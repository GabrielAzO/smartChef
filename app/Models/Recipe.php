<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class Recipe extends Model
{
    /** @use HasFactory<\Database\Factories\RecipeFactory> */
    use HasFactory;
    use HasNeighbors;

    protected $fillable = [
        'title',
        'raw_text',
        'tags',
        'embedding',
        // New columns added from migration
        'recipe_code',
        'recipe_name', 
        'cuisine',
        'recipe_type',
        'service_order',
        'preparation_time',
        'difficulty_level',
        'yield',
        'channel',
        'recipe_description',
        'ingredients_description',
        'preparation_method',
        'main_ingredients',
        'supporting_ingredients',
        'usage_groups',
        'preparation_techniques',
        'consumption_occasion',
        'general_images_link',
        'product_code',
        'content_code'
    ];

    protected $casts = [
        'tags' => 'array',
        'main_ingredients' => 'array',
        'supporting_ingredients' => 'array',
        'usage_groups' => 'array',
        'preparation_techniques' => 'array',
        'consumption_occasion' => 'array',
    ];

    public function getEmbeddingVector(): ?Vector
    {
        return $this->embedding ? new Vector($this->embedding) : null;
    }
}