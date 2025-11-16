<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DailyCash;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DailyCashController extends Controller
{
    /**
     * Set modal awal (opening balance) untuk hari tertentu
     */
    public function setOpeningBalance(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'opening_balance' => 'required|numeric|min:0',
        ]);

        // Check if user has outlet_id (owner doesn't have outlet_id)
        if (!Auth::user()->outlet_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak memiliki outlet. Hanya admin dan staff yang dapat mengatur saldo awal.',
            ], 403);
        }

        $dailyCash = DailyCash::updateOrCreate(
            [
                'outlet_id' => Auth::user()->outlet_id,
                'date' => $request->date,
            ],
            [
                'user_id' => Auth::id(),
                'opening_balance' => $request->opening_balance,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Opening balance has been set',
            'data' => $dailyCash
        ]);
    }

    /**
     * Tambahkan pengeluaran untuk hari tertentu
     */
    public function addExpense(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        // Check if user has outlet_id (owner doesn't have outlet_id)
        if (!Auth::user()->outlet_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak memiliki outlet. Hanya admin dan staff yang dapat menambahkan pengeluaran.',
            ], 403);
        }

        // Cari atau buat record daily cash untuk tanggal tersebut
        $dailyCash = DailyCash::firstOrCreate(
            [
                'outlet_id' => Auth::user()->outlet_id,
                'date' => $request->date,
            ],
            [
                'user_id' => Auth::id(),
                'opening_balance' => 0,
            ]
        );

        // Tambahkan expense
        $dailyCash->expenses += $request->amount;

        // Tambahkan note untuk expense
        $timeNow = Carbon::now()->format('H:i');
        $newNote = "[{$timeNow}] Rp " . number_format($request->amount, 0, ',', '.') . " - " . ($request->note ?? 'No description');

        $dailyCash->expenses_note = $dailyCash->expenses_note
            ? $dailyCash->expenses_note . "\n" . $newNote
            : $newNote;

        $dailyCash->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Expense has been added',
            'data' => $dailyCash
        ]);
    }

    /**
     * Dapatkan data daily cash untuk tanggal tertentu
     */
    public function getDailyCash(Request $request)
    {
        $date = $request->date ?? Carbon::now()->format('Y-m-d');

        // Check if user has outlet_id (owner doesn't have outlet_id)
        if (!Auth::user()->outlet_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak memiliki outlet. Hanya admin dan staff yang dapat melihat data kas harian.',
            ], 403);
        }

        $dailyCash = DailyCash::where('outlet_id', Auth::user()->outlet_id)
            ->where('date', $date)
            ->first();

        if (!$dailyCash) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'outlet_id' => Auth::user()->outlet_id,
                    'date' => $date,
                    'opening_balance' => 0,
                    'cash_sales' => 0,
                    'qris_sales' => 0,
                    'qris_fee' => 0,
                    'expenses' => 0,
                    'effective_expenses' => 0,
                    'expenses_note' => null,
                    'closing_balance' => 0,
                    'final_cash_closing' => 0,
                ]
            ]);
        }

        // Hitung cash sales untuk tanggal tersebut
        $cashSales = Order::where('outlet_id', Auth::user()->outlet_id)
            ->whereDate('created_at', $date)
            ->where('payment_method', 'cash')
            ->sum('total');

        // Hitung total penjualan QRIS (biaya QRIS tidak lagi dihitung di sistem)
        $qrisSales = Order::where('outlet_id', Auth::user()->outlet_id)
            ->whereDate('created_at', $date)
            ->where('payment_method', 'qris')
            ->sum('total');
        $qrisFee = 0;

        $effectiveExpenses = $dailyCash->opening_balance + $dailyCash->expenses;
        $closingBalance = ($cashSales + $qrisSales) - $effectiveExpenses;

        // Calculate final cash closing (only cash payments)
        $finalCashClosing = $cashSales - $dailyCash->expenses;

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $dailyCash->id,
                'outlet_id' => $dailyCash->outlet_id,
                'date' => $dailyCash->date,
                'opening_balance' => $dailyCash->opening_balance,
                'expenses' => $dailyCash->expenses,
                'effective_expenses' => $effectiveExpenses,
                'expenses_note' => $dailyCash->expenses_note,
                'cash_sales' => $cashSales,
                'qris_sales' => $qrisSales,
                'qris_fee' => $qrisFee,
                'closing_balance' => $closingBalance,
                'final_cash_closing' => $finalCashClosing,
            ]
        ]);
    }
}
