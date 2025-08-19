<?php

use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Recipe routes
Route::apiResource('recipes', RecipeController::class);
Route::get('recipes/search', [RecipeController::class, 'search']);

// Product routes  
Route::apiResource('products', ProductController::class);
Route::get('products/search', [ProductController::class, 'search']);
Route::get('products/{product}/similar', [ProductController::class, 'similar']);

// Content routes
Route::apiResource('contents', ContentController::class);
Route::get('contents/search', [ContentController::class, 'search']);
Route::get('contents/{content}/similar', [ContentController::class, 'similar']);

// Unified search routes
Route::get('search/all', [SearchController::class, 'searchAll']);
Route::get('search/mixed', [SearchController::class, 'searchMixed']);
Route::get('search/suggestions', [SearchController::class, 'suggestions']);