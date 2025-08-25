<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\ProductImage;
use App\Models\GroupProduct;
use App\Models\Recipe;
use App\Models\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Services\PrismService;
use Pgvector\Laravel\Distance;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::with([
            'groupProduct', 
            'detail', 
            'images' => function($query) {
                $query->active()->ordered();
            },
            'recipes:id,recipe_name as descricao',
            'contents:id,nome_conteudo as descricao'
        ])->latest()->get();
        
        $groupProducts = GroupProduct::all();
        $recipes = Recipe::select('id', 'recipe_name as descricao')->get();
        $contents = Content::where('status', true)->select('id', 'nome_conteudo as descricao')->get();

        return Inertia::render('Products/Manage', [
            'products' => $products,
            'groupProducts' => $groupProducts,
            'recipes' => $recipes,
            'contents' => $contents
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Debug: Log the incoming request data
        Log::info('Product store request data:', $request->all());
        
        // Validate product basic data
        $productData = $request->validate([
            'descricao' => 'required|string|max:255',
            'codigo_padrao' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:255',
            'group_product_id' => 'nullable|exists:group_products,id',
            'marca' => 'nullable|string|max:255',
            'status' => 'boolean',
        ]);

        // Validate product detail data
        $detailData = $request->validate([
            'escolha_embalagem' => 'nullable|string|max:255',
            'prompt_especificacao_embalagens' => 'nullable|string',
            'prompt_uso_informacoes_produto' => 'nullable|string',
            'especificacao_produto' => 'nullable|string',
            'perfil_sabor' => 'nullable|string',
            'descricao_tabela_nutricional' => 'nullable|string',
            'descricao_lista_ingredientes' => 'nullable|string',
            'descricao_modos_preparo' => 'nullable|string',
            'descricao_rendimentos' => 'nullable|string',
            'embalagem_tipo' => 'nullable|string|max:255',
            'embalagem_descricao' => 'nullable|string',
            'quantidade_caixa' => 'nullable|string|max:255',
            'peso_liquido' => 'nullable|numeric|min:0',
            'peso_bruto' => 'nullable|numeric|min:0',
            'validade' => 'nullable|string|max:255',
            'valor' => 'nullable|numeric|min:0',
            'desconto' => 'nullable|numeric|min:0',
            'informacao_adicional' => 'nullable|string',
            'ean' => 'nullable|string|max:255',
            'qr_code' => 'nullable|string|max:255',
            'url_rede_social' => 'nullable|url',
            'catalogo' => 'boolean',
            'lancamento' => 'boolean',
        ]);

        // Validate images data
        $imagesData = $request->validate([
            'images' => 'nullable|array',
            'images.*.image_type' => 'required|string|in:principal,embalagem,tabela_nutricional,lista_ingredientes,modos_preparo,rendimentos,outras',
            'images.*.nome' => 'nullable|string|max:255',
            'images.*.url' => 'required|url',
            'images.*.descricao' => 'nullable|string',
            'images.*.ordem' => 'nullable|integer|min:0',
            'images.*.ativo' => 'boolean',
            'images.*.valida_produtos_embalagem' => 'boolean',
        ]);

        // Validate relationship data
        $relationshipData = $request->validate([
            'recipe_ids' => 'nullable|array',
            'recipe_ids.*' => 'integer|exists:recipes,id',
            'content_ids' => 'nullable|array',
            'content_ids.*' => 'integer|exists:contents,id',
        ]);

        try {
            Log::info('Validation passed, starting database transaction');
            DB::beginTransaction();

            // Create or update product
            if ($request->id) {
                $product = Product::find($request->id);
                $product->update($productData);
            } else {
                $productData['ulid'] = Str::ulid();
                $productData['slug'] = Str::slug($productData['descricao']);
                $product = Product::create($productData);
            }

            // Create or update product details
            $product->detail()->updateOrCreate(
                ['product_id' => $product->id],
                $detailData
            );

            // Handle images
            if (!empty($imagesData['images'])) {
                foreach ($imagesData['images'] as $imageData) {
                    if (!empty($imageData['url'])) {
                        $product->images()->create(array_merge($imageData, [
                            'ordem' => $imageData['ordem'] ?? 0,
                            'ativo' => $imageData['ativo'] ?? true,
                            'valida_produtos_embalagem' => $imageData['valida_produtos_embalagem'] ?? false,
                        ]));
                    }
                }
            }

            // Handle recipe relationships
            if ($request->has('recipe_ids') && is_array($request->recipe_ids)) {
                Log::info('Syncing recipes:', $request->recipe_ids);
                $product->recipes()->sync($request->recipe_ids);
            }

            // Handle content relationships
            if ($request->has('content_ids') && is_array($request->content_ids)) {
                Log::info('Syncing contents:', $request->content_ids);
                $product->contents()->sync($request->content_ids);
            }

            DB::commit();
            return redirect()->route('products.index')->with('success', 'Produto salvo com sucesso!');

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->withErrors(['error' => 'Erro ao salvar produto: ' . $e->getMessage()]);
        }
    }

    /**
     * Search for products using text query and embeddings
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
            'include_inactive' => 'nullable|boolean'
        ]);

        $query = $request->query('query');
        $limit = $request->query('limit', 10);
        $includeInactive = $request->query('include_inactive', false);

        try {
            // Start with a base query
            $productsQuery = Product::with([
                'groupProduct', 
                'detail', 
                'images' => function($query) {
                    $query->active()->ordered();
                },
                'recipes:id,recipe_name',
                'contents:id,nome_conteudo'
            ]);

            // Filter by status unless including inactive
            if (!$includeInactive) {
                $productsQuery->where('status', true);
            }

            // Try embedding search first if PrismService is available
            try {
                $prism = new PrismService();
                $response = $prism->getEmbedding($query);
                $embedding = $prism->extractEmbeddingFromResponse($response);

                if ($embedding && is_array($embedding)) {
                    // Embedding-based search
                    $products = $productsQuery
                        ->nearestNeighbors('embedding', $embedding, Distance::Cosine)
                        ->take($limit)
                        ->get()
                        ->map(function ($product) {
                            return collect($product)->except('embedding')->toArray();
                        });
                } else {
                    throw new \Exception('Failed to generate embedding');
                }
            } catch (\Exception $e) {
                // Fallback to text-based search if embedding fails
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

            return response()->json([
                'products' => $products,
                'query' => $query,
                'total' => $products->count()
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Search failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->back();
    }
}
