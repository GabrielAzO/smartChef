<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\SearchService;
use App\Services\EntityEmbeddingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::latest()->get();

        return Inertia::render('Products/Manage', [
            'products' => $products
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // return Inertia::render('Products/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, EntityEmbeddingService $embeddingService)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'brand' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'nutritional_info' => 'nullable|array',
            'ingredients' => 'nullable|array',
            'price' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
        ]);

        $product = Product::updateOrCreate(
            ['id' => $request->id],
            $data
        );

        // Generate embedding asynchronously or in background
        // For now, we'll do it synchronously
        $embeddingService->generateAndSaveEmbedding($product);

        return response()->json($product);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return response()->json($product);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product, EntityEmbeddingService $embeddingService)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'brand' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'nutritional_info' => 'nullable|array',
            'ingredients' => 'nullable|array',
            'price' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
        ]);

        $product->update($data);
        
        // Regenerate embedding after update
        $embeddingService->generateAndSaveEmbedding($product);

        return response()->json($product);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }

    /**
     * Search products using semantic similarity
     */
    public function search(Request $request, SearchService $searchService)
    {
        $query = $request->query('query');
        $limit = (int) ($request->query('limit', 10));

        if (!$query) {
            return response()->json(['error' => 'Missing query.'], 422);
        }

        try {
            $results = $searchService->searchProducts($query, $limit);
            return response()->json($results);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to perform search.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Find similar products to a given product
     */
    public function similar(Product $product, SearchService $searchService)
    {
        try {
            $similar = $searchService->findSimilar($product, 5);
            return response()->json($similar);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to find similar products.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}