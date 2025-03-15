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
     * Generate Sales Summary Report
     */
    public function salesSummary(Request $request)
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
        $periodType = $request->input('period_type', 'daily'); // Default ke daily

        // Base query untuk orders
        $query = Order::whereBetween('created_at', [$startDate, $endDate]);
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        // Data penjualan
        $totalRevenue = $query->sum('total'); // Hasil penjualan bersih (sudah termasuk pajak dan dikurangi diskon)
        $totalOrders = $query->count(); // Jumlah transaksi
        $totalSubTotal = $query->sum('sub_total'); // Penjualan kotor sebelum pajak dan diskon
        $totalTax = $query->sum('tax'); // Total pajak
        $totalDiscountAmount = $query->sum('discount_amount'); // Total diskon nominal
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0; // Rata-rata nilai transaksi

        // Perbaikan perhitungan QRIS fee dengan CAST eksplisit
        $totalQrisFee = (clone $query)
            ->where('payment_method', 'qris')
            ->selectRaw('COALESCE(SUM(CAST(qris_fee AS DECIMAL(10,2))), 0) as total_fee')
            ->first()->total_fee;

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
            ->select(
                'payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total) as total_amount'),
                DB::raw('CASE WHEN payment_method = "qris" THEN COALESCE(SUM(CAST(qris_fee AS DECIMAL(10,2))), 0) ELSE 0 END as qris_fees')
            )
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

        // Generate rentang tanggal
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
            $dailyQrisFee = (clone $dailyOrdersQuery)
                ->where('payment_method', 'qris')
                ->selectRaw('COALESCE(SUM(CAST(qris_fee AS DECIMAL(10,2))), 0) as daily_fee')
                ->first()->daily_fee;

            // Data harian metode pembayaran
            $dailyPaymentMethods = (clone $dailyOrdersQuery)
                ->select('payment_method', DB::raw('SUM(total) as total_amount'))
                ->groupBy('payment_method')
                ->get();

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
                'day_name' => $currentDate->translatedFormat('l'),
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

        // Format judul laporan
        $title = 'Laporan Ringkasan Penjualan';
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
            $pdf = PDF::loadView('pages.reports.sales_summary_pdf', compact(
                'title',
                'subtitle',
                'totalRevenue',
                'totalOrders',
                'totalSubTotal',
                'totalTax',
                'totalDiscountAmount',
                'beverageSales',
                'qrisSales',
                'cashSales',
                'totalOpeningBalance',
                'totalExpenses',
                'totalQrisFee',
                'closingBalance',
                'dailyData',
                'startDate',
                'endDate',
                'periodType'
            ));

            // Set orientation to landscape
            $pdf->setPaper('a4', 'landscape');

            return $pdf->download('laporan_penjualan_' . $startDate->format('Y-m-d') . '_sampai_' . $endDate->format('Y-m-d') . '.pdf');
        } else {
            // Buat spreadsheet Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Ringkasan Penjualan');

            // Set metadata spreadsheet
            $spreadsheet->getProperties()->setCreator('Seblak Sulthane')->setLastModifiedBy('Seblak Sulthane')->setTitle($title)->setSubject($subtitle)->setDescription('Laporan Ringkasan Penjualan Seblak Sulthane');

            // Format header
            $sheet->setCellValue('A1', $title);
            $sheet->setCellValue('A2', $subtitle);
            $sheet->mergeCells('A1:M1');
            $sheet->mergeCells('A2:M2');

            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A2')->getFont()->setSize(12);

            // Section Ringkasan
            $sheet->setCellValue('A4', 'RINGKASAN PERIODE');
            $sheet->getStyle('A4')->getFont()->setBold(true);
            $sheet->getStyle('A4:M4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');

            // Data ringkasan periode
            $sheet->setCellValue('A5', 'Total Hasil Penjualan Bersih:');
            $sheet->setCellValue('B5', $totalRevenue);
            $sheet->getStyle('B5')->getNumberFormat()->setFormatCode('#,##0');

            $sheet->setCellValue('A6', 'Total Penjualan Kotor:');
            $sheet->setCellValue('B6', $totalSubTotal);
            $sheet->getStyle('B6')->getNumberFormat()->setFormatCode('#,##0');

            $sheet->setCellValue('A7', 'Total Discount:');
            $sheet->setCellValue('B7', $totalDiscountAmount);
            $sheet->getStyle('B7')->getNumberFormat()->setFormatCode('#,##0');

            $sheet->setCellValue('A8', 'Total Pajak:');
            $sheet->setCellValue('B8', $totalTax);
            $sheet->getStyle('B8')->getNumberFormat()->setFormatCode('#,##0');

            $sheet->setCellValue('A9', 'Total Penjualan Beverage:');
            $sheet->setCellValue('B9', $beverageSales);
            $sheet->getStyle('B9')->getNumberFormat()->setFormatCode('#,##0');

            $sheet->setCellValue('A10', 'Total Penjualan QRIS:');
            $sheet->setCellValue('B10', $qrisSales);
            $sheet->getStyle('B10')->getNumberFormat()->setFormatCode('#,##0');

            $sheet->setCellValue('A11', 'Total Biaya Layanan QRIS (0.3%):');
            $sheet->setCellValue('B11', $totalQrisFee);
            $sheet->getStyle('B11')->getNumberFormat()->setFormatCode('#,##0');

            $sheet->setCellValue('A12', 'Total Penjualan CASH:');
            $sheet->setCellValue('B12', $cashSales);
            $sheet->getStyle('B12')->getNumberFormat()->setFormatCode('#,##0');

            $sheet->setCellValue('A13', 'Total Pengeluaran:');
            $sheet->setCellValue('B13', $totalExpenses);
            $sheet->getStyle('B13')->getNumberFormat()->setFormatCode('#,##0');

            $sheet->setCellValue('A14', 'Total Saldo Awal:');
            $sheet->setCellValue('B14', $totalOpeningBalance);
            $sheet->getStyle('B14')->getNumberFormat()->setFormatCode('#,##0');

            $sheet->setCellValue('A15', 'Total Saldo Akhir:');
            $sheet->setCellValue('B15', $closingBalance);
            $sheet->getStyle('B15')->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('B15')->getFont()->setBold(true);

            $sheet->setCellValue('A16', 'Total Jumlah Orderan:');
            $sheet->setCellValue('B16', $totalOrders);

            // Section data harian
            $row = 18;
            $sheet->setCellValue('A' . $row, 'BREAKDOWN HARIAN');
            $sheet
                ->getStyle('A' . $row)
                ->getFont()
                ->setBold(true);
            $sheet
                ->getStyle('A' . $row . ':M' . $row)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setRGB('DDEBF7');

            // Header tabel data harian
            $row++;
            $sheet->setCellValue('A' . $row, 'Tanggal');
            $sheet->setCellValue('B' . $row, 'Hari');
            $sheet->setCellValue('C' . $row, 'Total Penjualan Bersih');
            $sheet->setCellValue('D' . $row, 'Total Penjualan Kotor');
            $sheet->setCellValue('E' . $row, 'Total Discount');
            $sheet->setCellValue('F' . $row, 'Total Pajak');
            $sheet->setCellValue('G' . $row, 'Total Beverage');
            $sheet->setCellValue('H' . $row, 'Total QRIS');
            $sheet->setCellValue('I' . $row, 'Biaya QRIS (0.3%)');
            $sheet->setCellValue('J' . $row, 'Total CASH');
            $sheet->setCellValue('K' . $row, 'Total Pengeluaran');
            $sheet->setCellValue('L' . $row, 'Saldo Awal');
            $sheet->setCellValue('M' . $row, 'Saldo Akhir');
            $sheet
                ->getStyle('A' . $row . ':M' . $row)
                ->getFont()
                ->setBold(true);

            // Data tabel harian
            $row++;
            foreach ($dailyData as $day) {
                $sheet->setCellValue('A' . $row, $day['date']);
                $sheet->setCellValue('B' . $row, $day['day_name']);
                $sheet->setCellValue('C' . $row, $day['revenue']);
                $sheet->setCellValue('D' . $row, $day['sub_total']);
                $sheet->setCellValue('E' . $row, $day['discount_amount']);
                $sheet->setCellValue('F' . $row, $day['tax']);
                $sheet->setCellValue('G' . $row, $day['beverage_sales']);
                $sheet->setCellValue('H' . $row, $day['qris_sales']);
                $sheet->setCellValue('I' . $row, $day['qris_fee']);
                $sheet->setCellValue('J' . $row, $day['cash_sales']);
                $sheet->setCellValue('K' . $row, $day['expenses']);
                $sheet->setCellValue('L' . $row, $day['opening_balance']);
                $sheet->setCellValue('M' . $row, $day['closing_balance']);

                // Format nomimal
                $sheet
                    ->getStyle('C' . $row . ':M' . $row)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                // Alternating row colors
                if ($row % 2 === 0) {
                    $sheet
                        ->getStyle('A' . $row . ':M' . $row)
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('F2F2F2');
                }

                $row++;
            }

            // Autosize columns
            foreach (range('A', 'M') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Freeze the header row
            $sheet->freezePane('A20');

            // Set the auto-filter
            $sheet->setAutoFilter('A19:M' . ($row - 1));

            // Set filename
            $filename = 'laporan_penjualan_' . $startDate->format('Y-m-d') . '_sampai_' . $endDate->format('Y-m-d') . '.xlsx';

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
