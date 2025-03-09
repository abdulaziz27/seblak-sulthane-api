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
                    'expenses' => 0,
                    'expenses_note' => null,
                    'closing_balance' => 0,
                ]
            ]);
        }

        // Hitung cash sales untuk tanggal tersebut
        $cashSales = Order::where('outlet_id', Auth::user()->outlet_id)
            ->whereDate('created_at', $date)
            ->where('payment_method', 'cash')
            ->sum('total');

        $closingBalance = $dailyCash->opening_balance + $cashSales - $dailyCash->expenses;

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $dailyCash->id,
                'outlet_id' => $dailyCash->outlet_id,
                'date' => $dailyCash->date,
                'opening_balance' => $dailyCash->opening_balance,
                'expenses' => $dailyCash->expenses,
                'expenses_note' => $dailyCash->expenses_note,
                'cash_sales' => $cashSales,
                'closing_balance' => $closingBalance,
            ]
        ]);
    }
}
