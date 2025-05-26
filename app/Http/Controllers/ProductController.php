<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        return Product::all();
    }

    public function create()
    {
        // Return view to create a product
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'shelf_id' => 'required|exists:shelves,id',
            'seller_id' => 'required|exists:sellers,id',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'description' => 'required|string',
            'images' => 'required|array',
            'images.*' => 'required|url',
            'category' => 'required|string|max:100',
            'age_recommendation' => 'required|string|max:50',
        ]);

        // Standardstatus auf "draft" setzen
        $validatedData['status'] = 'draft';

        Product::create($validatedData);

        return response()->json(['message' => 'Product created successfully as draft'], 201);
    }

    public function show(Product $product)
    {
        return $product;
    }

    public function edit(Product $product)
    {
        // Return view to edit a product
    }

    public function update(Request $request, Product $product)
    {
        $validatedData = $request->validate([
            'shelf_id' => 'sometimes|required|exists:shelves,id',
            'seller_id' => 'sometimes|required|exists:sellers,id',
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric',
            'description' => 'sometimes|required|string',
            'images' => 'sometimes|required|array',
            'images.*' => 'sometimes|required|url',
            'category' => 'sometimes|required|string|max:100',
            'age_recommendation' => 'sometimes|required|string|max:50',
            'status' => 'sometimes|in:draft,published', // Status nur erlaubte Werte
        ]);

        $product->update($validatedData);

        return response()->json(['message' => 'Product updated successfully'], 200);
    }

    public function destroy(Product $product)
    {
        // Überprüfen, ob das Produkt den Status "draft" hat
        if ($product->status !== 'draft') {
            return response()->json(['message' => 'Only draft products can be deleted'], 403);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully'], 200);
    }

    public function publish(Product $product)
    {
        // Überprüfen, ob das Produkt veröffentlicht werden kann
        if ($product->status === 'published') {
            return response()->json(['message' => 'Product is already published'], 400);
        }

        $product->update(['status' => 'published']);

        return response()->json(['message' => 'Product published successfully'], 200);
    }

    public function unpublish(Product $product)
    {
        // Überprüfen, ob das Produkt zurückgesetzt werden kann
        if ($product->status === 'draft') {
            return response()->json(['message' => 'Product is already in draft'], 400);
        }

        $product->update(['status' => 'draft']);

        return response()->json(['message' => 'Product unpublished successfully'], 200);
    }
}
