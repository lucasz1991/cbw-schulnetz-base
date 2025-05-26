<?php

namespace App\Http\Controllers;

use App\Models\RetailSpace;
use Illuminate\Http\Request;

class RetailSpaceController extends Controller
{
    // Anzeigen aller Verkaufsflächen
    public function index()
    {
        $retailSpaces = RetailSpace::all();
        return view('retail-spaces.index', compact('retailSpaces'));
    }

    // Anzeigen einer spezifischen Verkaufsfläche
    public function show(RetailSpace $retailSpace)
    {
        return view('retail-spaces.show', compact('retailSpace'));
    }

    // Anzeigen des Formulars zur Erstellung einer neuen Verkaufsfläche
    public function create()
    {
        return view('retail-spaces.create');
    }

    // Speichern einer neuen Verkaufsfläche
    public function store(Request $request)
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'name' => 'required|string|max:255',
            'width' => 'required|integer',
            'height' => 'required|integer',
            'depth' => 'required|integer',
            'max_weight_capacity' => 'required|integer',
            'status' => 'required|string'
        ]);

        RetailSpace::create($validated);
        return redirect()->route('retail-spaces.index')->with('message', 'Verkaufsfläche erfolgreich erstellt.');
    }

    // Bearbeiten einer Verkaufsfläche
    public function edit(RetailSpace $retailSpace)
    {
        return view('retail-spaces.edit', compact('retailSpace'));
    }

    // Aktualisieren einer bestehenden Verkaufsfläche
    public function update(Request $request, RetailSpace $retailSpace)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'width' => 'required|integer',
            'height' => 'required|integer',
            'depth' => 'required|integer',
            'max_weight_capacity' => 'required|integer',
            'status' => 'required|string'
        ]);

        $retailSpace->update($validated);
        return redirect()->route('retail-spaces.index')->with('message', 'Verkaufsfläche erfolgreich aktualisiert.');
    }

    // Löschen einer Verkaufsfläche
    public function destroy(RetailSpace $retailSpace)
    {
        $retailSpace->delete();
        return redirect()->route('retail-spaces.index')->with('message', 'Verkaufsfläche erfolgreich gelöscht.');
    }
}