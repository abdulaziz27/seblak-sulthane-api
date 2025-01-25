<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        $query = Outlet::query();

        // Filter by outlet based on role
        if (Auth::user()->role !== 'owner') {
            $query->where('id', Auth::user()->outlet_id);
        }

        // Search functionality
        if ($request->name) {
            $query->where('name', 'like', "%{$request->name}%");
        }

        $outlets = $query->latest()->paginate(10);

        // Pass user role to view for conditional rendering
        return view('pages.outlets.index', compact('outlets'));
    }

    public function create()
    {
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Only owner can create new outlets');
        }

        return view('pages.outlets.create');
    }

    public function store(Request $request)
    {
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Only owner can create new outlets');
        }

        $request->validate([
            'name' => 'required',
            'address' => 'required',
            'phone' => 'required',
        ]);

        Outlet::create($request->all());
        return redirect()->route('outlets.index')->with('success', 'Outlet created successfully');
    }

    public function show(Outlet $outlet)
    {
        // return view('pages.outlets.show', compact('outlet'));
    }

    public function edit(Outlet $outlet)
    {
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Only owner can edit outlets');
        }

        return view('pages.outlets.edit', compact('outlet'));
    }

    public function update(Request $request, Outlet $outlet)
    {
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Only owner can update outlets');
        }

        $request->validate([
            'name' => 'required',
            'address' => 'required',
            'phone' => 'required',
        ]);

        $outlet->update($request->all());
        return redirect()->route('outlets.index')->with('success', 'Outlet updated successfully');
    }

    public function destroy(Outlet $outlet)
    {
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Only owner can delete outlets');
        }

        // Check for associated records
        if ($outlet->users()->exists() || $outlet->orders()->exists()) {
            return redirect()->route('outlets.index')
                ->with('error', 'Cannot delete outlet with associated users or orders');
        }

        $outlet->delete();
        return redirect()->route('outlets.index')->with('success', 'Outlet deleted successfully');
    }
}
