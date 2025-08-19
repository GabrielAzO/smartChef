<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Services\SearchService;
use App\Services\EntityEmbeddingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $contents = Content::latest()->get();

        return Inertia::render('Contents/Manage', [
            'contents' => $contents
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // return Inertia::render('Contents/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, EntityEmbeddingService $embeddingService)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'type' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'meta_description' => 'nullable|string',
            'status' => 'nullable|string|in:draft,published,archived',
        ]);

        $content = Content::updateOrCreate(
            ['id' => $request->id],
            $data
        );

        // Generate embedding asynchronously or in background
        // For now, we'll do it synchronously
        $embeddingService->generateAndSaveEmbedding($content);

        return response()->json($content);
    }

    /**
     * Display the specified resource.
     */
    public function show(Content $content)
    {
        return response()->json($content->load(['recipes', 'products']));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Content $content)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Content $content, EntityEmbeddingService $embeddingService)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'type' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'meta_description' => 'nullable|string',
            'status' => 'nullable|string|in:draft,published,archived',
        ]);

        $content->update($data);
        
        // Regenerate embedding after update
        $embeddingService->generateAndSaveEmbedding($content);

        return response()->json($content);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Content $content)
    {
        $content->delete();
        return response()->json(['message' => 'Content deleted successfully']);
    }

    /**
     * Search content using semantic similarity
     */
    public function search(Request $request, SearchService $searchService)
    {
        $query = $request->query('query');
        $limit = (int) ($request->query('limit', 10));

        if (!$query) {
            return response()->json(['error' => 'Missing query.'], 422);
        }

        try {
            $results = $searchService->searchContents($query, $limit);
            return response()->json($results);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to perform search.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Find similar content to a given content
     */
    public function similar(Content $content, SearchService $searchService)
    {
        try {
            $similar = $searchService->findSimilar($content, 5);
            return response()->json($similar);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to find similar content.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}