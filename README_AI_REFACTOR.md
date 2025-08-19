# AI Search & Response System Refactoring

This document outlines the comprehensive refactoring of the AI searching context and response system for the SmartChef Laravel application.

## Overview

The refactoring introduces a unified, scalable approach to AI-powered search and embeddings across multiple entity types (recipes, products, and content) while maintaining compatibility with the existing Prism/OpenAI API integration.

## New Architecture

### Core Services

#### 1. **EmbeddingService** (`app/Services/EmbeddingService.php`)
- Unified service for generating embeddings using Prism/OpenAI
- Replaces the old `PrismService` and `RecipeEmbeddingService`
- Supports batch processing and configurable providers/models
- Better error handling and logging

#### 2. **EntityEmbeddingService** (`app/Services/EntityEmbeddingService.php`)
- Handles embedding generation for all entity types (Recipe, Product, Content)
- Provides unified `generateAndSaveEmbedding()` method
- Supports batch processing for multiple entities
- Entity-specific text preparation methods

#### 3. **SearchService** (`app/Services/SearchService.php`)
- Unified semantic search across all entity types
- Support for mixed search results with configurable weights
- Similar entity finding functionality
- Extensible threshold-based filtering (placeholder for future enhancement)

#### 4. **ToolCallingService** (`app/Services/ToolCallingService.php`) [Optional]
- Framework for future AI tool calling functionality
- Recipe suggestions, product recommendations, content suggestions
- Intent analysis capabilities
- Extensible tool definition system

### Models

#### Enhanced Models with Embedding Support:
- **Recipe** (existing) - Enhanced with `getSearchableText()` method
- **Product** (new) - Full CRUD with embedding support
- **Content** (new) - Full CRUD with embedding support and relationships

All models use:
- `HasNeighbors` trait for vector similarity search
- `embedding` field with pgvector support
- Standardized `getSearchableText()` methods

### Controllers

#### 1. **RecipeController** (refactored)
- Updated to use new `SearchService`
- Enhanced error handling
- Configurable search limits

#### 2. **ProductController** (new)
- Full REST API with search capabilities
- Automatic embedding generation on create/update
- Similar product finding

#### 3. **ContentController** (new)
- Full REST API with search capabilities
- Relationship loading for recipes and products
- Automatic embedding generation on create/update

#### 4. **SearchController** (new)
- Cross-entity search functionality
- Mixed search results with configurable weights
- Search suggestions endpoint
- Unified API for all search operations

### Console Commands

#### 1. **GenerateAllEmbeddings** (`embeddings:generate-all`)
- Process all entity types in one command
- Configurable batch sizes and entity type selection
- Missing-only processing option
- Progress tracking and error reporting

#### 2. **GenerateProductEmbeddings** (`products:generate-embeddings`)
- Dedicated product embedding generation
- Missing-only processing option

#### 3. **GenerateContentEmbeddings** (`content:generate-embeddings`)
- Dedicated content embedding generation
- Missing-only processing option

#### 4. **GenerateRecipeEmbeddings** (refactored)
- Updated to use new `EntityEmbeddingService`
- Improved error handling

### Database Migrations

- `2025_01_20_000001_add_embedding_to_products_table.php`
- `2025_01_20_000002_add_embedding_to_contents_table.php`

Both migrations add vector embeddings with 1536 dimensions (OpenAI text-embedding-3-small format).

### API Routes

New REST API endpoints in `routes/api.php`:

```php
// Recipe search
GET /api/recipes/search?query={query}&limit={limit}

// Product CRUD and search
GET|POST /api/products
GET|PUT|DELETE /api/products/{id}
GET /api/products/search?query={query}&limit={limit}
GET /api/products/{id}/similar

// Content CRUD and search  
GET|POST /api/contents
GET|PUT|DELETE /api/contents/{id}
GET /api/contents/search?query={query}&limit={limit}
GET /api/contents/{id}/similar

// Unified search
GET /api/search/all?query={query}&limit={limit}&types=recipes,products,contents
GET /api/search/mixed?query={query}&limit={limit}&recipe_weight=0.4&product_weight=0.3&content_weight=0.3
GET /api/search/suggestions?query={query}&limit={limit}
```

## Key Features

### 1. **Unified Embedding Generation**
- Single service handles all entity types
- Consistent error handling and logging
- Batch processing capabilities
- Configurable AI providers and models

### 2. **Cross-Entity Search**
- Search across recipes, products, and content simultaneously
- Mixed results with configurable entity weights
- Entity-specific search endpoints
- Search suggestions for autocomplete

### 3. **Scalable Architecture**
- Easy to add new entity types
- Configurable embedding dimensions
- Provider-agnostic design (currently uses OpenAI via Prism)
- Built-in caching considerations

### 4. **Enhanced Error Handling**
- Comprehensive logging for all AI operations
- Graceful degradation on API failures
- Detailed error responses for debugging

### 5. **Performance Optimizations**
- Batch processing for large datasets
- Configurable batch sizes
- Missing-only processing options
- Vector similarity search with pgvector

## Usage Examples

### Generate Embeddings
```bash
# Generate embeddings for all entities
php artisan embeddings:generate-all

# Generate only for recipes (missing embeddings)
php artisan recipes:generate-embeddings --missing-only

# Generate for specific entity types with custom batch size
php artisan embeddings:generate-all --type=recipe --type=product --batch-size=100
```

### API Usage
```bash
# Search recipes
curl "http://your-app.com/api/recipes/search?query=chicken+curry&limit=5"

# Cross-entity search
curl "http://your-app.com/api/search/all?query=healthy+ingredients&limit=10"

# Mixed search with custom weights
curl "http://your-app.com/api/search/mixed?query=italian+food&recipe_weight=0.6&product_weight=0.3&content_weight=0.1"

# Get search suggestions
curl "http://your-app.com/api/search/suggestions?query=chic&limit=5"
```

## Migration Guide

### 1. **Database**
Run the new migrations to add embedding columns:
```bash
php artisan migrate
```

### 2. **Generate Embeddings**
Process existing data:
```bash
php artisan embeddings:generate-all --missing-only
```

### 3. **Update Frontend**
Update API calls to use new search endpoints and handle the new response formats.

### 4. **Remove Old Services**
The old `PrismService` and `RecipeEmbeddingService` have been removed and replaced with the new unified services.

## Future Enhancements

1. **Tool Calling Integration**: The `ToolCallingService` provides a foundation for advanced AI interactions
2. **Threshold-based Filtering**: Implement similarity score thresholds for search results
3. **Caching Layer**: Add Redis caching for frequent searches
4. **Real-time Updates**: WebSocket integration for real-time search suggestions
5. **A/B Testing**: Framework for testing different embedding models and search algorithms

## Configuration

The system uses the existing Prism configuration in `config/prism.php`. No additional configuration required for basic functionality.

## Monitoring & Logging

All AI operations are logged with comprehensive context for debugging and monitoring. Check `storage/logs/laravel.log` for embedding generation and search operation logs.