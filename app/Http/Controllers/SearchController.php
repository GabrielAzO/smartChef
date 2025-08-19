<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Search across all entity types
     */
    public function searchAll(Request $request, SearchService $searchService)
    {
        $query = $request->query('query');
        $limit = (int) ($request->query('limit', 10));
        $entityTypes = $request->query('types', ['recipes', 'products', 'contents']);

        if (!$query) {
            return response()->json(['error' => 'Missing query parameter.'], 422);
        }

        if (!is_array($entityTypes)) {
            $entityTypes = explode(',', $entityTypes);
        }

        try {
            $results = $searchService->searchAll($query, $limit, $entityTypes);
            
            return response()->json([
                'query' => $query,
                'results' => $results,
                'total_results' => array_sum(array_map('count', $results))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to perform search.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get mixed results from all entity types
     */
    public function searchMixed(Request $request, SearchService $searchService)
    {
        $query = $request->query('query');
        $limit = (int) ($request->query('limit', 10));
        
        // Allow custom weights for different entity types
        $weights = [
            'recipes' => (float) ($request->query('recipe_weight', 0.4)),
            'products' => (float) ($request->query('product_weight', 0.3)),
            'contents' => (float) ($request->query('content_weight', 0.3)),
        ];

        if (!$query) {
            return response()->json(['error' => 'Missing query parameter.'], 422);
        }

        try {
            $results = $searchService->searchMixed($query, $limit, $weights);
            
            return response()->json([
                'query' => $query,
                'results' => $results,
                'total_results' => count($results),
                'weights_used' => $weights
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to perform mixed search.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get search suggestions based on query
     */
    public function suggestions(Request $request, SearchService $searchService)
    {
        $query = $request->query('query');
        $limit = (int) ($request->query('limit', 5));

        if (!$query || strlen($query) < 2) {
            return response()->json(['suggestions' => []]);
        }

        try {
            // Get a small sample from each entity type for suggestions
            $results = $searchService->searchAll($query, 3, ['recipes', 'products', 'contents']);
            
            $suggestions = [];
            
            foreach ($results['recipes'] as $recipe) {
                $suggestions[] = [
                    'type' => 'recipe',
                    'text' => $recipe->title,
                    'id' => $recipe->id
                ];
            }
            
            foreach ($results['products'] as $product) {
                $suggestions[] = [
                    'type' => 'product',
                    'text' => $product->name,
                    'id' => $product->id
                ];
            }
            
            foreach ($results['contents'] as $content) {
                $suggestions[] = [
                    'type' => 'content',
                    'text' => $content->title,
                    'id' => $content->id
                ];
            }
            
            return response()->json([
                'suggestions' => array_slice($suggestions, 0, $limit)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get suggestions.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}