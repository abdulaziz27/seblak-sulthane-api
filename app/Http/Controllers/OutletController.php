<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        $outlets = Outlet::when($request->name, function ($query, $name) {
            $query->where('name', 'like', "%{$name}%");
        })
            ->latest()
            ->paginate(10);

        return view('pages.outlets.index', compact('outlets'));
    }

    public function create()
    {
        return view('pages.outlets.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'address' => 'required',
            'phone' => 'required',
        ]);

        Outlet::create($request->all());

        return redirect()->route('outlets.index')->with('success', 'Outlet created successfully.');
    }

    public function show(Outlet $outlet)
    {
        // return view('pages.outlets.show', compact('outlet'));
    }

    public function edit(Outlet $outlet)
    {
        return view('pages.outlets.edit', compact('outlet'));
    }

    public function update(Request $request, Outlet $outlet)
    {
        $request->validate([
            'name' => 'required',
            'address' => 'required',
            'phone' => 'required',
        ]);

        $outlet->update($request->all());

        return redirect()->route('outlets.index')->with('success', 'Outlet updated successfully.');
    }

    public function destroy(Outlet $outlet)
    {
        $outlet->delete();

        return redirect()->route('outlets.index')->with('success', 'Outlet deleted successfully.');
    }
}
