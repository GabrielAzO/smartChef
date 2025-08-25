<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\Product;
use App\Models\Content;
use App\Services\PrismService;
use Illuminate\Http\Request;
use Pgvector\Laravel\Distance;

class SearchController extends Controller
{
    /**
     * Search across all models (recipes, products, contents) using embeddings and text search
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1',
            'type' => 'nullable|string|in:recipes,products,contents,all',
            'limit' => 'nullable|integer|min:1|max:50',
            'include_inactive' => 'nullable|boolean'
        ]);

        $query = $request->query('query');
        $type = $request->query('type', 'all');
        $limit = $request->query('limit', 5);
        $includeInactive = $request->query('include_inactive', false);

        try {
            $results = [];

            // Try embedding search first if PrismService is available
            $useEmbedding = true;
            $embedding = null;
            
            try {
                $prism = new PrismService();
                $response = $prism->getEmbedding($query);
                $embedding = $prism->extractEmbeddingFromResponse($response);

                if (!$embedding || !is_array($embedding)) {
                    $useEmbedding = false;
                }
            } catch (\Exception $e) {
                $useEmbedding = false;
            }

            // Search recipes
            if ($type === 'all' || $type === 'recipes') {
                $recipesQuery = Recipe::with(['products']);

                if ($useEmbedding) {
                    $recipes = $recipesQuery
                        ->nearestNeighbors('embedding', $embedding, Distance::Cosine)
                        ->take($limit)
                        ->get()
                        ->map(function ($recipe) {
                            return collect($recipe)->except('embedding')->toArray();
                        });
                } else {
                    $recipes = $recipesQuery
                        ->where(function($q) use ($query) {
                            $q->where('recipe_name', 'ILIKE', '%' . $query . '%')
                              ->orWhere('recipe_code', 'ILIKE', '%' . $query . '%')
                              ->orWhere('cuisine', 'ILIKE', '%' . $query . '%')
                              ->orWhere('recipe_type', 'ILIKE', '%' . $query . '%')
                              ->orWhere('recipe_description', 'ILIKE', '%' . $query . '%')
                              ->orWhere('ingredients_description', 'ILIKE', '%' . $query . '%')
                              ->orWhere('preparation_method', 'ILIKE', '%' . $query . '%')
                              ->orWhere('difficulty_level', 'ILIKE', '%' . $query . '%')
                              ->orWhere('channel', 'ILIKE', '%' . $query . '%');
                        })
                        ->take($limit)
                        ->get()
                        ->map(function ($recipe) {
                            return collect($recipe)->except('embedding')->toArray();
                        });
                }
                $results['recipes'] = $recipes;
            }

            // Search products
            if ($type === 'all' || $type === 'products') {
                $productsQuery = Product::with([
                    'groupProduct', 
                    'detail', 
                    'images' => function($query) {
                        $query->active()->ordered();
                    },
                    'recipes:id,recipe_name',
                    'contents:id,nome_conteudo'
                ]);

                if (!$includeInactive) {
                    $productsQuery->where('status', true);
                }

                if ($useEmbedding) {
                    $products = $productsQuery
                        ->nearestNeighbors('embedding', $embedding, Distance::Cosine)
                        ->take($limit)
                        ->get()
                        ->map(function ($product) {
                            return collect($product)->except('embedding')->toArray();
                        });
                } else {
                    $products = $productsQuery
                        ->where(function($q) use ($query) {
                            $q->where('descricao', 'ILIKE', '%' . $query . '%')
                              ->orWhere('codigo_padrao', 'ILIKE', '%' . $query . '%')
                              ->orWhere('sku', 'ILIKE', '%' . $query . '%')
                              ->orWhere('marca', 'ILIKE', '%' . $query . '%')
                              ->orWhereHas('groupProduct', function($gq) use ($query) {
                                  $gq->where('name', 'ILIKE', '%' . $query . '%');
                              })
                              ->orWhereHas('detail', function($dq) use ($query) {
                                  $dq->where('especificacao_produto', 'ILIKE', '%' . $query . '%')
                                    ->orWhere('perfil_sabor', 'ILIKE', '%' . $query . '%')
                                    ->orWhere('descricao_tabela_nutricional', 'ILIKE', '%' . $query . '%')
                                    ->orWhere('descricao_lista_ingredientes', 'ILIKE', '%' . $query . '%')
                                    ->orWhere('descricao_modos_preparo', 'ILIKE', '%' . $query . '%');
                              });
                        })
                        ->take($limit)
                        ->get()
                        ->map(function ($product) {
                            return collect($product)->except('embedding')->toArray();
                        });
                }
                $results['products'] = $products;
            }

            // Search contents
            if ($type === 'all' || $type === 'contents') {
                $contentsQuery = Content::with(['recipes', 'products']);

                if (!$includeInactive) {
                    $contentsQuery->where('status', true);
                }

                if ($useEmbedding) {
                    $contents = $contentsQuery
                        ->nearestNeighbors('embedding', $embedding, Distance::Cosine)
                        ->take($limit)
                        ->get()
                        ->map(function ($content) {
                            return collect($content)->except('embedding')->toArray();
                        });
                } else {
                    $contents = $contentsQuery
                        ->where(function($q) use ($query) {
                            $q->where('nome_conteudo', 'ILIKE', '%' . $query . '%')
                              ->orWhere('content_code', 'ILIKE', '%' . $query . '%')
                              ->orWhere('descricao_conteudo', 'ILIKE', '%' . $query . '%')
                              ->orWhere('descricao_tabela_nutricional', 'ILIKE', '%' . $query . '%')
                              ->orWhere('descricao_lista_ingredientes', 'ILIKE', '%' . $query . '%')
                              ->orWhere('descricao_modos_preparo', 'ILIKE', '%' . $query . '%')
                              ->orWhere('descricao_rendimentos', 'ILIKE', '%' . $query . '%')
                              ->orWhere('tipo_conteudo', 'ILIKE', '%' . $query . '%')
                              ->orWhere('pilares', 'ILIKE', '%' . $query . '%')
                              ->orWhere('canal', 'ILIKE', '%' . $query . '%');
                        })
                        ->take($limit)
                        ->get()
                        ->map(function ($content) {
                            return collect($content)->except('embedding')->toArray();
                        });
                }
                $results['contents'] = $contents;
            }

            // Calculate totals
            $totals = [];
            foreach ($results as $key => $items) {
                $totals[$key] = $items->count();
            }

            return response()->json([
                'results' => $results,
                'totals' => $totals,
                'query' => $query,
                'search_method' => $useEmbedding ? 'embedding' : 'text',
                'total_results' => array_sum($totals)
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Search failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get search suggestions based on partial query
     */
    public function suggestions(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1',
            'type' => 'nullable|string|in:recipes,products,contents,all',
            'limit' => 'nullable|integer|min:1|max:20'
        ]);

        $query = $request->query('query');
        $type = $request->query('type', 'all');
        $limit = $request->query('limit', 5);

        try {
            $suggestions = [];

            if ($type === 'all' || $type === 'recipes') {
                $recipes = Recipe::select('id', 'recipe_name as name', 'recipe_code as code')
                    ->where('recipe_name', 'ILIKE', '%' . $query . '%')
                    ->take($limit)
                    ->get()
                    ->map(function($recipe) {
                        return [
                            'id' => $recipe->id,
                            'name' => $recipe->name,
                            'code' => $recipe->code,
                            'type' => 'recipe'
                        ];
                    });
                $suggestions = array_merge($suggestions, $recipes->toArray());
            }

            if ($type === 'all' || $type === 'products') {
                $products = Product::select('id', 'descricao as name', 'codigo_padrao as code')
                    ->where('status', true)
                    ->where('descricao', 'ILIKE', '%' . $query . '%')
                    ->take($limit)
                    ->get()
                    ->map(function($product) {
                        return [
                            'id' => $product->id,
                            'name' => $product->name,
                            'code' => $product->code,
                            'type' => 'product'
                        ];
                    });
                $suggestions = array_merge($suggestions, $products->toArray());
            }

            if ($type === 'all' || $type === 'contents') {
                $contents = Content::select('id', 'nome_conteudo as name', 'content_code as code')
                    ->where('status', true)
                    ->where('nome_conteudo', 'ILIKE', '%' . $query . '%')
                    ->take($limit)
                    ->get()
                    ->map(function($content) {
                        return [
                            'id' => $content->id,
                            'name' => $content->name,
                            'code' => $content->code,
                            'type' => 'content'
                        ];
                    });
                $suggestions = array_merge($suggestions, $contents->toArray());
            }

            // Limit total suggestions
            if (count($suggestions) > ($limit * 3)) {
                $suggestions = array_slice($suggestions, 0, $limit * 3);
            }

            return response()->json([
                'suggestions' => $suggestions,
                'query' => $query,
                'total' => count($suggestions)
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Suggestions failed: ' . $e->getMessage()], 500);
        }
    }
}