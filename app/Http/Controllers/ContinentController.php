<?php

namespace App\Http\Controllers;

use App\Models\Continent;
use Illuminate\Http\Request;

class ContinentController extends Controller
{
    public function index()
    {
        $continents = Continent::orderBy('code')->get();

        return view('continents.index', compact('continents'));
    }

    public function create()
    {
        $continent = new Continent();

        return view('continents.create', compact('continent'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'   => ['required', 'string', 'max:5', 'unique:continents,code'],
            'nameEn' => ['required', 'string', 'max:20'],
            'nameDe' => ['nullable', 'string', 'max:20'],
        ]);

        Continent::create($data);

        return redirect()
            ->route('continents.index')
            ->with('status', 'Kontinent wurde angelegt.');
    }

    public function edit(Continent $continent)
    {
        return view('continents.edit', compact('continent'));
    }

    public function update(Request $request, Continent $continent)
    {
        $data = $request->validate([
            'code'   => ['required', 'string', 'max:5', 'unique:continents,code,' . $continent->id],
            'nameEn' => ['required', 'string', 'max:20'],
            'nameDe' => ['nullable', 'string', 'max:20'],
        ]);

        $continent->update($data);

        return redirect()
            ->route('continents.index')
            ->with('status', 'Kontinent wurde aktualisiert.');
    }

    public function destroy(Continent $continent)
    {
        $continent->delete();

        return redirect()
            ->route('continents.index')
            ->with('status', 'Kontinent wurde gelöscht.');
    }
}
