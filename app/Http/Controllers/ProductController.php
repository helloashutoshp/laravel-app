<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    /**
     * Display a listing of the user's products.
     */
    public function index(Request $request)
    {
        $products = Product::with('images')
            ->where('user_id', auth()->user()->id)
            ->latest()
            ->paginate(10);
        return response()->json($products);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'cost' => 'required|numeric|min:0',
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        // Use Auth facade instead of auth() helper
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated['user_id'] = $user->id;
        $product = Product::create($validated);

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $product->images()->create(['image' => $path]);
                $imagePaths[] = $path;
            }
        }

        return response()->json([
            'product' => $product->load('images'),
            'message' => 'Product created successfully'
        ], 201);
    }

    /**
     * Display the specified product (only if owned by user).
     */
    public function show($id)
    {
        $product = Product::with('images')
            ->where('id', $id)
            ->where('user_id', auth()->user()->id)
            ->first();
        if (!$product) {
            return response()->json(['message' => 'Not found'], 404);
        }
        return response()->json($product);
    }

    /**
     * Update the specified product (only if owned by user).
     */
    public function update(Request $request, $id)
    {
        $product = Product::where('id', $id)->where('user_id', auth()->user()->id)->first();
        if (!$product) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'cost' => 'required|numeric|min:0',
            'images' => 'sometimes|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
        $product->update($validated);

        // Handle new images if provided
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $product->images()->create(['image' => $path]);
            }
        }

        return response()->json([
            'product' => $product->load('images'),
            'message' => 'Product updated successfully'
        ]);
    }

    /**
     * Remove the specified product (only if owned by user).
     */
    public function destroy($id)
    {
        $product = Product::where('id', $id)->where('user_id', auth()->user()->id)->first();
        if (!$product) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $product->delete(); // images are deleted by cascade
        return response()->json(['message' => 'Product and its images deleted successfully']);
    }
}
