<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function index()
    {
        $discounts = Discount::latest()->paginate(10);
        return view('pages.discounts.index', compact('discounts'));
    }

    public function create()
    {
        // return view('pages.discounts.create');
    }

    public function store(Request $request)
    {
        // $request->validate([
        //     'name' => 'required|string',
        //     'description' => 'required|string',
        //     'value' => 'required|numeric',
        //     'type' => 'required|in:percentage,fixed',
        //     'category' => 'required|in:member,event',
        //     'expired_date' => 'nullable|date',
        // ]);

        // Discount::create($request->all());
        // return redirect()->route('discounts.index')->with('success', 'Discount created successfully');
    }

    public function edit(Discount $discount)
    {
        // return view('pages.discounts.edit', compact('discount'));
    }

    public function update(Request $request, Discount $discount)
    {
        // $request->validate([
        //     'name' => 'required|string',
        //     'description' => 'required|string',
        //     'value' => 'required|numeric',
        //     'type' => 'required|in:percentage,fixed',
        //     'category' => 'required|in:member,event',
        //     'expired_date' => 'nullable|date',
        // ]);

        // $discount->update($request->all());
        // return redirect()->route('discounts.index')->with('success', 'Discount updated successfully');
    }

    public function destroy(Discount $discount)
    {
        // $discount->delete();
        // return redirect()->route('discounts.index')->with('success', 'Discount deleted successfully');
    }
}
