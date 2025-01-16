<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $query = Member::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        }

        $members = $query->latest()->paginate(10);
        return view('pages.members.index', compact('members'));
    }

    public function create()
    {
        return view('pages.members.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|unique:members,phone'
        ]);

        Member::create($request->all());
        return redirect()->route('members.index')->with('success', 'Member berhasil ditambahkan');
    }

    public function show(Member $member)
    {
        // Get orders related to this member
        $orders = $member->orders()->latest()->paginate(10);

        // Calculate total spending
        $totalSpending = $member->orders()->sum('total_amount');

        return view('pages.members.show', compact('member', 'orders', 'totalSpending'));
    }

    public function edit(Member $member)
    {
        return view('pages.members.edit', compact('member'));
    }

    public function update(Request $request, Member $member)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|unique:members,phone,' . $member->id
        ]);

        $member->update($request->all());
        return redirect()->route('members.index')->with('success', 'Member berhasil diupdate');
    }

    public function destroy(Member $member)
    {
        $member->delete();
        return redirect()->route('members.index')->with('success', 'Member berhasil dihapus');
    }
}
