<?php

namespace App\Http\Controllers;

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
// use Barryvdh\DomPDF\Facade as PDF;

class ReportController extends Controller
{
    /**
     * Show the reports dashboard
     */
    public function index()
    {
        // Ensure only owner can access
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('home')->with('error', 'You do not have permission to access reports');
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

        // Base query
        $query = Order::whereBetween('created_at', [$startDate, $endDate]);

        // Filter by outlet if provided
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        // Get the data
        $totalRevenue = $query->sum('total');
        $totalOrders = $query->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Get daily sales data
        $dailySales = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total) as total_sales'),
            DB::raw('COUNT(*) as order_count')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($outletId, function ($query) use ($outletId) {
                return $query->where('outlet_id', $outletId);
            })
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Payment method breakdown
        $paymentMethods = Order::select(
            'payment_method',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total) as total')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($outletId, function ($query) use ($outletId) {
                return $query->where('outlet_id', $outletId);
            })
            ->groupBy('payment_method')
            ->get();

        // Tax and discount totals
        $taxTotal = $query->sum('tax');
        $discountTotal = $query->sum('discount_amount');

        // Format the report title
        $title = 'Sales Summary Report';
        $subtitle = 'Period: ' . $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y');

        if ($outletId) {
            $outlet = Outlet::find($outletId);
            $subtitle .= ' | Outlet: ' . $outlet->name;
        } else {
            $subtitle .= ' | All Outlets';
        }

        // Generate the report based on requested format
        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('pages.reports.sales_summary_pdf', compact(
                'title',
                'subtitle',
                'totalRevenue',
                'totalOrders',
                'avgOrderValue',
                'dailySales',
                'paymentMethods',
                'taxTotal',
                'discountTotal',
                'startDate',
                'endDate'
            ));

            return $pdf->download('sales_summary_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.pdf');
        } else {
            // Create an Excel spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set spreadsheet metadata
            $spreadsheet->getProperties()
                ->setCreator('Seblak Sulthane')
                ->setLastModifiedBy('Seblak Sulthane')
                ->setTitle($title)
                ->setSubject($subtitle)
                ->setDescription('Sales Summary Report for Seblak Sulthane');

            // Format the header
            $sheet->setCellValue('A1', $title);
            $sheet->setCellValue('A2', $subtitle);
            $sheet->mergeCells('A1:F1');
            $sheet->mergeCells('A2:F2');

            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A2')->getFont()->setSize(12);

            // Summary section
            $sheet->setCellValue('A4', 'SUMMARY');
            $sheet->getStyle('A4')->getFont()->setBold(true);

            $sheet->setCellValue('A5', 'Total Revenue:');
            $sheet->setCellValue('B5', $totalRevenue);
            $sheet->getStyle('B5')->getNumberFormat()->setFormatCode('#,##0');

            $sheet->setCellValue('A6', 'Total Orders:');
            $sheet->setCellValue('B6', $totalOrders);

            $sheet->setCellValue('A7', 'Average Order Value:');
            $sheet->setCellValue('B7', $avgOrderValue);
            $sheet->getStyle('B7')->getNumberFormat()->setFormatCode('#,##0');

            $sheet->setCellValue('A8', 'Total Tax:');
            $sheet->setCellValue('B8', $taxTotal);
            $sheet->getStyle('B8')->getNumberFormat()->setFormatCode('#,##0');

            $sheet->setCellValue('A9', 'Total Discounts:');
            $sheet->setCellValue('B9', $discountTotal);
            $sheet->getStyle('B9')->getNumberFormat()->setFormatCode('#,##0');

            // Daily sales section
            $sheet->setCellValue('A11', 'DAILY SALES');
            $sheet->getStyle('A11')->getFont()->setBold(true);

            $sheet->setCellValue('A12', 'Date');
            $sheet->setCellValue('B12', 'Total Sales');
            $sheet->setCellValue('C12', 'Order Count');
            $sheet->getStyle('A12:C12')->getFont()->setBold(true);

            $row = 13;
            foreach ($dailySales as $sale) {
                $sheet->setCellValue('A' . $row, $sale->date);
                $sheet->setCellValue('B' . $row, $sale->total_sales);
                $sheet->setCellValue('C' . $row, $sale->order_count);
                $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0');
                $row++;
            }

            // Payment method section
            $row += 2;
            $paymentRow = $row;
            $sheet->setCellValue('A' . $paymentRow, 'PAYMENT METHODS');
            $sheet->getStyle('A' . $paymentRow)->getFont()->setBold(true);

            $paymentRow++;
            $sheet->setCellValue('A' . $paymentRow, 'Method');
            $sheet->setCellValue('B' . $paymentRow, 'Count');
            $sheet->setCellValue('C' . $paymentRow, 'Total');
            $sheet->getStyle('A' . $paymentRow . ':C' . $paymentRow)->getFont()->setBold(true);

            $paymentRow++;
            foreach ($paymentMethods as $method) {
                $sheet->setCellValue('A' . $paymentRow, strtoupper($method->payment_method));
                $sheet->setCellValue('B' . $paymentRow, $method->count);
                $sheet->setCellValue('C' . $paymentRow, $method->total);
                $sheet->getStyle('C' . $paymentRow)->getNumberFormat()->setFormatCode('#,##0');
                $paymentRow++;
            }

            // Auto-size columns
            foreach (range('A', 'C') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Set filename and headers
            $filename = 'sales_summary_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.xlsx';

            // Create the writer and output the file
            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;
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
        $outletPerformance = Order::select(
            'outlets.id as outlet_id',
            'outlets.name as outlet_name',
            DB::raw('COUNT(orders.id) as total_orders'),
            DB::raw('SUM(orders.total) as total_revenue'),
            DB::raw('SUM(orders.tax) as total_tax'),
            DB::raw('SUM(orders.discount_amount) as total_discount'),
            DB::raw('COUNT(DISTINCT orders.member_id) as total_customers'),
            DB::raw('AVG(orders.total) as avg_order_value')
        )
            ->join('outlets', 'outlets.id', '=', 'orders.outlet_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('outlets.id', 'outlets.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        // Get daily trends per outlet
        $dailyTrends = Order::select(
            'outlets.id as outlet_id',
            'outlets.name as outlet_name',
            DB::raw('DATE(orders.created_at) as date'),
            DB::raw('SUM(orders.total) as daily_revenue'),
            DB::raw('COUNT(orders.id) as daily_orders')
        )
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
            $pdf = PDF::loadView('pages.reports.outlet_performance_pdf', compact(
                'title',
                'subtitle',
                'outletPerformance',
                'dailyTrends',
                'startDate',
                'endDate'
            ));

            return $pdf->download('outlet_performance_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.pdf');
        } else {
            // Create an Excel spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set spreadsheet metadata
            $spreadsheet->getProperties()
                ->setCreator('Seblak Sulthane')
                ->setLastModifiedBy('Seblak Sulthane')
                ->setTitle($title)
                ->setSubject($subtitle)
                ->setDescription('Outlet Performance Report for Seblak Sulthane');

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
                $sheet->getStyle('C' . $row . ':E' . $row)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('#,##0');

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

                    $trendSheet->getStyle('B' . $trendRow)->getNumberFormat()->setFormatCode('#,##0');

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
            exit;
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

        // Get product performance data
        $productPerformance = OrderItem::select(
            'products.id as product_id',
            'products.name as product_name',
            'categories.name as category_name',
            DB::raw('SUM(order_items.quantity) as total_quantity'),
            DB::raw('SUM(order_items.quantity * order_items.price_per_unit) as total_revenue'),
            DB::raw('COUNT(DISTINCT orders.id) as order_count')
        )
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

        // Get category breakdown
        $categoryBreakdown = OrderItem::select(
            'categories.name as category_name',
            DB::raw('SUM(order_items.quantity) as total_quantity'),
            DB::raw('SUM(order_items.quantity * order_items.price_per_unit) as total_revenue'),
            DB::raw('COUNT(DISTINCT products.id) as product_count')
        )
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
            $pdf = PDF::loadView('pages.reports.product_performance_pdf', compact(
                'title',
                'subtitle',
                'productPerformance',
                'categoryBreakdown',
                'startDate',
                'endDate'
            ));

            return $pdf->download('product_performance_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.pdf');
        } else {
            // Create an Excel spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set spreadsheet metadata
            $spreadsheet->getProperties()
                ->setCreator('Seblak Sulthane')
                ->setLastModifiedBy('Seblak Sulthane')
                ->setTitle($title)
                ->setSubject($subtitle)
                ->setDescription('Product Performance Report for Seblak Sulthane');

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
                $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0');

                $row++;
            }

            // Product performance section
            $row += 2;
            $productRow = $row;
            $sheet->setCellValue('A' . $productRow, 'PRODUCT PERFORMANCE');
            $sheet->getStyle('A' . $productRow)->getFont()->setBold(true);

            $productRow++;
            $sheet->setCellValue('A' . $productRow, 'Product');
            $sheet->setCellValue('B' . $productRow, 'Category');
            $sheet->setCellValue('C' . $productRow, 'Quantity Sold');
            $sheet->setCellValue('D' . $productRow, 'Revenue');
            $sheet->setCellValue('E' . $productRow, 'Order Count');
            $sheet->getStyle('A' . $productRow . ':E' . $productRow)->getFont()->setBold(true);

            $productRow++;
            foreach ($productPerformance as $product) {
                $sheet->setCellValue('A' . $productRow, $product->product_name);
                $sheet->setCellValue('B' . $productRow, $product->category_name);
                $sheet->setCellValue('C' . $productRow, $product->total_quantity);
                $sheet->setCellValue('D' . $productRow, $product->total_revenue);
                $sheet->setCellValue('E' . $productRow, $product->order_count);

                // Format numbers
                $sheet->getStyle('D' . $productRow)->getNumberFormat()->setFormatCode('#,##0');

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
            exit;
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
        $topCustomers = Order::select(
            'members.id as member_id',
            'members.name as member_name',
            'members.phone as member_phone',
            DB::raw('COUNT(orders.id) as total_transactions'),
            DB::raw('SUM(orders.total) as total_spent'),
            DB::raw('AVG(orders.total) as avg_order_value')
        )
            ->join('members', 'members.id', '=', 'orders.member_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('members.id', 'members.name', 'members.phone')
            ->orderBy('total_spent', 'desc')
            ->limit(50)
            ->get();

        // Get member vs non-member stats
        $memberStats = Order::select(
            DB::raw('CASE WHEN member_id IS NULL THEN "Non-Member" ELSE "Member" END as customer_type'),
            DB::raw('COUNT(*) as order_count'),
            DB::raw('SUM(total) as total_revenue'),
            DB::raw('AVG(total) as avg_order_value')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('CASE WHEN member_id IS NULL THEN "Non-Member" ELSE "Member" END'))
            ->get();

        // Get member growth
        $memberGrowth = Member::select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('COUNT(*) as new_members')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
            ->orderBy('month')
            ->get();

        // Format the report title
        $title = 'Customer Insights Report';
        $subtitle = 'Period: ' . $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y');

        // Generate the report based on requested format
        if ($request->format === 'pdf') {
            $pdf = PDF::loadView('pages.reports.customer_insights_pdf', compact(
                'title',
                'subtitle',
                'topCustomers',
                'memberStats',
                'memberGrowth',
                'startDate',
                'endDate'
            ));

            return $pdf->download('customer_insights_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.pdf');
        } else {
            // Create an Excel spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set spreadsheet metadata
            $spreadsheet->getProperties()
                ->setCreator('Seblak Sulthane')
                ->setLastModifiedBy('Seblak Sulthane')
                ->setTitle($title)
                ->setSubject($subtitle)
                ->setDescription('Customer Insights Report for Seblak Sulthane');

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
                $sheet->getStyle('C' . $row . ':D' . $row)->getNumberFormat()->setFormatCode('#,##0');

                $row++;
            }

            // Member growth section
            $row += 2;
            $sheet->setCellValue('A' . $row, 'MEMBER GROWTH');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);

            $row++;
            $sheet->setCellValue('A' . $row, 'Month');
            $sheet->setCellValue('B' . $row, 'New Members');
            $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);

            $row++;
            foreach ($memberGrowth as $growth) {
                $sheet->setCellValue('A' . $row, $growth->month);
                $sheet->setCellValue('B' . $row, $growth->new_members);
                $row++;
            }

            // Top customers section
            $row += 2;
            $sheet->setCellValue('A' . $row, 'TOP CUSTOMERS');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);

            $row++;
            $sheet->setCellValue('A' . $row, 'Rank');
            $sheet->setCellValue('B' . $row, 'Name');
            $sheet->setCellValue('C' . $row, 'Phone');
            $sheet->setCellValue('D' . $row, 'Transactions');
            $sheet->setCellValue('E' . $row, 'Total Spent');
            $sheet->setCellValue('F' . $row, 'Avg Order Value');
            $sheet->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true);

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
                $sheet->getStyle('E' . $row . ':F' . $row)->getNumberFormat()->setFormatCode('#,##0');

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
            exit;
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

        // Format the report title
        $title = 'Inventory & Raw Materials Report';
        $subtitle = 'Generated on: ' . now()->format('d M Y');

        // Generate the report based on requested format
        if ($request->format === 'pdf') {
            $pdf = PDF::loadView('pages.reports.inventory_pdf', compact(
                'title',
                'subtitle',
                'rawMaterials',
                'materialOrders'
            ));

            return $pdf->download('inventory_report_' . now()->format('Y-m-d') . '.pdf');
        } else {
            // Create an Excel spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set spreadsheet metadata
            $spreadsheet->getProperties()
                ->setCreator('Seblak Sulthane')
                ->setLastModifiedBy('Seblak Sulthane')
                ->setTitle($title)
                ->setSubject($subtitle)
                ->setDescription('Inventory Report for Seblak Sulthane');

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
                $sheet->getStyle('D' . $row . ':E' . $row)->getNumberFormat()->setFormatCode('#,##0');

                // Format low stock items
                if ($material->stock < 10) {
                    $sheet->getStyle('C' . $row)->getFont()->getColor()->setARGB('FF0000'); // Red for low stock
                }

                $row++;
            }

            // Summary row
            $row++;
            $sheet->setCellValue('A' . $row, 'Total Inventory Value:');
            $sheet->setCellValue('E' . $row, $totalInventoryValue);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('E' . $row)->getFont()->setBold(true);

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
                $itemsList = $order->items->map(function ($item) {
                    return $item->quantity . ' ' . $item->rawMaterial->unit . ' ' . $item->rawMaterial->name;
                })->join(", ");

                $orderSheet->setCellValue('A' . $orderRow, '#' . $order->id);
                $orderSheet->setCellValue('B' . $orderRow, $order->created_at->format('d M Y'));
                $orderSheet->setCellValue('C' . $orderRow, $order->franchise->name);
                $orderSheet->setCellValue('D' . $orderRow, ucfirst($order->status));
                $orderSheet->setCellValue('E' . $orderRow, $itemsList);
                $orderSheet->setCellValue('F' . $orderRow, $order->total_amount);

                // Format numbers
                $orderSheet->getStyle('F' . $orderRow)->getNumberFormat()->setFormatCode('#,##0');

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
            exit;
        }
    }
}
