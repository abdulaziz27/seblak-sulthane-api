<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DiscountController extends Controller
{
    public function index()
    {
        try {
            $discounts = Discount::where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('expired_date')
                        ->orWhere('expired_date', '>=', Carbon::now());
                })
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $discounts
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch discounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'description' => 'required|string',
                'value' => 'required|numeric',
                'type' => 'required|in:percentage,fixed',
                'status' => 'nullable|in:active,inactive',
                'expired_date' => 'nullable|date',
                'category' => 'nullable|string'
            ]);

            $discount = Discount::create($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Discount created successfully',
                'data' => $discount
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create discount',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $discount = Discount::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $discount
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Discount not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching the discount',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $discount = Discount::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string',
                'description' => 'required|string',
                'value' => 'required|numeric',
                'type' => 'required|in:percentage,fixed',
                'status' => 'nullable|in:active,inactive',
                'expired_date' => 'nullable|date',
                'category' => 'nullable|string'
            ]);

            $discount->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Discount updated successfully',
                'data' => $discount->fresh()
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Discount not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while updating the discount',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $discount = Discount::findOrFail($id);
            $discount->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Discount deleted successfully'
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Discount not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while deleting the discount',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
