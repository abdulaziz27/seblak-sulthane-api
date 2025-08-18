<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Member::query();

            // Search functionality
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // Sort functionality
            if ($request->has('sort_by')) {
                $sortField = $request->sort_by;
                $sortDirection = $request->input('sort_direction', 'asc');
                $query->orderBy($sortField, $sortDirection);
            } else {
                $query->latest();
            }

            $perPage = $request->input('per_page', 10);
            $members = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $members
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|unique:members,phone',
            ]);

            DB::beginTransaction();
            $member = Member::create($request->all());
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Member created successfully',
                'data' => $member
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $member = Member::findOrFail($id);
            return response()->json([
                'status' => 'success',
                'data' => $member
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Member not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function getMemberByPhone(Request $request)
    {
        try {
            $phone = $request->input('phone');
            $member = Member::where('phone', $phone)->first();

            if (!$member) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Member not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $member
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to find member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $member = Member::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|unique:members,phone,' . $id,
            ]);

            $member->update($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Member updated successfully',
                'data' => $member
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $member = Member::findOrFail($id);
            $member->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Member deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete member',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
