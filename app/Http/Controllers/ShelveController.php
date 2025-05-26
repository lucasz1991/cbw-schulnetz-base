<?php

namespace App\Http\Controllers;

use App\Models\Shelve;
use App\Models\Review;
use Illuminate\Http\Request;


class ShelveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Shelve $shelf)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Shelve $shelf)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Shelve $shelf)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Shelve $shelf)
    {
        //
    }


    public function addReview(Request $request, Shelve $shelf)
    {
        $validatedData = $request->validate([
            'buyer_id' => 'required|exists:buyers,id',
            'review_text' => 'required|string',
            'rating' => 'required|integer|min:1|max:5'
        ]);

        Review::create([
            'buyer_id' => $validatedData['buyer_id'],
            'shelf_id' => $shelf->id,
            'review_text' => $validatedData['review_text'],
            'rating' => $validatedData['rating'],
            'date' => now()
        ]);

        return response()->json(['message' => 'Review added successfully'], 201);
    }
}
