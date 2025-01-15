<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $query = Member::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $members = $query->paginate(10);
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
            'phone' => 'required|string|unique:members,phone',
        ]);

        Member::create($request->all());
        return redirect()->route('members.index')->with('success', 'Member berhasil ditambahkan');
    }

    public function show(Member $member)
    {
        // Get member's order history
        $orders = Order::where('member_id', $member->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Calculate total spending
        $totalSpending = Order::where('member_id', $member->id)
            ->sum('total_amount');

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
            'phone' => 'required|string|unique:members,phone,' . $member->id,
        ]);

        $member->update($request->all());
        return redirect()->route('members.index')->with('success', 'Member berhasil diupdate');
    }

    public function destroy(Member $member)
    {
        $member->delete();
        return redirect()->route('members.index')->with('success', 'Member berhasil dihapus');
    }

    public function searchMember(Request $request)
    {
        $phone = $request->phone;
        $member = Member::where('phone', $phone)->first();

        if (!$member) {
            return response()->json([
                'status' => 'error',
                'message' => 'Member tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $member
        ]);
    }
}
