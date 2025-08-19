<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Services\SearchService;
use Exception;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Pgvector\Laravel\Distance;
class RecipeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $recipes = Recipe::latest()->get();

        return Inertia::render('Recipes/Manage', [
            'recipes' => $recipes
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // return Inertia::render('Recipes/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'raw_text' => 'required|string|min:30',
            'tags' => 'nullable|array'
        ]);

        Recipe::updateOrCreate(
            ['id' => $request->id],
            $data
        );

        return null;
    }

    /**
     * Display the specified resource.
     */
    public function show(Recipe $recipe)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Recipe $recipe)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Recipe $recipe)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Recipe $recipe)
    {
        $recipe->delete();
    }

    public function search(Request $request, SearchService $searchService)
    {
        $query = $request->query('query');
        $limit = (int) ($request->query('limit', 10));

        if (!$query) {
            return response()->json(['error' => 'Missing query.'], 422);
        }

        try {
            $results = $searchService->searchRecipes($query, $limit);
            return response()->json($results);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to perform search.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
