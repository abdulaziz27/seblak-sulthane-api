<?php

namespace App\Http\Controllers;

use App\Models\DailyCash;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Member;
use App\Models\MaterialOrder;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /**
     * Show the reports dashboard
     */
    public function index()
    {
        // Ensure only owner can access
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('home')->with('error', 'Anda tidak memiliki akses untuk melihat laporan');
        }

        $outlets = Outlet::all();

        return view('pages.reports.index', compact('outlets'));
    }

    /**
     * Generate Enhanced Sales Summary Report
     */
    public function salesSummary(Request $request)
    {
        \Log::info('Sales Summary Request Parameters', [
            'all_params' => $request->all(),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'period_type' => $request->input('period_type'),
            'outlet_id' => $request->input('outlet_id'),
        ]);

        if ($request->has('date_range') && strpos($request->date_range, ' - ') !== false) {
            $dates = explode(' - ', $request->date_range);
            if (count($dates) === 2) {
                $request->merge([
                    'start_date' => $dates[0],
                    'end_date' => $dates[1],
                ]);

                \Log::info('Override dates from date_range: ' . $request->date_range, [
                    'new_start_date' => $dates[0],
                    'new_end_date' => $dates[1],
                ]);
            }
        }

        // Validasi request dengan pesan error spesifik
        $request->validate(
            [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'outlet_id' => 'nullable|exists:outlets,id',
                'format' => 'required|in:pdf,excel',
                'period_type' => 'required|in:daily,weekly,monthly',
            ],
            [
                'start_date.required' => 'Tanggal awal diperlukan',
                'end_date.required' => 'Tanggal akhir diperlukan',
                'end_date.after_or_equal' => 'Tanggal akhir harus setelah atau sama dengan tanggal awal',
                'period_type.required' => 'Tipe periode diperlukan',
            ],
        );

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $outletId = $request->outlet_id;
        $periodType = $request->period_type;

        // Log untuk debugging
        \Log::info('Laporan dengan parameter', [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'period_type' => $periodType,
            'outlet_id' => $outletId,
        ]);

        // Base query untuk orders
        $query = Order::whereBetween('created_at', [$startDate, $endDate]);
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        // Data penjualan
        $totalRevenue = $query->sum('total');
        $totalOrders = $query->count();
        $totalSubTotal = $query->sum('sub_total');
        $totalTax = $query->sum('tax');
        $totalDiscountAmount = $query->sum('discount_amount');
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Perbaikan perhitungan QRIS fee dengan CAST eksplisit
        $totalQrisFee = (clone $query)->where('payment_method', 'qris')->selectRaw('COALESCE(SUM(CAST(qris_fee AS DECIMAL(10,2))), 0) as total_fee')->first()->total_fee ?? 0;

        // Data untuk beverage (minuman) - Asumsikan category_id 2 adalah minuman
        $beverageSales = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->when($outletId, function ($query) use ($outletId) {
                return $query->where('orders.outlet_id', $outletId);
            })
            ->where('categories.id', 2) // ID kategori minuman
            ->sum(DB::raw('order_items.quantity * order_items.price'));

        // Data berdasarkan metode pembayaran
        $paymentMethods = Order::whereBetween('created_at', [$startDate, $endDate])
            ->when($outletId, function ($query) use ($outletId) {
                return $query->where('outlet_id', $outletId);
            })
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total_amount'), DB::raw('CASE WHEN payment_method = "qris" THEN COALESCE(SUM(CAST(qris_fee AS DECIMAL(10,2))), 0) ELSE 0 END as qris_fees'))
            ->groupBy('payment_method')
            ->get();

        // Menghitung total berdasarkan metode pembayaran
        $cashSales = 0;
        $qrisSales = 0;

        foreach ($paymentMethods as $method) {
            $method_key = strtolower($method->payment_method);
            if (in_array($method_key, ['cash', 'tunai'])) {
                $cashSales += $method->total_amount;
            } elseif (in_array($method_key, ['qris', 'qriss'])) {
                $qrisSales += $method->total_amount;
            }
        }

        // Data Daily Cash (Saldo Awal dan Pengeluaran)
        $dailyCashQuery = DailyCash::query();

        // Pastikan kita memfilter berdasarkan rentang tanggal dengan format yang benar
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        $dailyCashQuery->whereRaw('DATE(date) BETWEEN ? AND ?', [$startDateStr, $endDateStr]);

        // Filter berdasarkan outlet jika diperlukan
        if ($outletId) {
            $dailyCashQuery->where('outlet_id', $outletId);
        }

        // Ambil data daily cash
        $dailyCashData = $dailyCashQuery->get();

        $totalOpeningBalance = $dailyCashData->sum('opening_balance');
        $totalExpenses = $dailyCashData->sum('expenses');

        // Menghitung saldo akhir (kurangi dengan biaya QRIS)
        $closingBalance = $totalOpeningBalance + $cashSales + $qrisSales - $totalExpenses - $totalQrisFee;

        // Persiapan data harian
        $dailyData = [];

        // Query data DailyCash untuk mendapatkan semua data pengeluaran dan saldo awal
        $dailyCashByDate = [];
        foreach ($dailyCashData as $dailyCash) {
            $dailyCashByDate[$dailyCash->date->format('Y-m-d')] = $dailyCash;
        }

        // Data penjualan berdasarkan tipe periode
        $salesData = [];

        if ($periodType === 'daily') {
            // Generate rentang tanggal untuk tipe periode harian
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $currentDateStr = $currentDate->format('Y-m-d');

                // Query orders harian
                $dailyOrdersQuery = Order::whereDate('created_at', $currentDateStr);
                if ($outletId) {
                    $dailyOrdersQuery->where('outlet_id', $outletId);
                }

                // Hitung data penjualan harian
                $dailyRevenue = $dailyOrdersQuery->sum('total');
                $dailyOrders = $dailyOrdersQuery->count();
                $dailySubTotal = $dailyOrdersQuery->sum('sub_total');
                $dailyTax = $dailyOrdersQuery->sum('tax');
                $dailyDiscountAmount = $dailyOrdersQuery->sum('discount_amount');

                // Perbaikan perhitungan QRIS fee harian
                $dailyQrisFee = (clone $dailyOrdersQuery)->where('payment_method', 'qris')->selectRaw('COALESCE(SUM(CAST(qris_fee AS DECIMAL(10,2))), 0) as daily_fee')->first()->daily_fee ?? 0;

                // Data harian metode pembayaran
                $dailyPaymentMethods = (clone $dailyOrdersQuery)->select('payment_method', DB::raw('SUM(total) as total_amount'))->groupBy('payment_method')->get();

                $dailyCashSales = 0;
                $dailyQrisSales = 0;

                foreach ($dailyPaymentMethods as $method) {
                    $method_key = strtolower(trim($method->payment_method));
                    if (in_array($method_key, ['cash', 'tunai'])) {
                        $dailyCashSales += $method->total_amount;
                    } elseif (in_array($method_key, ['qris', 'qriss'])) {
                        $dailyQrisSales += $method->total_amount;
                    }
                }

                // Data DailyCash harian - ambil dari array yang telah dibuat
                $dailyCashRecord = $dailyCashByDate[$currentDateStr] ?? null;
                $dailyOpeningBalance = $dailyCashRecord ? $dailyCashRecord->opening_balance : 0;
                $dailyExpenses = $dailyCashRecord ? $dailyCashRecord->expenses : 0;

                // Hitung saldo akhir harian (kurangi dengan biaya QRIS)
                $dailyClosingBalance = $dailyOpeningBalance + $dailyCashSales + $dailyQrisSales - $dailyExpenses - $dailyQrisFee;

                // Data minuman harian
                $dailyBeverageSales = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->join('categories', 'categories.id', '=', 'products.category_id')
                    ->whereDate('orders.created_at', $currentDateStr)
                    ->when($outletId, function ($query) use ($outletId) {
                        return $query->where('orders.outlet_id', $outletId);
                    })
                    ->where('categories.id', 2) // ID kategori minuman
                    ->sum(DB::raw('order_items.quantity * order_items.price'));

                // Tambahkan ke array data harian
                $dailyData[] = [
                    'date' => $currentDateStr,
                    'day_name' => $currentDate->locale('id')->isoFormat('dddd'),
                    'revenue' => $dailyRevenue,
                    'sub_total' => $dailySubTotal,
                    'tax' => $dailyTax,
                    'discount_amount' => $dailyDiscountAmount,
                    'beverage_sales' => $dailyBeverageSales,
                    'qris_sales' => $dailyQrisSales,
                    'qris_fee' => $dailyQrisFee,
                    'cash_sales' => $dailyCashSales,
                    'expenses' => $dailyExpenses,
                    'opening_balance' => $dailyOpeningBalance,
                    'closing_balance' => $dailyClosingBalance,
                    'orders_count' => $dailyOrders,
                ];

                $currentDate->addDay();
            }
        } elseif ($periodType === 'weekly') {
            // Query untuk data mingguan yang diperbaiki
            $salesData = Order::select(DB::raw("CONCAT(YEAR(created_at), '-', WEEK(created_at)) as period_key"), DB::raw("CONCAT(DATE_FORMAT(MIN(created_at), '%d/%m/%Y'), ' - ', DATE_FORMAT(MAX(created_at), '%d/%m/%Y')) as period_label"), DB::raw('COUNT(*) as order_count'), DB::raw('SUM(total) as total_sales'), DB::raw('SUM(sub_total) as sub_total'), DB::raw('SUM(tax) as tax'), DB::raw('SUM(discount_amount) as discount_amount'), DB::raw('SUM(CASE WHEN payment_method = "qris" THEN CAST(qris_fee AS DECIMAL(10,2)) ELSE 0 END) as qris_fee'))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->when($outletId, function ($query) use ($outletId) {
                    return $query->where('outlet_id', $outletId);
                })
                ->groupBy(DB::raw("CONCAT(YEAR(created_at), '-', WEEK(created_at))"))
                ->orderBy(DB::raw('MIN(created_at)'))
                ->get();

            // Get payment data per week
            foreach ($salesData as $weekData) {
                list($year, $week) = explode('-', $weekData->period_key);

                $weekPayments = Order::select('payment_method', DB::raw('SUM(total) as total_amount'))
                    ->whereRaw('YEAR(created_at) = ?', [$year])
                    ->whereRaw('WEEK(created_at) = ?', [$week])
                    ->when($outletId, function ($query) use ($outletId) {
                        return $query->where('outlet_id', $outletId);
                    })
                    ->groupBy('payment_method')
                    ->get();

                $weekCashSales = 0;
                $weekQrisSales = 0;

                foreach ($weekPayments as $method) {
                    $method_key = strtolower(trim($method->payment_method));
                    if (in_array($method_key, ['cash', 'tunai'])) {
                        $weekCashSales += $method->total_amount;
                    } elseif (in_array($method_key, ['qris', 'qriss'])) {
                        $weekQrisSales += $method->total_amount;
                    }
                }

                $weekData->cash_sales = $weekCashSales;
                $weekData->qris_sales = $weekQrisSales;

                // Get beverage sales for this week
                $weekData->beverage_sales = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->join('categories', 'categories.id', '=', 'products.category_id')
                    ->whereRaw('YEAR(orders.created_at) = ?', [$year])
                    ->whereRaw('WEEK(orders.created_at) = ?', [$week])
                    ->when($outletId, function ($query) use ($outletId) {
                        return $query->where('orders.outlet_id', $outletId);
                    })
                    ->where('categories.id', 2) // ID kategori minuman
                    ->sum(DB::raw('order_items.quantity * order_items.price'));
            }
        } elseif ($periodType === 'monthly') {
            // Query untuk data bulanan yang diperbaiki
            $salesData = Order::select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period_key"), DB::raw("DATE_FORMAT(MIN(created_at), '%M %Y') as period_label"), DB::raw('COUNT(*) as order_count'), DB::raw('SUM(total) as total_sales'), DB::raw('SUM(sub_total) as sub_total'), DB::raw('SUM(tax) as tax'), DB::raw('SUM(discount_amount) as discount_amount'), DB::raw('SUM(CASE WHEN payment_method = "qris" THEN CAST(qris_fee AS DECIMAL(10,2)) ELSE 0 END) as qris_fee'))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->when($outletId, function ($query) use ($outletId) {
                    return $query->where('outlet_id', $outletId);
                })
                ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
                ->orderBy('period_key')
                ->get();

            // Get payment data per month
            foreach ($salesData as $monthData) {
                list($year, $month) = explode('-', $monthData->period_key);

                $monthPayments = Order::select('payment_method', DB::raw('SUM(total) as total_amount'))
                    ->whereRaw('YEAR(created_at) = ?', [$year])
                    ->whereRaw('MONTH(created_at) = ?', [$month])
                    ->when($outletId, function ($query) use ($outletId) {
                        return $query->where('outlet_id', $outletId);
                    })
                    ->groupBy('payment_method')
                    ->get();

                $monthCashSales = 0;
                $monthQrisSales = 0;

                foreach ($monthPayments as $method) {
                    $method_key = strtolower(trim($method->payment_method));
                    if (in_array($method_key, ['cash', 'tunai'])) {
                        $monthCashSales += $method->total_amount;
                    } elseif (in_array($method_key, ['qris', 'qriss'])) {
                        $monthQrisSales += $method->total_amount;
                    }
                }

                $monthData->cash_sales = $monthCashSales;
                $monthData->qris_sales = $monthQrisSales;

                // Get beverage sales for this month
                $monthData->beverage_sales = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->join('categories', 'categories.id', '=', 'products.category_id')
                    ->whereRaw('YEAR(orders.created_at) = ?', [$year])
                    ->whereRaw('MONTH(orders.created_at) = ?', [$month])
                    ->when($outletId, function ($query) use ($outletId) {
                        return $query->where('orders.outlet_id', $outletId);
                    })
                    ->where('categories.id', 2) // ID kategori minuman
                    ->sum(DB::raw('order_items.quantity * order_items.price'));
            }
        }

        // Format judul laporan berdasarkan tipe periode
        $periodTypeLabels = [
            'daily' => 'Harian',
            'weekly' => 'Mingguan',
            'monthly' => 'Bulanan',
        ];

        $title = 'Laporan Ringkasan Penjualan ' . $periodTypeLabels[$periodType];
        $subtitle = 'Periode: ' . $startDate->translatedFormat('d M Y') . ' - ' . $endDate->translatedFormat('d M Y');

        if ($outletId) {
            $outlet = Outlet::find($outletId);
            $subtitle .= ' | Outlet: ' . $outlet->name;
        } else {
            $subtitle .= ' | Semua Outlet';
        }

        // Generate laporan berdasarkan format yang diminta
        if ($request->format === 'pdf') {
            // Create PDF with custom view data
            $pdf = PDF::loadView('pages.reports.sales_summary_pdf', compact('title', 'subtitle', 'totalRevenue', 'totalOrders', 'totalSubTotal', 'totalTax', 'totalDiscountAmount', 'beverageSales', 'qrisSales', 'cashSales', 'totalOpeningBalance', 'totalExpenses', 'totalQrisFee', 'closingBalance', 'dailyData', 'salesData', 'periodType', 'startDate', 'endDate'));

            // Set orientation to landscape
            $pdf->setPaper('a4', 'landscape');

            return $pdf->download('laporan_penjualan_' . $periodTypeLabels[$periodType] . '_' . $startDate->format('Y-m-d') . '_sampai_' . $endDate->format('Y-m-d') . '.pdf');
        } else {
            // ========= ENHANCED EXCEL REPORT GENERATION (SINGLE SHEET) ==========

            // Create a new spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Laporan Penjualan');

            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('Seblak Sulthane')
                ->setLastModifiedBy('Seblak Sulthane')
                ->setTitle($title)
                ->setSubject($subtitle)
                ->setDescription('Laporan Ringkasan Penjualan Seblak Sulthane')
                ->setKeywords('sales, report, seblak, sulthane')
                ->setCategory('Financial Reports');

            // Set the sheet to be scrollable from the beginning
            $spreadsheet->getActiveSheet()->getPageSetup()->setFitToWidth(1);
            $spreadsheet->getActiveSheet()->getPageSetup()->setFitToHeight(0);

            // Critical settings to ensure full scrolling ability
            $spreadsheet->getActiveSheet()->getSheetView()->setZoomScale(100);
            $spreadsheet->getActiveSheet()->getSheetView()->setZoomScaleNormal(100);

            // Set the active cell to A1 to ensure we start at the top
            $spreadsheet->getActiveSheet()->setSelectedCell('A1');

            // Disable any potential split panes
            $spreadsheet->getActiveSheet()->getPageSetup()->setHorizontalCentered(false);
            $spreadsheet->getActiveSheet()->getPageSetup()->setVerticalCentered(false);

            // Ensure view settings are normal
            $spreadsheet->getActiveSheet()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
            $spreadsheet->getActiveSheet()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);

            // Define commonly used styles
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'BFBFBF'],
                    ],
                ],
            ];

            $sectionStyle = [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'borders' => [
                    'outline' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '8EA9DB'],
                    ],
                ],
            ];

            $tableStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'BFBFBF'],
                    ],
                    'outline' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => '8EA9DB'],
                    ],
                ],
            ];

            $totalsStyle = [
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9E1F2'],
                ],
            ];

            // Add company header and title
            $sheet->setCellValue('A1', 'SEBLAK SULTHANE');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(20)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_DARKRED));

            // Report title and period
            $sheet->setCellValue('A3', $title);
            $sheet->setCellValue('A4', $subtitle);
            $sheet->mergeCells('A3:F3');
            $sheet->mergeCells('A4:F4');
            $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A4')->getFont()->setSize(12);

            // Add header border
            $titleBorderStyle = [
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => '808080'],
                    ],
                ],
            ];
            $sheet->getStyle('A1:F4')->applyFromArray($titleBorderStyle);

            // SECTION 1: SUMMARY KPIs
            $sheet->setCellValue('A6', 'RINGKASAN KPI');
            $sheet->mergeCells('A6:F6');
            $sheet->getStyle('A6')->applyFromArray($sectionStyle);

            $row = 7;
            // KPI Row 1: Key metrics
            $sheet->setCellValue('A' . $row, 'Total Penjualan:');
            $sheet->setCellValue('B' . $row, $totalRevenue);
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('B' . $row)->getFont()->setBold(true);

            $sheet->setCellValue('D' . $row, 'Jumlah Order:');
            $sheet->setCellValue('E' . $row, $totalOrders);
            $sheet->getStyle('E' . $row)->getFont()->setBold(true);

            $row++;
            $sheet->setCellValue('A' . $row, 'Penjualan Kotor:');
            $sheet->setCellValue('B' . $row, $totalSubTotal);
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0');

            $sheet->setCellValue('D' . $row, 'Rata-rata Order:');
            $sheet->setCellValue('E' . $row, $avgOrderValue);
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0');

            $row++;
            $sheet->setCellValue('A' . $row, 'Total Diskon:');
            $sheet->setCellValue('B' . $row, $totalDiscountAmount);
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0');

            $sheet->setCellValue('D' . $row, 'Penjualan Beverage:');
            $sheet->setCellValue('E' . $row, $beverageSales);
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0');

            $row++;
            $sheet->setCellValue('A' . $row, 'Total Pajak:');
            $sheet->setCellValue('B' . $row, $totalTax);
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0');

            // Apply nice borders to KPI section
            $kpiBoxStyle = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E6F0FF'], // Light blue
                ],
            ];
            $sheet->getStyle('A7:B' . $row)->applyFromArray(array_merge($tableStyle, $kpiBoxStyle));
            $sheet->getStyle('D7:E' . $row)->applyFromArray(array_merge($tableStyle, $kpiBoxStyle));

            // SECTION 2: PAYMENT METHODS
            $row += 2;
            $paymentRow = $row;
            $sheet->setCellValue('A' . $paymentRow, 'METODE PEMBAYARAN');
            $sheet->mergeCells('A' . $paymentRow . ':C' . $paymentRow);
            $sheet->getStyle('A' . $paymentRow)->applyFromArray($sectionStyle);

            $paymentRow++;
            $sheet->setCellValue('A' . $paymentRow, 'Metode');
            $sheet->setCellValue('B' . $paymentRow, 'Jumlah (Rp)');
            $sheet->setCellValue('C' . $paymentRow, 'Persentase');
            $sheet->getStyle('A' . $paymentRow . ':C' . $paymentRow)->applyFromArray($headerStyle);

            $paymentRow++;
            // Add payment methods data
            $startPaymentRow = $paymentRow;
            foreach ($paymentMethods as $method) {
                $sheet->setCellValue('A' . $paymentRow, ucfirst($method->payment_method));
                $sheet->setCellValue('B' . $paymentRow, $method->total_amount);
                $sheet->getStyle('B' . $paymentRow)->getNumberFormat()->setFormatCode('#,##0');

                // Calculate percentage
                $percentage = ($totalRevenue > 0) ? ($method->total_amount / $totalRevenue) : 0;
                $sheet->setCellValue('C' . $paymentRow, $percentage);
                $sheet->getStyle('C' . $paymentRow)->getNumberFormat()->setFormatCode('0.00%');

                $paymentRow++;
            }

            // Add total row for payments
            $sheet->setCellValue('A' . $paymentRow, 'TOTAL');
            $sheet->setCellValue('B' . $paymentRow, $totalRevenue);
            $sheet->setCellValue('C' . $paymentRow, '100%');
            $sheet->getStyle('B' . $paymentRow)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('A' . $paymentRow . ':C' . $paymentRow)->applyFromArray($totalsStyle);

            // Format payment method table
            $sheet->getStyle('A' . $startPaymentRow . ':C' . $paymentRow)->applyFromArray($tableStyle);

            // SECTION 3: CASH FLOW
            $row = $paymentRow + 2;
            $cashFlowRow = $row;
            $sheet->setCellValue('A' . $cashFlowRow, 'ARUS KAS');
            $sheet->mergeCells('A' . $cashFlowRow . ':C' . $cashFlowRow);
            $sheet->getStyle('A' . $cashFlowRow)->applyFromArray($sectionStyle);

            $cashFlowRow++;
            $sheet->setCellValue('A' . $cashFlowRow, 'Saldo Awal:');
            $sheet->setCellValue('B' . $cashFlowRow, $totalOpeningBalance);
            $sheet->getStyle('B' . $cashFlowRow)->getNumberFormat()->setFormatCode('#,##0');

            $cashFlowRow++;
            $sheet->setCellValue('A' . $cashFlowRow, 'Penjualan CASH:');
            $sheet->setCellValue('B' . $cashFlowRow, $cashSales);
            $sheet->getStyle('B' . $cashFlowRow)->getNumberFormat()->setFormatCode('#,##0');

            $cashFlowRow++;
            $sheet->setCellValue('A' . $cashFlowRow, 'Penjualan QRIS:');
            $sheet->setCellValue('B' . $cashFlowRow, $qrisSales);
            $sheet->getStyle('B' . $cashFlowRow)->getNumberFormat()->setFormatCode('#,##0');

            $cashFlowRow++;
            $sheet->setCellValue('A' . $cashFlowRow, 'Biaya Layanan QRIS:');
            $sheet->setCellValue('B' . $cashFlowRow, $totalQrisFee);
            $sheet->getStyle('B' . $cashFlowRow)->getNumberFormat()->setFormatCode('#,##0');

            $cashFlowRow++;
            $sheet->setCellValue('A' . $cashFlowRow, 'Total Pengeluaran:');
            $sheet->setCellValue('B' . $cashFlowRow, $totalExpenses);
            $sheet->getStyle('B' . $cashFlowRow)->getNumberFormat()->setFormatCode('#,##0');

            $cashFlowRow++;
            $sheet->setCellValue('A' . $cashFlowRow, 'Saldo Akhir:');
            $sheet->setCellValue('B' . $cashFlowRow, $closingBalance);
            $sheet->getStyle('A' . $cashFlowRow . ':B' . $cashFlowRow)->getFont()->setBold(true);
            $sheet->getStyle('B' . $cashFlowRow)->getNumberFormat()->setFormatCode('#,##0');

            // Highlight negative closing balance
            if ($closingBalance < 0) {
                $sheet->getStyle('B' . $cashFlowRow)->getFont()->getColor()->setRGB('FF0000');
            }

            // Format cash flow table
            $cashFlowBoxStyle = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E6F0FF'], // Light blue
                ],
            ];
            $sheet->getStyle('A' . ($row + 1) . ':B' . $cashFlowRow)->applyFromArray(array_merge($tableStyle, $cashFlowBoxStyle));

            // SECTION 4: DATA DETAILED BY PERIOD TYPE
            $row = $cashFlowRow + 2;
            $detailRow = $row;
            $sheet->setCellValue('A' . $detailRow, 'DATA ' . strtoupper($periodTypeLabels[$periodType]));

            if ($periodType === 'daily') {
                $sheet->mergeCells('A' . $detailRow . ':N' . $detailRow);
            } else {
                $sheet->mergeCells('A' . $detailRow . ':K' . $detailRow);
            }

            $sheet->getStyle('A' . $detailRow)->applyFromArray($sectionStyle);

            $detailRow++;

            if ($periodType === 'daily') {
                // Daily data headers - Added SALDO AWAL column
                $sheet->setCellValue('A' . $detailRow, 'TANGGAL');
                $sheet->setCellValue('B' . $detailRow, 'HARI');
                $sheet->setCellValue('C' . $detailRow, 'JUMLAH ORDER');
                $sheet->setCellValue('D' . $detailRow, 'PENJUALAN BERSIH');
                $sheet->setCellValue('E' . $detailRow, 'PENJUALAN KOTOR');
                $sheet->setCellValue('F' . $detailRow, 'DISKON');
                $sheet->setCellValue('G' . $detailRow, 'PAJAK');
                $sheet->setCellValue('H' . $detailRow, 'BEVERAGE');
                $sheet->setCellValue('I' . $detailRow, 'QRIS');
                $sheet->setCellValue('J' . $detailRow, 'BIAYA QRIS');
                $sheet->setCellValue('K' . $detailRow, 'CASH');
                $sheet->setCellValue('L' . $detailRow, 'SALDO AWAL'); // Added SALDO AWAL column
                $sheet->setCellValue('M' . $detailRow, 'PENGELUARAN');
                $sheet->setCellValue('N' . $detailRow, 'SALDO AKHIR');

                $sheet->getStyle('A' . $detailRow . ':N' . $detailRow)->applyFromArray($headerStyle);

                // Sort data by date, newest first for better user experience
                usort($dailyData, function ($a, $b) {
                    return strtotime($b['date']) - strtotime($a['date']);
                });

                // Add data
                $detailRow++;
                $startDetailRow = $detailRow;
                foreach ($dailyData as $day) {
                    $dateObj = Carbon::parse($day['date']);
                    $sheet->setCellValue('A' . $detailRow, $dateObj->format('d/m/Y'));
                    $sheet->setCellValue('B' . $detailRow, $day['day_name']);
                    $sheet->setCellValue('C' . $detailRow, $day['orders_count']);
                    $sheet->setCellValue('D' . $detailRow, $day['revenue']);
                    $sheet->setCellValue('E' . $detailRow, $day['sub_total']);
                    $sheet->setCellValue('F' . $detailRow, $day['discount_amount']);
                    $sheet->setCellValue('G' . $detailRow, $day['tax']);
                    $sheet->setCellValue('H' . $detailRow, $day['beverage_sales']);
                    $sheet->setCellValue('I' . $detailRow, $day['qris_sales']);
                    $sheet->setCellValue('J' . $detailRow, $day['qris_fee']);
                    $sheet->setCellValue('K' . $detailRow, $day['cash_sales']);
                    $sheet->setCellValue('L' . $detailRow, $day['opening_balance']); // Added SALDO AWAL value
                    $sheet->setCellValue('M' . $detailRow, $day['expenses']);
                    $sheet->setCellValue('N' . $detailRow, $day['closing_balance']);

                    // Format numbers
                    $sheet->getStyle('D' . $detailRow . ':N' . $detailRow)->getNumberFormat()->setFormatCode('#,##0');

                    // Add weekend highlighting
                    if (in_array($day['day_name'], ['Sabtu', 'Minggu'])) {
                        $sheet->getStyle('A' . $detailRow . ':N' . $detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4D6');
                    }

                    // Highlight negative closing balance
                    if ($day['closing_balance'] < 0) {
                        $sheet->getStyle('N' . $detailRow)->getFont()->getColor()->setRGB('FF0000');
                    }

                    // Add zebra striping for better readability
                    if ($detailRow % 2 == 0) {
                        $sheet->getStyle('A' . $detailRow . ':N' . $detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
                    }

                    $detailRow++;
                }

                // Add totals row
                $sheet->setCellValue('A' . $detailRow, 'TOTAL');
                $sheet->mergeCells('A' . $detailRow . ':B' . $detailRow);
                $sheet->setCellValue('C' . $detailRow, $totalOrders);
                $sheet->setCellValue('D' . $detailRow, $totalRevenue);
                $sheet->setCellValue('E' . $detailRow, $totalSubTotal);
                $sheet->setCellValue('F' . $detailRow, $totalDiscountAmount);
                $sheet->setCellValue('G' . $detailRow, $totalTax);
                $sheet->setCellValue('H' . $detailRow, $beverageSales);
                $sheet->setCellValue('I' . $detailRow, $qrisSales);
                $sheet->setCellValue('J' . $detailRow, $totalQrisFee);
                $sheet->setCellValue('K' . $detailRow, $cashSales);
                $sheet->setCellValue('L' . $detailRow, $totalOpeningBalance); // Added total opening balance
                $sheet->setCellValue('M' . $detailRow, $totalExpenses);
                $sheet->setCellValue('N' . $detailRow, $closingBalance);

                // Format totals
                $sheet->getStyle('A' . $detailRow . ':N' . $detailRow)->applyFromArray($totalsStyle);
                $sheet->getStyle('D' . $detailRow . ':N' . $detailRow)->getNumberFormat()->setFormatCode('#,##0');

                // Format the entire data table
                $sheet->getStyle('A' . $startDetailRow . ':N' . $detailRow)->applyFromArray($tableStyle);

                // Enable filtering
                $sheet->setAutoFilter('A' . ($row + 1) . ':N' . ($detailRow - 1));
            } elseif ($periodType === 'weekly' || $periodType === 'monthly') {
                // Column headers for weekly/monthly data
                $sheet->setCellValue('A' . $detailRow, 'PERIODE');
                $sheet->setCellValue('B' . $detailRow, 'JUMLAH ORDER');
                $sheet->setCellValue('C' . $detailRow, 'PENJUALAN BERSIH');
                $sheet->setCellValue('D' . $detailRow, 'PENJUALAN KOTOR');
                $sheet->setCellValue('E' . $detailRow, 'DISKON');
                $sheet->setCellValue('F' . $detailRow, 'PAJAK');
                $sheet->setCellValue('G' . $detailRow, 'BEVERAGES');
                $sheet->setCellValue('H' . $detailRow, 'QRIS');
                $sheet->setCellValue('I' . $detailRow, 'BIAYA QRIS');
                $sheet->setCellValue('J' . $detailRow, 'CASH');
                $sheet->setCellValue('K' . $detailRow, 'AVG ORDER VALUE');

                $sheet->getStyle('A' . $detailRow . ':K' . $detailRow)->applyFromArray($headerStyle);

                // Sort data, newest first
                $salesData = $salesData->sortByDesc(function ($item) {
                    return $item->period_key;
                });

                // Add data rows
                $detailRow++;
                $startDetailRow = $detailRow;
                foreach ($salesData as $period) {
                    $sheet->setCellValue('A' . $detailRow, $period->period_label);
                    $sheet->setCellValue('B' . $detailRow, $period->order_count);
                    $sheet->setCellValue('C' . $detailRow, $period->total_sales);
                    $sheet->setCellValue('D' . $detailRow, $period->sub_total);
                    $sheet->setCellValue('E' . $detailRow, $period->discount_amount);
                    $sheet->setCellValue('F' . $detailRow, $period->tax);
                    $sheet->setCellValue('G' . $detailRow, $period->beverage_sales ?? 0);
                    $sheet->setCellValue('H' . $detailRow, $period->qris_sales ?? 0);
                    $sheet->setCellValue('I' . $detailRow, $period->qris_fee);
                    $sheet->setCellValue('J' . $detailRow, $period->cash_sales ?? 0);

                    // Calculate average order value
                    $avgValue = $period->order_count > 0 ? $period->total_sales / $period->order_count : 0;
                    $sheet->setCellValue('K' . $detailRow, $avgValue);

                    // Format numbers
                    $sheet->getStyle('C' . $detailRow . ':K' . $detailRow)->getNumberFormat()->setFormatCode('#,##0');

                    // Add zebra striping for better readability
                    if ($detailRow % 2 == 0) {
                        $sheet->getStyle('A' . $detailRow . ':K' . $detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
                    }

                    $detailRow++;
                }

                // Add totals row
                $sheet->setCellValue('A' . $detailRow, 'TOTAL');
                $sheet->setCellValue('B' . $detailRow, $totalOrders);
                $sheet->setCellValue('C' . $detailRow, $totalRevenue);
                $sheet->setCellValue('D' . $detailRow, $totalSubTotal);
                $sheet->setCellValue('E' . $detailRow, $totalDiscountAmount);
                $sheet->setCellValue('F' . $detailRow, $totalTax);
                $sheet->setCellValue('G' . $detailRow, $beverageSales);
                $sheet->setCellValue('H' . $detailRow, $qrisSales);
                $sheet->setCellValue('I' . $detailRow, $totalQrisFee);
                $sheet->setCellValue('J' . $detailRow, $cashSales);
                $sheet->setCellValue('K' . $detailRow, $avgOrderValue);

                // Format totals
                $sheet->getStyle('A' . $detailRow . ':K' . $detailRow)->applyFromArray($totalsStyle);
                $sheet->getStyle('C' . $detailRow . ':K' . $detailRow)->getNumberFormat()->setFormatCode('#,##0');

                // Format the entire data table
                $sheet->getStyle('A' . $startDetailRow . ':K' . $detailRow)->applyFromArray($tableStyle);

                // Enable filtering
                $sheet->setAutoFilter('A' . ($row + 1) . ':K' . ($detailRow - 1));
            }

            // FOOTER SECTION - Generated information
            $row = $detailRow + 3;
            $sheet->setCellValue('A' . $row, 'Laporan dibuat pada: ' . now()->format('d M Y H:i'));

            if ($periodType === 'daily') {
                $sheet->mergeCells('A' . $row . ':N' . $row);
            } else {
                $sheet->mergeCells('A' . $row . ':K' . $row);
            }

            $sheet->getStyle('A' . $row)->getFont()->setItalic(true);

            // Auto-size columns for better readability
            if ($periodType === 'daily') {
                foreach (range('A', 'N') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            } else {
                foreach (range('A', 'K') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            }

            // IMPORTANT: Do not use freeze panes at all to ensure full scrolling ability
            // Instead, we'll use cell styling to make the headers stand out

            // Remove any existing freeze panes
            $sheet->unfreezePane();

            // Make sure all headers are bold and stand out even without freezing
            if ($periodType === 'daily') {
                $sheet->getStyle('A' . ($row - $detailRow + $startDetailRow - 1) . ':N' . ($row - $detailRow + $startDetailRow - 1))->getFont()->setBold(true);
            } else {
                $sheet->getStyle('A' . ($row - $detailRow + $startDetailRow - 1) . ':K' . ($row - $detailRow + $startDetailRow - 1))->getFont()->setBold(true);
            }

            // Remove all protection which can interfere with scrolling
            $sheet->getProtection()->setSheet(false);

            // Configure proper view settings to ensure scrolling works properly
            $sheet->getSheetView()->setZoomScale(100); // Normal zoom
            $sheet->getSheetView()->setZoomScaleNormal(100);
            $sheet->getSheetView()->setView(\PhpOffice\PhpSpreadsheet\Worksheet\SheetView::SHEETVIEW_NORMAL);

            // Set the active cell to A1 to ensure we start at the top
            $sheet->setSelectedCell('A1');

            // Set filename and headers
            $filename = 'laporan_penjualan_' . $periodTypeLabels[$periodType] . '_' . $startDate->format('Y-m-d') . '_sampai_' . $endDate->format('Y-m-d') . '.xlsx';

            // Create the writer with additional options to ensure Excel compatibility
            $writer = new Xlsx($spreadsheet);

            // Additional settings to improve Excel compatibility
            $writer->setOffice2003Compatibility(false);
            $writer->setPreCalculateFormulas(true);

            // Standard headers
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: max-age=1');

            // Additional headers to prevent caching issues
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // Always modified
            header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header('Pragma: public'); // HTTP/1.0

            $writer->save('php://output');
            exit();
        }
    }

    /**
     * Generate Outlet Performance Report
     */
    public function outletPerformance(Request $request)
    {
        // Validate request
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'required|in:pdf,excel',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        // Get performance data for all outlets
        $outletPerformance = Order::select('outlets.id as outlet_id', 'outlets.name as outlet_name', DB::raw('COUNT(orders.id) as total_orders'), DB::raw('SUM(orders.total) as total_revenue'), DB::raw('SUM(orders.tax) as total_tax'), DB::raw('SUM(orders.discount_amount) as total_discount'), DB::raw('COUNT(DISTINCT orders.member_id) as total_customers'), DB::raw('AVG(orders.total) as avg_order_value'))
            ->join('outlets', 'outlets.id', '=', 'orders.outlet_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('outlets.id', 'outlets.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        // Get daily trends per outlet
        $dailyTrends = Order::select('outlets.id as outlet_id', 'outlets.name as outlet_name', DB::raw('DATE(orders.created_at) as date'), DB::raw('SUM(orders.total) as daily_revenue'), DB::raw('COUNT(orders.id) as daily_orders'))
            ->join('outlets', 'outlets.id', '=', 'orders.outlet_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('outlets.id', 'outlets.name', 'date')
            ->orderBy('date')
            ->get()
            ->groupBy('outlet_id');

        // Format the report title
        $title = 'Outlet Performance Report';
        $subtitle = 'Period: ' . $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y');

        // Generate the report based on requested format
        if ($request->format === 'pdf') {
            $pdf = PDF::loadView('pages.reports.outlet_performance_pdf', compact('title', 'subtitle', 'outletPerformance', 'dailyTrends', 'startDate', 'endDate'));

            return $pdf->download('outlet_performance_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.pdf');
        } else {
            // Create an Excel spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set spreadsheet metadata
            $spreadsheet->getProperties()->setCreator('Seblak Sulthane')->setLastModifiedBy('Seblak Sulthane')->setTitle($title)->setSubject($subtitle)->setDescription('Outlet Performance Report for Seblak Sulthane');

            // Format the header
            $sheet->setCellValue('A1', $title);
            $sheet->setCellValue('A2', $subtitle);
            $sheet->mergeCells('A1:H1');
            $sheet->mergeCells('A2:H2');

            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A2')->getFont()->setSize(12);

            // Performance summary section
            $sheet->setCellValue('A4', 'OUTLET PERFORMANCE SUMMARY');
            $sheet->getStyle('A4')->getFont()->setBold(true);

            // Headers
            $sheet->setCellValue('A5', 'Outlet');
            $sheet->setCellValue('B5', 'Total Orders');
            $sheet->setCellValue('C5', 'Total Revenue');
            $sheet->setCellValue('D5', 'Total Tax');
            $sheet->setCellValue('E5', 'Total Discounts');
            $sheet->setCellValue('F5', 'Total Customers');
            $sheet->setCellValue('G5', 'Avg Order Value');
            $sheet->getStyle('A5:G5')->getFont()->setBold(true);

            // Data rows
            $row = 6;
            foreach ($outletPerformance as $outlet) {
                $sheet->setCellValue('A' . $row, $outlet->outlet_name);
                $sheet->setCellValue('B' . $row, $outlet->total_orders);
                $sheet->setCellValue('C' . $row, $outlet->total_revenue);
                $sheet->setCellValue('D' . $row, $outlet->total_tax);
                $sheet->setCellValue('E' . $row, $outlet->total_discount);
                $sheet->setCellValue('F' . $row, $outlet->total_customers);
                $sheet->setCellValue('G' . $row, $outlet->avg_order_value);

                // Format numbers
                $sheet
                    ->getStyle('C' . $row . ':E' . $row)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
                $sheet
                    ->getStyle('G' . $row)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                $row++;
            }

            // Create separate sheets for each outlet's daily trends
            foreach ($dailyTrends as $outletId => $trends) {
                $outletName = $trends->first()->outlet_name;
                $sheetName = substr(str_replace(' ', '_', $outletName), 0, 30); // Make sure sheet name is valid

                // Create a new sheet for this outlet
                $trendSheet = $spreadsheet->createSheet();
                $trendSheet->setTitle($sheetName);

                // Headers
                $trendSheet->setCellValue('A1', 'Daily Trends: ' . $outletName);
                $trendSheet->mergeCells('A1:D1');
                $trendSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                $trendSheet->setCellValue('A3', 'Date');
                $trendSheet->setCellValue('B3', 'Revenue');
                $trendSheet->setCellValue('C3', 'Orders');
                $trendSheet->getStyle('A3:C3')->getFont()->setBold(true);

                // Data rows
                $trendRow = 4;
                foreach ($trends as $trend) {
                    $trendSheet->setCellValue('A' . $trendRow, $trend->date);
                    $trendSheet->setCellValue('B' . $trendRow, $trend->daily_revenue);
                    $trendSheet->setCellValue('C' . $trendRow, $trend->daily_orders);

                    $trendSheet
                        ->getStyle('B' . $trendRow)
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');

                    $trendRow++;
                }

                // Auto-size columns
                foreach (range('A', 'C') as $col) {
                    $trendSheet->getColumnDimension($col)->setAutoSize(true);
                }
            }

            // Auto-size columns on main sheet
            foreach (range('A', 'G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Set first sheet as active
            $spreadsheet->setActiveSheetIndex(0);

            // Set filename and headers
            $filename = 'outlet_performance_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.xlsx';

            // Create the writer and output the file
            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit();
        }
    }

    /**
     * Generate Raw Materials Purchase Report
     */
    public function materialPurchases(Request $request)
    {
        // Validate request
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'outlet_id' => 'nullable|exists:outlets,id',
            'format' => 'required|in:pdf,excel',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $outletId = $request->outlet_id;

        // Base query for material orders
        $query = MaterialOrder::with(['franchise', 'items.rawMaterial'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Filter by outlet if provided
        if ($outletId) {
            $query->where('franchise_id', $outletId);
        } else if (Auth::user()->role !== 'owner') {
            // If not owner, restrict to user's outlet
            $query->where('franchise_id', Auth::user()->outlet_id);
        }

        // Get overview statistics
        $totalPurchaseAmount = $query->sum('total_amount');
        $totalOrderCount = $query->count();

        // Get daily breakdown data
        $dailyData = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $currentDateStr = $currentDate->format('Y-m-d');

            // Get orders for this date
            $dailyOrdersQuery = MaterialOrder::with(['items'])
                ->whereDate('created_at', $currentDateStr);

            if ($outletId) {
                $dailyOrdersQuery->where('franchise_id', $outletId);
            } else if (Auth::user()->role !== 'owner') {
                $dailyOrdersQuery->where('franchise_id', Auth::user()->outlet_id);
            }

            $dailyOrders = $dailyOrdersQuery->get();

            // Calculate daily stats
            $dailyTotalAmount = $dailyOrders->sum('total_amount');
            $dailyOrderCount = $dailyOrders->count();

            // Calculate total items across all orders for this day
            $dailyItemCount = 0;
            foreach ($dailyOrders as $order) {
                $dailyItemCount += $order->items->count();
            }

            // Collect payment methods used on this day
            $paymentMethods = $dailyOrders->pluck('payment_method')->unique()->implode(', ');

            // Add to daily data if there were any orders
            if ($dailyOrderCount > 0) {
                $dailyData[] = [
                    'date' => $currentDateStr,
                    'day_name' => $currentDate->locale('id')->isoFormat('dddd'),
                    'order_count' => $dailyOrderCount,
                    'item_count' => $dailyItemCount,
                    'payment_methods' => $paymentMethods,
                    'total_amount' => $dailyTotalAmount
                ];
            } else {
                // If no orders on this day, still add the entry with zeros
                $dailyData[] = [
                    'date' => $currentDateStr,
                    'day_name' => $currentDate->locale('id')->isoFormat('dddd'),
                    'order_count' => 0,
                    'item_count' => 0,
                    'payment_methods' => '-',
                    'total_amount' => 0
                ];
            }

            $currentDate->addDay();
        }

        // Format the report title
        $title = 'Laporan Pembelian Bahan Baku';
        $subtitle = 'Periode: ' . $startDate->translatedFormat('d M Y') . ' - ' . $endDate->translatedFormat('d M Y');

        if ($outletId) {
            $outlet = Outlet::find($outletId);
            $subtitle .= ' | Outlet: ' . $outlet->name;
        } else {
            $subtitle .= ' | ' . (Auth::user()->role === 'owner' ? 'Semua Outlet' : 'Outlet: ' . Auth::user()->outlet->name);
        }

        // Generate the report based on requested format
        if ($request->format === 'pdf') {
            $pdf = PDF::loadView('pages.reports.material_purchases_pdf', compact(
                'title',
                'subtitle',
                'totalPurchaseAmount',
                'totalOrderCount',
                'dailyData',
                'startDate',
                'endDate'
            ));

            // Set orientation to landscape
            $pdf->setPaper('a4', 'landscape');

            return $pdf->download('laporan_pembelian_bahan_baku_' . $startDate->format('Y-m-d') . '_sampai_' . $endDate->format('Y-m-d') . '.pdf');
        } else {
            // Create an Excel spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Laporan Bahan Baku');

            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('Seblak Sulthane')
                ->setLastModifiedBy('Seblak Sulthane')
                ->setTitle($title)
                ->setSubject($subtitle)
                ->setDescription('Laporan Pembelian Bahan Baku Seblak Sulthane')
                ->setKeywords('materials, report, seblak, sulthane')
                ->setCategory('Financial Reports');

            // Define commonly used styles
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'BFBFBF'],
                    ],
                ],
            ];

            $sectionStyle = [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'borders' => [
                    'outline' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '8EA9DB'],
                    ],
                ],
            ];

            $tableStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'BFBFBF'],
                    ],
                    'outline' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => '8EA9DB'],
                    ],
                ],
            ];

            $totalsStyle = [
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9E1F2'],
                ],
            ];

            // Add company header and title
            $sheet->setCellValue('A1', 'SEBLAK SULTHANE');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(20)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_DARKRED));

            // Report title and period
            $sheet->setCellValue('A3', $title);
            $sheet->setCellValue('A4', $subtitle);
            $sheet->mergeCells('A3:F3');
            $sheet->mergeCells('A4:F4');
            $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A4')->getFont()->setSize(12);

            // Add header border
            $titleBorderStyle = [
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => '808080'],
                    ],
                ],
            ];
            $sheet->getStyle('A1:F4')->applyFromArray($titleBorderStyle);

            // SECTION 1: SUMMARY KPIs
            $sheet->setCellValue('A6', 'RINGKASAN');
            $sheet->mergeCells('A6:F6');
            $sheet->getStyle('A6')->applyFromArray($sectionStyle);

            $row = 7;
            // Key metrics
            $sheet->setCellValue('A' . $row, 'Total Pengeluaran Bahan Baku:');
            $sheet->setCellValue('B' . $row, $totalPurchaseAmount);
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('B' . $row)->getFont()->setBold(true);

            $sheet->setCellValue('D' . $row, 'Jumlah Pemesanan:');
            $sheet->setCellValue('E' . $row, $totalOrderCount);
            $sheet->getStyle('E' . $row)->getFont()->setBold(true);

            // Apply nice borders to KPI section
            $kpiBoxStyle = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E6F0FF'], // Light blue
                ],
            ];
            $sheet->getStyle('A7:B' . $row)->applyFromArray(array_merge($tableStyle, $kpiBoxStyle));
            $sheet->getStyle('D7:E' . $row)->applyFromArray(array_merge($tableStyle, $kpiBoxStyle));

            // SECTION 2: DAILY BREAKDOWN
            $row += 2;
            $detailRow = $row;
            $sheet->setCellValue('A' . $detailRow, 'DATA HARIAN');
            $sheet->mergeCells('A' . $detailRow . ':F' . $detailRow);
            $sheet->getStyle('A' . $detailRow)->applyFromArray($sectionStyle);

            $detailRow++;

            // Headers for daily breakdown
            $sheet->setCellValue('A' . $detailRow, 'TANGGAL');
            $sheet->setCellValue('B' . $detailRow, 'HARI');
            $sheet->setCellValue('C' . $detailRow, 'JUMLAH ORDER');
            $sheet->setCellValue('D' . $detailRow, 'JUMLAH ITEM');
            $sheet->setCellValue('E' . $detailRow, 'METODE PEMBAYARAN');
            $sheet->setCellValue('F' . $detailRow, 'TOTAL PEMBELIAN');
            $sheet->getStyle('A' . $detailRow . ':F' . $detailRow)->applyFromArray($headerStyle);

            // Sort data by date, newest first for better user experience
            usort($dailyData, function ($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            // Add data
            $detailRow++;
            $startDetailRow = $detailRow;

            foreach ($dailyData as $day) {
                $dateObj = Carbon::parse($day['date']);
                $sheet->setCellValue('A' . $detailRow, $dateObj->format('d/m/Y'));
                $sheet->setCellValue('B' . $detailRow, $day['day_name']);
                $sheet->setCellValue('C' . $detailRow, $day['order_count']);
                $sheet->setCellValue('D' . $detailRow, $day['item_count']);
                $sheet->setCellValue('E' . $detailRow, $day['payment_methods']);
                $sheet->setCellValue('F' . $detailRow, $day['total_amount']);

                // Format numbers
                $sheet->getStyle('F' . $detailRow)->getNumberFormat()->setFormatCode('#,##0');

                // Add weekend highlighting
                if (in_array($day['day_name'], ['Sabtu', 'Minggu'])) {
                    $sheet->getStyle('A' . $detailRow . ':F' . $detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4D6');
                }

                // Add zebra striping for better readability
                if ($detailRow % 2 == 0) {
                    $sheet->getStyle('A' . $detailRow . ':F' . $detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
                }

                $detailRow++;
            }

            // Add totals row
            $sheet->setCellValue('A' . $detailRow, 'TOTAL');
            $sheet->mergeCells('A' . $detailRow . ':B' . $detailRow);
            $sheet->setCellValue('C' . $detailRow, $totalOrderCount);

            // Calculate total items from daily data
            $totalItems = array_sum(array_column($dailyData, 'item_count'));
            $sheet->setCellValue('D' . $detailRow, $totalItems);

            $sheet->setCellValue('E' . $detailRow, '-');
            $sheet->setCellValue('F' . $detailRow, $totalPurchaseAmount);

            // Format totals
            $sheet->getStyle('A' . $detailRow . ':F' . $detailRow)->applyFromArray($totalsStyle);
            $sheet->getStyle('F' . $detailRow)->getNumberFormat()->setFormatCode('#,##0');

            // Format the entire data table
            $sheet->getStyle('A' . $startDetailRow . ':F' . $detailRow)->applyFromArray($tableStyle);

            // Enable filtering
            $sheet->setAutoFilter('A' . ($row + 1) . ':F' . ($detailRow - 1));

            // FOOTER SECTION - Generated information
            $row = $detailRow + 3;
            $sheet->setCellValue('A' . $row, 'Laporan dibuat pada: ' . now()->format('d M Y H:i'));
            $sheet->mergeCells('A' . $row . ':F' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setItalic(true);

            // Auto-size columns for better readability
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Remove any existing freeze panes
            $sheet->unfreezePane();

            // Make sure all headers are bold and stand out
            $sheet->getStyle('A' . ($row - $detailRow + $startDetailRow - 1) . ':F' . ($row - $detailRow + $startDetailRow - 1))->getFont()->setBold(true);

            // Remove all protection which can interfere with scrolling
            $sheet->getProtection()->setSheet(false);

            // Configure proper view settings
            $sheet->getSheetView()->setZoomScale(100); // Normal zoom
            $sheet->getSheetView()->setZoomScaleNormal(100);
            $sheet->getSheetView()->setView(\PhpOffice\PhpSpreadsheet\Worksheet\SheetView::SHEETVIEW_NORMAL);

            // Set the active cell to A1
            $sheet->setSelectedCell('A1');

            // Set filename and headers
            $filename = 'laporan_pembelian_bahan_baku_' . $startDate->format('Y-m-d') . '_sampai_' . $endDate->format('Y-m-d') . '.xlsx';

            // Create the writer
            $writer = new Xlsx($spreadsheet);

            // Additional settings to improve Excel compatibility
            $writer->setOffice2003Compatibility(false);
            $writer->setPreCalculateFormulas(true);

            // Standard headers
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: max-age=1');

            // Additional headers to prevent caching issues
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // Always modified
            header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header('Pragma: public'); // HTTP/1.0

            $writer->save('php://output');
            exit();
        }
    }

    /**
     * Generate Product Performance Report
     */
    public function productPerformance(Request $request)
    {
        // Validate request
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'outlet_id' => 'nullable|exists:outlets,id',
            'format' => 'required|in:pdf,excel',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $outletId = $request->outlet_id;

        // Get product performance data - FIXED QUERY with price instead of price_per_unit
        $productPerformance = OrderItem::select('products.id as product_id', 'products.name as product_name', 'categories.name as category_name', DB::raw('SUM(order_items.quantity) as total_quantity'), DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue'), DB::raw('COUNT(DISTINCT orders.id) as order_count'))
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->when($outletId, function ($query) use ($outletId) {
                return $query->where('orders.outlet_id', $outletId);
            })
            ->groupBy('products.id', 'products.name', 'categories.name')
            ->orderBy('total_quantity', 'desc')
            ->get();

        // Get category breakdown - FIXED QUERY with price instead of price_per_unit
        $categoryBreakdown = OrderItem::select('categories.name as category_name', DB::raw('SUM(order_items.quantity) as total_quantity'), DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue'), DB::raw('COUNT(DISTINCT products.id) as product_count'))
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->when($outletId, function ($query) use ($outletId) {
                return $query->where('orders.outlet_id', $outletId);
            })
            ->groupBy('categories.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        // Format the report title
        $title = 'Product Performance Report';
        $subtitle = 'Period: ' . $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y');

        if ($outletId) {
            $outlet = Outlet::find($outletId);
            $subtitle .= ' | Outlet: ' . $outlet->name;
        } else {
            $subtitle .= ' | All Outlets';
        }

        // Generate the report based on requested format
        if ($request->format === 'pdf') {
            $pdf = PDF::loadView('pages.reports.product_performance_pdf', compact('title', 'subtitle', 'productPerformance', 'categoryBreakdown', 'startDate', 'endDate'));

            return $pdf->download('product_performance_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.pdf');
        } else {
            // Create an Excel spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set spreadsheet metadata
            $spreadsheet->getProperties()->setCreator('Seblak Sulthane')->setLastModifiedBy('Seblak Sulthane')->setTitle($title)->setSubject($subtitle)->setDescription('Product Performance Report for Seblak Sulthane');

            // Format the header
            $sheet->setCellValue('A1', $title);
            $sheet->setCellValue('A2', $subtitle);
            $sheet->mergeCells('A1:F1');
            $sheet->mergeCells('A2:F2');

            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A2')->getFont()->setSize(12);

            // Category breakdown section
            $sheet->setCellValue('A4', 'CATEGORY BREAKDOWN');
            $sheet->getStyle('A4')->getFont()->setBold(true);

            // Headers
            $sheet->setCellValue('A5', 'Category');
            $sheet->setCellValue('B5', 'Product Count');
            $sheet->setCellValue('C5', 'Total Quantity Sold');
            $sheet->setCellValue('D5', 'Total Revenue');
            $sheet->getStyle('A5:D5')->getFont()->setBold(true);

            // Data rows
            $row = 6;
            foreach ($categoryBreakdown as $category) {
                $sheet->setCellValue('A' . $row, $category->category_name);
                $sheet->setCellValue('B' . $row, $category->product_count);
                $sheet->setCellValue('C' . $row, $category->total_quantity);
                $sheet->setCellValue('D' . $row, $category->total_revenue);

                // Format numbers
                $sheet
                    ->getStyle('D' . $row)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                $row++;
            }

            // Product performance section
            $row += 2;
            $productRow = $row;
            $sheet->setCellValue('A' . $productRow, 'PRODUCT PERFORMANCE');
            $sheet
                ->getStyle('A' . $productRow)
                ->getFont()
                ->setBold(true);

            $productRow++;
            $sheet->setCellValue('A' . $productRow, 'Product');
            $sheet->setCellValue('B' . $productRow, 'Category');
            $sheet->setCellValue('C' . $productRow, 'Quantity Sold');
            $sheet->setCellValue('D' . $productRow, 'Revenue');
            $sheet->setCellValue('E' . $productRow, 'Order Count');
            $sheet
                ->getStyle('A' . $productRow . ':E' . $productRow)
                ->getFont()
                ->setBold(true);

            $productRow++;
            foreach ($productPerformance as $product) {
                $sheet->setCellValue('A' . $productRow, $product->product_name);
                $sheet->setCellValue('B' . $productRow, $product->category_name);
                $sheet->setCellValue('C' . $productRow, $product->total_quantity);
                $sheet->setCellValue('D' . $productRow, $product->total_revenue);
                $sheet->setCellValue('E' . $productRow, $product->order_count);

                // Format numbers
                $sheet
                    ->getStyle('D' . $productRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                $productRow++;
            }

            // Create a sheet for product rankings
            $rankSheet = $spreadsheet->createSheet();
            $rankSheet->setTitle('Product Rankings');

            // Headers
            $rankSheet->setCellValue('A1', 'Product Rankings');
            $rankSheet->mergeCells('A1:D1');
            $rankSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            $rankSheet->setCellValue('A3', 'Rank');
            $rankSheet->setCellValue('B3', 'Product');
            $rankSheet->setCellValue('C3', 'Category');
            $rankSheet->setCellValue('D3', 'Quantity Sold');
            $rankSheet->getStyle('A3:D3')->getFont()->setBold(true);

            // Data rows
            $rankRow = 4;
            $rank = 1;
            foreach ($productPerformance as $product) {
                $rankSheet->setCellValue('A' . $rankRow, $rank);
                $rankSheet->setCellValue('B' . $rankRow, $product->product_name);
                $rankSheet->setCellValue('C' . $rankRow, $product->category_name);
                $rankSheet->setCellValue('D' . $rankRow, $product->total_quantity);

                $rank++;
                $rankRow++;
            }

            // Auto-size columns on all sheets
            foreach (range('A', 'E') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
                if ($col <= 'D') {
                    $rankSheet->getColumnDimension($col)->setAutoSize(true);
                }
            }

            // Set first sheet as active
            $spreadsheet->setActiveSheetIndex(0);

            // Set filename and headers
            $filename = 'product_performance_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.xlsx';

            // Create the writer and output the file
            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit();
        }
    }

    /**
     * Generate Customer Insights Report
     */
    public function customerInsights(Request $request)
    {
        // Validate request
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'required|in:pdf,excel',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        // Get top customers
        $topCustomers = Order::select('members.id as member_id', 'members.name as member_name', 'members.phone as member_phone', DB::raw('COUNT(orders.id) as total_transactions'), DB::raw('SUM(orders.total) as total_spent'), DB::raw('AVG(orders.total) as avg_order_value'))
            ->join('members', 'members.id', '=', 'orders.member_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('members.id', 'members.name', 'members.phone')
            ->orderBy('total_spent', 'desc')
            ->limit(50)
            ->get();

        // Get member vs non-member stats
        $memberStats = Order::select(DB::raw('CASE WHEN member_id IS NULL THEN "Non-Member" ELSE "Member" END as customer_type'), DB::raw('COUNT(*) as order_count'), DB::raw('SUM(total) as total_revenue'), DB::raw('AVG(total) as avg_order_value'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('CASE WHEN member_id IS NULL THEN "Non-Member" ELSE "Member" END'))
            ->get();

        // Get member growth
        $memberGrowth = Member::select(DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'), DB::raw('COUNT(*) as new_members'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
            ->orderBy('month')
            ->get();

        // Format the report title
        $title = 'Customer Insights Report';
        $subtitle = 'Period: ' . $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y');

        // Generate the report based on requested format
        if ($request->format === 'pdf') {
            $pdf = PDF::loadView('pages.reports.customer_insights_pdf', compact('title', 'subtitle', 'topCustomers', 'memberStats', 'memberGrowth', 'startDate', 'endDate'));

            return $pdf->download('customer_insights_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.pdf');
        } else {
            // Create an Excel spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set spreadsheet metadata
            $spreadsheet->getProperties()->setCreator('Seblak Sulthane')->setLastModifiedBy('Seblak Sulthane')->setTitle($title)->setSubject($subtitle)->setDescription('Customer Insights Report for Seblak Sulthane');

            // Format the header
            $sheet->setCellValue('A1', $title);
            $sheet->setCellValue('A2', $subtitle);
            $sheet->mergeCells('A1:F1');
            $sheet->mergeCells('A2:F2');

            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A2')->getFont()->setSize(12);

            // Member vs Non-member section
            $sheet->setCellValue('A4', 'MEMBER VS NON-MEMBER COMPARISON');
            $sheet->getStyle('A4')->getFont()->setBold(true);

            // Headers
            $sheet->setCellValue('A5', 'Customer Type');
            $sheet->setCellValue('B5', 'Order Count');
            $sheet->setCellValue('C5', 'Total Revenue');
            $sheet->setCellValue('D5', 'Avg Order Value');
            $sheet->getStyle('A5:D5')->getFont()->setBold(true);

            // Data rows
            $row = 6;
            foreach ($memberStats as $stat) {
                $sheet->setCellValue('A' . $row, $stat->customer_type);
                $sheet->setCellValue('B' . $row, $stat->order_count);
                $sheet->setCellValue('C' . $row, $stat->total_revenue);
                $sheet->setCellValue('D' . $row, $stat->avg_order_value);

                // Format numbers
                $sheet
                    ->getStyle('C' . $row . ':D' . $row)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                $row++;
            }

            // Member growth section
            $row += 2;
            $sheet->setCellValue('A' . $row, 'MEMBER GROWTH');
            $sheet
                ->getStyle('A' . $row)
                ->getFont()
                ->setBold(true);

            $row++;
            $sheet->setCellValue('A' . $row, 'Month');
            $sheet->setCellValue('B' . $row, 'New Members');
            $sheet
                ->getStyle('A' . $row . ':B' . $row)
                ->getFont()
                ->setBold(true);

            $row++;
            foreach ($memberGrowth as $growth) {
                $sheet->setCellValue('A' . $row, $growth->month);
                $sheet->setCellValue('B' . $row, $growth->new_members);
                $row++;
            }

            // Top customers section
            $row += 2;
            $sheet->setCellValue('A' . $row, 'TOP CUSTOMERS');
            $sheet
                ->getStyle('A' . $row)
                ->getFont()
                ->setBold(true);

            $row++;
            $sheet->setCellValue('A' . $row, 'Rank');
            $sheet->setCellValue('B' . $row, 'Name');
            $sheet->setCellValue('C' . $row, 'Phone');
            $sheet->setCellValue('D' . $row, 'Transactions');
            $sheet->setCellValue('E' . $row, 'Total Spent');
            $sheet->setCellValue('F' . $row, 'Avg Order Value');
            $sheet
                ->getStyle('A' . $row . ':F' . $row)
                ->getFont()
                ->setBold(true);

            $row++;
            $rank = 1;
            foreach ($topCustomers as $customer) {
                $sheet->setCellValue('A' . $row, $rank);
                $sheet->setCellValue('B' . $row, $customer->member_name);
                $sheet->setCellValue('C' . $row, $customer->member_phone);
                $sheet->setCellValue('D' . $row, $customer->total_transactions);
                $sheet->setCellValue('E' . $row, $customer->total_spent);
                $sheet->setCellValue('F' . $row, $customer->avg_order_value);

                // Format numbers
                $sheet
                    ->getStyle('E' . $row . ':F' . $row)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                $rank++;
                $row++;
            }

            // Auto-size columns
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Set filename and headers
            $filename = 'customer_insights_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.xlsx';

            // Create the writer and output the file
            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit();
        }
    }

    /**
     * Generate Inventory Report
     */
    public function inventoryReport(Request $request)
    {
        // Validate request
        $request->validate([
            'format' => 'required|in:pdf,excel',
        ]);

        // Get current inventory status
        $rawMaterials = RawMaterial::orderBy('name')->get();

        // Get recent material orders (last 30 days)
        $materialOrders = MaterialOrder::with(['franchise', 'items.rawMaterial'])
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate total spending on material orders for each outlet
        $outletSpending = MaterialOrder::select('outlets.id as outlet_id', 'outlets.name as outlet_name', DB::raw('SUM(material_orders.total_amount) as total_spending'), DB::raw('COUNT(material_orders.id) as order_count'))
            ->join('outlets', 'outlets.id', '=', 'material_orders.franchise_id')
            ->where('material_orders.created_at', '>=', now()->subDays(30))
            ->groupBy('outlets.id', 'outlets.name')
            ->orderBy('total_spending', 'desc')
            ->get();

        // Format the report title
        $title = 'Inventory & Raw Materials Report';
        $subtitle = 'Generated on: ' . now()->format('d M Y');

        // Generate the report based on requested format
        if ($request->format === 'pdf') {
            $pdf = PDF::loadView('pages.reports.inventory_pdf', compact('title', 'subtitle', 'rawMaterials', 'materialOrders', 'outletSpending'));

            return $pdf->download('inventory_report_' . now()->format('Y-m-d') . '.pdf');
        } else {
            // Create an Excel spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set spreadsheet metadata
            $spreadsheet->getProperties()->setCreator('Seblak Sulthane')->setLastModifiedBy('Seblak Sulthane')->setTitle($title)->setSubject($subtitle)->setDescription('Inventory Report for Seblak Sulthane');

            // Format the header
            $sheet->setCellValue('A1', $title);
            $sheet->setCellValue('A2', $subtitle);
            $sheet->mergeCells('A1:F1');
            $sheet->mergeCells('A2:F2');

            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A2')->getFont()->setSize(12);

            // Current inventory section
            $sheet->setCellValue('A4', 'CURRENT INVENTORY STATUS');
            $sheet->getStyle('A4')->getFont()->setBold(true);

            // Headers
            $sheet->setCellValue('A5', 'Material');
            $sheet->setCellValue('B5', 'Unit');
            $sheet->setCellValue('C5', 'Current Stock');
            $sheet->setCellValue('D5', 'Price per Unit');
            $sheet->setCellValue('E5', 'Total Value');
            $sheet->setCellValue('F5', 'Status');
            $sheet->getStyle('A5:F5')->getFont()->setBold(true);

            // Data rows
            $row = 6;
            $totalInventoryValue = 0;

            foreach ($rawMaterials as $material) {
                $itemValue = $material->stock * $material->price;
                $totalInventoryValue += $itemValue;

                $sheet->setCellValue('A' . $row, $material->name);
                $sheet->setCellValue('B' . $row, $material->unit);
                $sheet->setCellValue('C' . $row, $material->stock);
                $sheet->setCellValue('D' . $row, $material->price);
                $sheet->setCellValue('E' . $row, $itemValue);
                $sheet->setCellValue('F' . $row, $material->is_active ? 'Active' : 'Inactive');

                // Format numbers
                $sheet
                    ->getStyle('D' . $row . ':E' . $row)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                // Format low stock items
                if ($material->stock < 10) {
                    $sheet
                        ->getStyle('C' . $row)
                        ->getFont()
                        ->getColor()
                        ->setARGB('FF0000'); // Red for low stock
                }

                $row++;
            }

            // Summary row
            $row++;
            $sheet->setCellValue('A' . $row, 'Total Inventory Value:');
            $sheet->setCellValue('E' . $row, $totalInventoryValue);
            $sheet
                ->getStyle('A' . $row)
                ->getFont()
                ->setBold(true);
            $sheet
                ->getStyle('E' . $row)
                ->getNumberFormat()
                ->setFormatCode('#,##0');
            $sheet
                ->getStyle('E' . $row)
                ->getFont()
                ->setBold(true);

            // Create a sheet for outlet spending
            $spendingSheet = $spreadsheet->createSheet();
            $spendingSheet->setTitle('Outlet Spending');

            // Headers
            $spendingSheet->setCellValue('A1', 'OUTLET MATERIAL SPENDING SUMMARY');
            $spendingSheet->mergeCells('A1:C1');
            $spendingSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            $spendingSheet->setCellValue('A3', 'Outlet');
            $spendingSheet->setCellValue('B3', 'Order Count');
            $spendingSheet->setCellValue('C3', 'Total Spending');
            $spendingSheet->getStyle('A3:C3')->getFont()->setBold(true);

            // Data rows
            $spendingRow = 4;
            foreach ($outletSpending as $outlet) {
                $spendingSheet->setCellValue('A' . $spendingRow, $outlet->outlet_name);
                $spendingSheet->setCellValue('B' . $spendingRow, $outlet->order_count);
                $spendingSheet->setCellValue('C' . $spendingRow, $outlet->total_spending);

                // Format numbers
                $spendingSheet
                    ->getStyle('C' . $spendingRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                $spendingRow++;
            }

            // Auto-size columns
            foreach (range('A', 'C') as $col) {
                $spendingSheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Create a sheet for recent orders
            $orderSheet = $spreadsheet->createSheet();
            $orderSheet->setTitle('Recent Orders');

            // Headers
            $orderSheet->setCellValue('A1', 'RECENT MATERIAL ORDERS (LAST 30 DAYS)');
            $orderSheet->mergeCells('A1:F1');
            $orderSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            $orderSheet->setCellValue('A3', 'Order ID');
            $orderSheet->setCellValue('B3', 'Date');
            $orderSheet->setCellValue('C3', 'Outlet');
            $orderSheet->setCellValue('D3', 'Status');
            $orderSheet->setCellValue('E3', 'Items');
            $orderSheet->setCellValue('F3', 'Total Amount');
            $orderSheet->getStyle('A3:F3')->getFont()->setBold(true);

            // Data rows
            $orderRow = 4;
            foreach ($materialOrders as $order) {
                $itemsList = $order->items
                    ->map(function ($item) {
                        return $item->quantity . ' ' . $item->rawMaterial->unit . ' ' . $item->rawMaterial->name;
                    })
                    ->join(', ');

                $orderSheet->setCellValue('A' . $orderRow, '#' . $order->id);
                $orderSheet->setCellValue('B' . $orderRow, $order->created_at->format('d M Y'));
                $orderSheet->setCellValue('C' . $orderRow, $order->franchise->name);
                $orderSheet->setCellValue('D' . $orderRow, ucfirst($order->status));
                $orderSheet->setCellValue('E' . $orderRow, $itemsList);
                $orderSheet->setCellValue('F' . $orderRow, $order->total_amount);

                // Format numbers
                $orderSheet
                    ->getStyle('F' . $orderRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                $orderRow++;
            }

            // Auto-size columns on all sheets
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
                $orderSheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Set first sheet as active
            $spreadsheet->setActiveSheetIndex(0);

            // Set filename and headers
            $filename = 'inventory_report_' . now()->format('Y-m-d') . '.xlsx';

            // Create the writer and output the file
            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit();
        }
    }
}
