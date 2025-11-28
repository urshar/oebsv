<?php

namespace App\Http\Controllers;

use App\Models\ParaClassifier;
use App\Models\Nation;
use Illuminate\Http\Request;

class ParaClassifierController extends Controller
{
    public function index()
    {
        $classifiers = ParaClassifier::with('nation')
            ->orderBy('lastName')
            ->orderBy('firstName')
            ->paginate(20);

        return view('paraclassifiers.index', compact('classifiers'));
    }

    public function create()
    {
        $classifier = new ParaClassifier();

        $nations = Nation::orderBy('nameDe')
            ->get()
            ->pluck('display_name', 'id');


        return view('paraclassifiers.create', compact('classifier', 'nations'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        ParaClassifier::create($data);

        return redirect()
            ->route('classifiers.index')
            ->with('success', 'Classifier created.');
    }

    public function edit(ParaClassifier $classifier)
    {
        $nations = Nation::orderBy('nameDe')
            ->get()
            ->pluck('display_name', 'id');

        return view('paraclassifiers.edit', compact('classifier', 'nations'));
    }

    public function update(Request $request, ParaClassifier $classifier)
    {
        $data = $this->validateData($request);

        $classifier->update($data);

        return redirect()
            ->route('classifiers.index')
            ->with('success', 'Classifier updated.');
    }

    public function destroy(ParaClassifier $classifier)
    {
        $classifier->delete();

        return redirect()
            ->route('classifiers.index')
            ->with('success', 'Classifier deleted.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'firstName'  => ['nullable', 'string', 'max:100'],
            'lastName'   => ['nullable', 'string', 'max:100'],
            'email'      => ['nullable', 'email', 'max:190'],
            'phone'      => ['nullable', 'string', 'max:50'],
            'type'       => ['nullable', 'string', 'in:TECH,MED,BOTH'],
            'wps_id'     => ['nullable', 'string', 'max:50'],
            'nation_id'  => ['nullable', 'exists:nations,id'],
        ]);
    }
}
