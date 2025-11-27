<?php

namespace App\Http\Controllers;

use App\Models\Nation;
use App\Models\Continent;
use Illuminate\Http\Request;

class NationController extends Controller
{
    public function index()
    {
        $nations = Nation::with('continent')
            ->orderBy('nameEn')
            ->get();

        return view('nations.index', compact('nations'));
    }

    public function create()
    {
        $nation = new Nation();
        $continents = Continent::orderBy('nameEn')->pluck('nameEn', 'id');

        return view('nations.create', compact('nation', 'continents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nameEn'       => ['required', 'string', 'max:200', 'unique:nations,nameEn'],
            'nameDe'       => ['nullable', 'string', 'max:200'],
            'ioc'          => ['nullable', 'string', 'max:3', 'unique:nations,ioc'],
            'iso2'         => ['nullable', 'string', 'max:2', 'unique:nations,iso2'],
            'iso3'         => ['nullable', 'string', 'max:3', 'unique:nations,iso3'],
            'continent_id' => ['nullable', 'exists:continents,id'],
        ]);

        Nation::create($data);

        return redirect()
            ->route('nations.index')
            ->with('status', 'Nation wurde angelegt.');
    }

    public function edit(Nation $nation)
    {
        $continents = Continent::orderBy('nameEn')->pluck('nameEn', 'id');

        return view('nations.edit', compact('nation', 'continents'));
    }

    public function update(Request $request, Nation $nation)
    {
        $data = $request->validate([
            'nameEn'       => ['required', 'string', 'max:200', 'unique:nations,nameEn,' . $nation->id],
            'nameDe'       => ['nullable', 'string', 'max:200'],
            'ioc'          => ['nullable', 'string', 'max:3', 'unique:nations,ioc,' . $nation->id],
            'iso2'         => ['nullable', 'string', 'max:2', 'unique:nations,iso2,' . $nation->id],
            'iso3'         => ['nullable', 'string', 'max:3', 'unique:nations,iso3,' . $nation->id],
            'continent_id' => ['nullable', 'exists:continents,id'],
        ]);

        $nation->update($data);

        return redirect()
            ->route('nations.index')
            ->with('status', 'Nation wurde aktualisiert.');
    }

    public function destroy(Nation $nation)
    {
        $nation->delete();

        return redirect()
            ->route('nations.index')
            ->with('status', 'Nation wurde gelöscht.');
    }
}
