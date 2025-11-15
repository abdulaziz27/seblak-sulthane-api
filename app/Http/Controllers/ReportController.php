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
use App\Models\StockAdjustment;
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
        // if (Auth::user()->role !== 'owner') {
        //     return redirect()->route('home')->with('error', 'Anda tidak memiliki akses untuk melihat laporan');
        // }

        $outlets = Outlet::all();

        return view('pages.reports.index', compact('outlets'));
    }

    /**
     * Generate Supplier Purchase Report
     */
    public function supplierPurchases(Request $request)
    {
        // Validate request
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'required|in:pdf,excel',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        // Check permissions
        if (Auth::user()->role !== 'owner' && Auth::user()->role !== 'admin') {
            return redirect()->back()->with('error', 'Anda tidak memiliki akses untuk laporan ini');
        }

        // Ambil data penyesuaian stok dengan tipe 'purchase'
        $purchases = StockAdjustment::with(['rawMaterial', 'user'])
            ->whereIn('adjustment_type', ['purchase', 'usage', 'damage', 'other'])
            ->where('quantity', '>', 0)
            ->whereBetween('adjustment_date', [$startDate, $endDate])
            ->orderBy('adjustment_date', 'desc')
            ->get();

        // Grouped by date
        $dailyData = [];
        $dateGroups = $purchases->groupBy(function ($item) {
            return $item->adjustment_date->format('Y-m-d');
        });

        // Generate all dates in the range even if no purchases
        $currentDate = clone $startDate;
        $grandTotalIn = 0;
        $grandTotalOut = 0;
        $grandTotalInQty = 0;
        $grandTotalOutQty = 0;
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $adjustments = $dateGroups[$dateStr] ?? collect();

            $orderCount = $adjustments->count();
            $inQty = $adjustments->where('adjustment_type', 'purchase')->sum('quantity');
            $outQty = $adjustments->where('adjustment_type', '!=', 'purchase')->sum('quantity');
            $inAmount = $adjustments->where('adjustment_type', 'purchase')->sum(function ($adj) {
                return $adj->quantity * $adj->purchase_price;
            });
            $outAmount = $adjustments->where('adjustment_type', '!=', 'purchase')->sum(function ($adj) {
                return $adj->quantity * ($adj->purchase_price ?? 0);
            });
            $saldoQty = $inQty - $outQty;
            $saldoAmount = $inAmount - $outAmount;

            $grandTotalIn += $inAmount;
            $grandTotalOut += $outAmount;
            $grandTotalInQty += $inQty;
            $grandTotalOutQty += $outQty;

            // Create detailed purchases array
            $detailedPurchases = [];
            foreach ($adjustments as $index => $adjustment) {
                $baseData = [
                    'no' => $index + 1,
                    'name' => $adjustment->rawMaterial->name,
                    'unit' => $adjustment->rawMaterial->unit,
                    'quantity' => $adjustment->adjustment_type === 'purchase' ? $adjustment->quantity : -$adjustment->quantity,
                    'adjustment_type' => $adjustment->adjustment_type,
                    'notes' => $adjustment->notes
                ];
                if ($adjustment->adjustment_type === 'purchase') {
                    $specificData = [
                        'purchase_price' => $adjustment->purchase_price,
                        'selling_price' => $adjustment->rawMaterial->price,
                        'subtotal' => $adjustment->quantity * $adjustment->purchase_price,
                        'is_purchase' => true
                    ];
                } else {
                    $specificData = [
                        'purchase_price' => $adjustment->purchase_price,
                        'selling_price' => null,
                        'subtotal' => - ($adjustment->quantity * ($adjustment->purchase_price ?? 0)),
                        'is_purchase' => false
                    ];
                }
                $detailedPurchases[] = array_merge($baseData, $specificData);
            }

            $dailyData[] = [
                'date' => $dateStr,
                'day_name' => $currentDate->locale('id')->isoFormat('dddd'),
                'order_count' => $orderCount,
                'in_qty' => $inQty,
                'out_qty' => $outQty,
                'saldo_qty' => $saldoQty,
                'in_amount' => $inAmount,
                'out_amount' => $outAmount,
                'saldo_amount' => $saldoAmount,
                'detailed_purchases' => $detailedPurchases
            ];

            $currentDate->addDay();
        }

        // Sort by date (newest first)
        usort($dailyData, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        // Summary data
        $summaryData = [
            'total_purchase_amount' => $grandTotalIn,
            'total_purchase_count' => $purchases->where('adjustment_type', 'purchase')->count(),
            'total_purchase_items' => $grandTotalInQty,
            'total_reduction_count' => $purchases->where('adjustment_type', '!=', 'purchase')->count(),
            'total_reduction_items' => $grandTotalOutQty,
            'total_reduction_amount' => $grandTotalOut,
            'saldo_qty' => $grandTotalInQty - $grandTotalOutQty,
            'saldo_amount' => $grandTotalIn - $grandTotalOut,
        ];

        // Judul laporan
        $title = 'Laporan Pembelian dari Supplier';
        $subtitle = 'Periode: ' . $startDate->translatedFormat('d M Y') . ' - ' . $endDate->translatedFormat('d M Y');

        // Generate laporan berdasarkan format yang diminta
        if ($request->format === 'pdf') {
            $pdf = PDF::loadView('pages.reports.supplier_purchases_pdf', compact(
                'title',
                'subtitle',
                'summaryData',
                'dailyData',
                'startDate',
                'endDate'
            ));

            // Set orientation to landscape
            $pdf->setPaper('a4', 'landscape');

            return $pdf->download('laporan_pembelian_supplier_' . $startDate->format('Y-m-d') . '_sampai_' . $endDate->format('Y-m-d') . '.pdf');
        } else {
            // Excel format implementation
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Pembelian Supplier');

            // Set metadata
            $spreadsheet->getProperties()
                ->setCreator('Seblak Sulthane')
                ->setLastModifiedBy('Seblak Sulthane')
                ->setTitle($title)
                ->setSubject($subtitle)
                ->setDescription('Laporan Pembelian Bahan Baku dari Supplier');

            // Define styles
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
                    ],
                ],
            ];

            $subHeaderStyle = [
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9E1F2'],
                ],
            ];

            // Add header
            $sheet->setCellValue('A1', 'SEBLAK SULTHANE');
            $sheet->setCellValue('A2', $title);
            $sheet->setCellValue('A3', $subtitle);
            $sheet->mergeCells('A2:G2');
            $sheet->mergeCells('A3:G3');
            $sheet->getStyle('A2:A3')->getFont()->setBold(true);

            // Add summary section
            $row = 5;
            $sheet->setCellValue('A' . $row, 'RINGKASAN PERIODE');
            $sheet->mergeCells('A' . $row . ':H' . $row);
            $sheet->getStyle('A' . $row)->applyFromArray($subHeaderStyle);

            $row++;
            $sheet->setCellValue('A' . $row, 'Total Nilai Pembelian (Masuk)');
            $sheet->setCellValue('C' . $row, $summaryData['total_purchase_amount']);
            $sheet->setCellValue('E' . $row, 'Total Nilai Pengurangan (Keluar)');
            $sheet->setCellValue('G' . $row, $summaryData['total_reduction_amount']);
            $sheet->getStyle('C' . $row . ':G' . $row)->getNumberFormat()->setFormatCode('#,##0');

            $row++;
            $sheet->setCellValue('A' . $row, 'Total Item Masuk');
            $sheet->setCellValue('C' . $row, $summaryData['total_purchase_items']);
            $sheet->setCellValue('E' . $row, 'Total Item Keluar');
            $sheet->setCellValue('G' . $row, $summaryData['total_reduction_items']);

            $row++;
            $sheet->setCellValue('A' . $row, 'Saldo Item');
            $sheet->setCellValue('C' . $row, $summaryData['saldo_qty']);
            $sheet->setCellValue('E' . $row, 'Saldo Nilai');
            $sheet->setCellValue('G' . $row, $summaryData['saldo_amount']);
            $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('#,##0');

            // Add daily data section
            $row += 2;
            $sheet->setCellValue('A' . $row, 'DATA HARIAN');
            $sheet->mergeCells('A' . $row . ':I' . $row);
            $sheet->getStyle('A' . $row)->applyFromArray($subHeaderStyle);

            $row++;
            $sheet->setCellValue('A' . $row, 'Tanggal');
            $sheet->setCellValue('B' . $row, 'Hari');
            $sheet->setCellValue('C' . $row, 'Order');
            $sheet->setCellValue('D' . $row, 'Masuk');
            $sheet->setCellValue('E' . $row, 'Keluar');
            $sheet->setCellValue('F' . $row, 'Saldo');
            $sheet->setCellValue('G' . $row, 'Nilai Masuk');
            $sheet->setCellValue('H' . $row, 'Nilai Keluar');
            $sheet->setCellValue('I' . $row, 'Saldo Nilai');
            $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray($headerStyle);

            // Add daily data
            $startDailyRow = $row + 1;
            foreach ($dailyData as $day) {
                $row++;
                $sheet->setCellValue('A' . $row, Carbon::parse($day['date'])->format('d/m/Y'));
                $sheet->setCellValue('B' . $row, $day['day_name']);
                $sheet->setCellValue('C' . $row, $day['order_count']);
                $sheet->setCellValue('D' . $row, $day['in_qty']);
                $sheet->setCellValue('E' . $row, $day['out_qty']);
                $sheet->setCellValue('F' . $row, $day['saldo_qty']);
                $sheet->setCellValue('G' . $row, $day['in_amount']);
                $sheet->setCellValue('H' . $row, $day['out_amount']);
                $sheet->setCellValue('I' . $row, $day['saldo_amount']);
                $sheet->getStyle('G' . $row . ':I' . $row)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('C' . $row . ':F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('G' . $row . ':I' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                if ($row % 2 == 0) {
                    $sheet->getStyle('A' . $row . ':I' . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F2F2F2');
                }
            }
            // Total row for daily data
            $row++;
            $sheet->setCellValue('A' . $row, 'TOTAL');
            $sheet->mergeCells('A' . $row . ':C' . $row);
            $sheet->setCellValue('D' . $row, $summaryData['total_purchase_items']);
            $sheet->setCellValue('E' . $row, $summaryData['total_reduction_items']);
            $sheet->setCellValue('F' . $row, $summaryData['saldo_qty']);
            $sheet->setCellValue('G' . $row, $summaryData['total_purchase_amount']);
            $sheet->setCellValue('H' . $row, $summaryData['total_reduction_amount']);
            $sheet->setCellValue('I' . $row, $summaryData['saldo_amount']);
            $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray($subHeaderStyle);
            $sheet->getStyle('G' . $row . ':I' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('G' . $row . ':I' . $row)->getNumberFormat()->setFormatCode('#,##0');

            // Add detail purchase section
            $row += 2;
            $sheet->setCellValue('A' . $row, 'DETAIL PEMBELIAN HARIAN');
            $sheet->mergeCells('A' . $row . ':H' . $row);
            $sheet->getStyle('A' . $row)->applyFromArray($subHeaderStyle);

            $daysWithPurchases = array_filter($dailyData, function ($day) {
                return count($day['detailed_purchases']) > 0;
            });

            foreach ($daysWithPurchases as $day) {
                $row += 2;
                $sheet->setCellValue('A' . $row, 'Detail Pembelian: ' . $day['day_name'] . ', ' . Carbon::parse($day['date'])->format('d M Y'));
                $sheet->mergeCells('A' . $row . ':H' . $row);
                $sheet->getStyle('A' . $row)->applyFromArray($subHeaderStyle);

                $row++;
                $sheet->setCellValue('A' . $row, 'No');
                $sheet->setCellValue('B' . $row, 'Nama Bahan');
                $sheet->setCellValue('C' . $row, 'Satuan');
                $sheet->setCellValue('D' . $row, 'Harga Beli');
                $sheet->setCellValue('E' . $row, 'Quantity');
                $sheet->setCellValue('F' . $row, 'Total Nilai');
                $sheet->setCellValue('G' . $row, 'Tipe');
                $sheet->setCellValue('H' . $row, 'Catatan');
                $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($headerStyle);
                $startDetailRow = $row + 1;
                foreach ($day['detailed_purchases'] as $purchase) {
                    $row++;
                    $sheet->setCellValue('A' . $row, $purchase['no']);
                    $sheet->setCellValue('B' . $row, $purchase['name']);
                    $sheet->setCellValue('C' . $row, $purchase['unit']);
                    $sheet->setCellValue('D' . $row, $purchase['purchase_price']);
                    $sheet->setCellValue('E' . $row, $purchase['quantity']);
                    $sheet->setCellValue('F' . $row, $purchase['subtotal']);
                    // Tipe dalam bahasa Indonesia
                    $typeLabels = [
                        'purchase' => 'Pembelian',
                        'usage' => 'Penggunaan',
                        'damage' => 'Rusak',
                        'other' => 'Lainnya',
                    ];
                    $sheet->setCellValue('G' . $row, isset($typeLabels[$purchase['adjustment_type']]) ? $typeLabels[$purchase['adjustment_type']] : ucfirst($purchase['adjustment_type']));
                    $sheet->setCellValue('H' . $row, $purchase['notes']);
                    $sheet->getStyle('D' . $row . ':F' . $row)->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                // Subtotal for this day
                $row++;
                $sheet->setCellValue('E' . $row, 'TOTAL');
                $sheet->setCellValue('F' . $row, $day['saldo_amount']);
                $sheet->getStyle('E' . $row . ':F' . $row)->applyFromArray($subHeaderStyle);
                $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0');
            }
            // Auto-size columns
            foreach (range('A', 'H') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Output Excel file
            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="laporan_pembelian_supplier_' . $startDate->format('Y-m-d') . '_sampai_' . $endDate->format('Y-m-d') . '.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit();
        }
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

        // Jika staff, batasi akses ke outlet mereka saja
        if (Auth::user()->role === 'staff' || Auth::user()->role === 'admin') {
            $outletId = Auth::user()->outlet_id;
        } else {
            $outletId = $request->outlet_id;
        }

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
        $totalQrisFee = 0;

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

        // Get detailed beverage sales breakdown by payment method
        $beverageSalesByPayment = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->select('orders.payment_method', DB::raw('SUM(order_items.quantity * order_items.price) as total_sales'), DB::raw('SUM(order_items.quantity) as total_quantity'))
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->when($outletId, function ($query) use ($outletId) {
                return $query->where('orders.outlet_id', $outletId);
            })
            ->where('categories.id', 2) // ID kategori minuman
            ->groupBy('orders.payment_method')
            ->get();

        // Initialize beverage sales variables
        $beverageCashSales = 0;
        $beverageQrisSales = 0;
        $beverageCashQuantity = 0;
        $beverageQrisQuantity = 0;

        // Extract cash and QRIS values
        foreach ($beverageSalesByPayment as $paymentData) {
            $paymentMethod = strtolower($paymentData->payment_method);
            if ($paymentMethod === 'cash') {
                $beverageCashSales = $paymentData->total_sales;
                $beverageCashQuantity = $paymentData->total_quantity;
            } elseif ($paymentMethod === 'qris') {
                $beverageQrisSales = $paymentData->total_sales;
                $beverageQrisQuantity = $paymentData->total_quantity;
            }
        }

        // Create structured beverage breakdown for API consistency
        $beveragePaymentBreakdown = [
            'cash' => [
                'quantity' => $beverageCashQuantity,
                'amount' => $beverageCashSales,
            ],
            'qris' => [
                'quantity' => $beverageQrisQuantity,
                'amount' => $beverageQrisSales,
            ],
            'total' => [
                'quantity' => $beverageCashQuantity + $beverageQrisQuantity,
                'amount' => $beverageSales,
            ],
        ];

        // Food (Makanan + Level) sales calculation
        $foodSales = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->when($outletId, function ($query) use ($outletId) {
                return $query->where('orders.outlet_id', $outletId);
            })
            ->where('categories.id', '!=', 2)
            ->sum(DB::raw('order_items.quantity * order_items.price'));

        $foodSalesByPayment = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->select('orders.payment_method', DB::raw('SUM(order_items.quantity * order_items.price) as total_sales'), DB::raw('SUM(order_items.quantity) as total_quantity'))
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->when($outletId, function ($query) use ($outletId) {
                return $query->where('orders.outlet_id', $outletId);
            })
            ->where('categories.id', '!=', 2)
            ->groupBy('orders.payment_method')
            ->get();

        $foodCashSales = 0;
        $foodQrisSales = 0;
        $foodCashQuantity = 0;
        $foodQrisQuantity = 0;

        foreach ($foodSalesByPayment as $paymentData) {
            $paymentMethod = strtolower($paymentData->payment_method);
            if ($paymentMethod === 'cash') {
                $foodCashSales = $paymentData->total_sales;
                $foodCashQuantity = $paymentData->total_quantity;
            } elseif ($paymentMethod === 'qris') {
                $foodQrisSales = $paymentData->total_sales;
                $foodQrisQuantity = $paymentData->total_quantity;
            }
        }

        $foodPaymentBreakdown = [
            'cash' => [
                'quantity' => $foodCashQuantity,
                'amount' => $foodCashSales,
            ],
            'qris' => [
                'quantity' => $foodQrisQuantity,
                'amount' => $foodQrisSales,
            ],
            'total' => [
                'quantity' => $foodCashQuantity + $foodQrisQuantity,
                'amount' => $foodSales,
            ],
        ];

        // Data berdasarkan metode pembayaran
        $paymentMethods = Order::whereBetween('created_at', [$startDate, $endDate])
            ->when($outletId, function ($query) use ($outletId) {
                return $query->where('outlet_id', $outletId);
            })
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total_amount'))
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

        $effectiveExpenses = $totalOpeningBalance + $totalExpenses;

        // Menghitung saldo akhir (modal awal diperlakukan sebagai bagian pengeluaran)
        $closingBalance = ($cashSales + $qrisSales) - $effectiveExpenses;

        $finalCashClosing = $cashSales - $totalExpenses;

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

                // Biaya QRIS tidak dihitung lagi
                $dailyQrisFee = 0;

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

                $dailyEffectiveExpenses = $dailyOpeningBalance + $dailyExpenses;

                // Hitung saldo akhir harian
                $dailyClosingBalance = ($dailyCashSales + $dailyQrisSales) - $dailyEffectiveExpenses;

                // Calculate daily final cash closing
                $dailyFinalCashClosing = $dailyCashSales - $dailyExpenses;

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

                // Data makanan + level harian (non-minuman)
                $dailyFoodSales = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->join('categories', 'categories.id', '=', 'products.category_id')
                    ->whereDate('orders.created_at', $currentDateStr)
                    ->when($outletId, function ($query) use ($outletId) {
                        return $query->where('orders.outlet_id', $outletId);
                    })
                    ->where('categories.id', '!=', 2)
                    ->sum(DB::raw('order_items.quantity * order_items.price'));

                // Get daily beverage sales by payment method
                $dailyBeverageByPayment = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->join('categories', 'categories.id', '=', 'products.category_id')
                    ->select('orders.payment_method', DB::raw('SUM(order_items.quantity * order_items.price) as total_sales'), DB::raw('SUM(order_items.quantity) as total_quantity'))
                    ->whereDate('orders.created_at', $currentDateStr)
                    ->when($outletId, function ($query) use ($outletId) {
                        return $query->where('orders.outlet_id', $outletId);
                    })
                    ->where('categories.id', 2) // ID kategori minuman
                    ->groupBy('orders.payment_method')
                    ->get();

                // Initialize daily beverage variables
                $dailyBeverageCashSales = 0;
                $dailyBeverageQrisSales = 0;
                $dailyBeverageCashQuantity = 0;
                $dailyBeverageQrisQuantity = 0;

                // Extract daily cash and QRIS values
                foreach ($dailyBeverageByPayment as $paymentData) {
                    $paymentMethod = strtolower($paymentData->payment_method);
                    if ($paymentMethod === 'cash') {
                        $dailyBeverageCashSales = $paymentData->total_sales;
                        $dailyBeverageCashQuantity = $paymentData->total_quantity;
                    } elseif ($paymentMethod === 'qris') {
                        $dailyBeverageQrisSales = $paymentData->total_sales;
                        $dailyBeverageQrisQuantity = $paymentData->total_quantity;
                    }
                }

                // Create structured daily beverage breakdown for API consistency
                $dailyBeverageBreakdown = [
                    'cash' => [
                        'quantity' => $dailyBeverageCashQuantity,
                        'amount' => $dailyBeverageCashSales,
                    ],
                    'qris' => [
                        'quantity' => $dailyBeverageQrisQuantity,
                        'amount' => $dailyBeverageQrisSales,
                    ],
                    'total' => [
                        'quantity' => $dailyBeverageCashQuantity + $dailyBeverageQrisQuantity,
                        'amount' => $dailyBeverageSales,
                    ],
                ];

                // Get daily food sales by payment method
                $dailyFoodByPayment = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->join('categories', 'categories.id', '=', 'products.category_id')
                    ->select('orders.payment_method', DB::raw('SUM(order_items.quantity * order_items.price) as total_sales'), DB::raw('SUM(order_items.quantity) as total_quantity'))
                    ->whereDate('orders.created_at', $currentDateStr)
                    ->when($outletId, function ($query) use ($outletId) {
                        return $query->where('orders.outlet_id', $outletId);
                    })
                    ->where('categories.id', '!=', 2)
                    ->groupBy('orders.payment_method')
                    ->get();

                $dailyFoodCashSales = 0;
                $dailyFoodQrisSales = 0;
                $dailyFoodCashQuantity = 0;
                $dailyFoodQrisQuantity = 0;

                foreach ($dailyFoodByPayment as $paymentData) {
                    $paymentMethod = strtolower($paymentData->payment_method);
                    if ($paymentMethod === 'cash') {
                        $dailyFoodCashSales = $paymentData->total_sales;
                        $dailyFoodCashQuantity = $paymentData->total_quantity;
                    } elseif ($paymentMethod === 'qris') {
                        $dailyFoodQrisSales = $paymentData->total_sales;
                        $dailyFoodQrisQuantity = $paymentData->total_quantity;
                    }
                }

                $dailyFoodBreakdown = [
                    'cash' => [
                        'quantity' => $dailyFoodCashQuantity,
                        'amount' => $dailyFoodCashSales,
                    ],
                    'qris' => [
                        'quantity' => $dailyFoodQrisQuantity,
                        'amount' => $dailyFoodQrisSales,
                    ],
                    'total' => [
                        'quantity' => $dailyFoodCashQuantity + $dailyFoodQrisQuantity,
                        'amount' => $dailyFoodSales,
                    ],
                ];

                // Tambahkan ke array data harian
                $dailyData[] = [
                    'date' => $currentDateStr,
                    'day_name' => $currentDate->locale('id')->isoFormat('dddd'),
                    'revenue' => $dailyRevenue,
                    'sub_total' => $dailySubTotal,
                    'tax' => $dailyTax,
                    'discount_amount' => $dailyDiscountAmount,
                    'beverage_sales' => $dailyBeverageSales,
                    'beverage_cash_sales' => $dailyBeverageCashSales,
                    'beverage_qris_sales' => $dailyBeverageQrisSales,
                    'beverage_cash_quantity' => $dailyBeverageCashQuantity,
                    'beverage_qris_quantity' => $dailyBeverageQrisQuantity,
                    'beverage_breakdown' => $dailyBeverageBreakdown, // Add structured breakdown
                    'food_sales' => $dailyFoodSales,
                    'food_cash_sales' => $dailyFoodCashSales,
                    'food_qris_sales' => $dailyFoodQrisSales,
                    'food_cash_quantity' => $dailyFoodCashQuantity,
                    'food_qris_quantity' => $dailyFoodQrisQuantity,
                    'food_breakdown' => $dailyFoodBreakdown,
                    'qris_sales' => $dailyQrisSales,
                    'qris_fee' => $dailyQrisFee,
                    'cash_sales' => $dailyCashSales,
                    'expenses' => $dailyExpenses,
                    'effective_expenses' => $dailyEffectiveExpenses,
                    'opening_balance' => $dailyOpeningBalance,
                    'closing_balance' => $dailyClosingBalance,
                    'final_cash_closing' => $dailyFinalCashClosing,
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
                [$year, $week] = explode('-', $weekData->period_key);

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

                // Get beverage sales by payment method for this week
                $periodBeverageByPayment = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->join('categories', 'categories.id', '=', 'products.category_id')
                    ->select('orders.payment_method', DB::raw('SUM(order_items.quantity * order_items.price) as total_sales'), DB::raw('SUM(order_items.quantity) as total_quantity'))
                    ->whereRaw('YEAR(orders.created_at) = ?', [$year])
                    ->whereRaw('WEEK(orders.created_at) = ?', [$week])
                    ->when($outletId, function ($query) use ($outletId) {
                        return $query->where('orders.outlet_id', $outletId);
                    })
                    ->where('categories.id', 2) // ID kategori minuman
                    ->groupBy('orders.payment_method')
                    ->get();

                // Initialize period beverage variables
                $weekData->beverage_cash_sales = 0;
                $weekData->beverage_qris_sales = 0;
                $weekData->beverage_cash_quantity = 0;
                $weekData->beverage_qris_quantity = 0;

                // Extract period cash and QRIS values
                foreach ($periodBeverageByPayment as $paymentData) {
                    $paymentMethod = strtolower($paymentData->payment_method);
                    if ($paymentMethod === 'cash') {
                        $weekData->beverage_cash_sales = $paymentData->total_sales;
                        $weekData->beverage_cash_quantity = $paymentData->total_quantity;
                    } elseif ($paymentMethod === 'qris') {
                        $weekData->beverage_qris_sales = $paymentData->total_sales;
                        $weekData->beverage_qris_quantity = $paymentData->total_quantity;
                    }
                }

                // Add structured beverage breakdown
                $weekData->beverage_breakdown = [
                    'cash' => [
                        'quantity' => $weekData->beverage_cash_quantity,
                        'amount' => $weekData->beverage_cash_sales,
                    ],
                    'qris' => [
                        'quantity' => $weekData->beverage_qris_quantity,
                        'amount' => $weekData->beverage_qris_sales,
                    ],
                    'total' => [
                        'quantity' => $weekData->beverage_cash_quantity + $weekData->beverage_qris_quantity,
                        'amount' => $weekData->beverage_sales,
                    ],
                ];

                // Get food sales for this week (non-beverage categories)
                $weekData->food_sales = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->join('categories', 'categories.id', '=', 'products.category_id')
                    ->whereRaw('YEAR(orders.created_at) = ?', [$year])
                    ->whereRaw('WEEK(orders.created_at) = ?', [$week])
                    ->when($outletId, function ($query) use ($outletId) {
                        return $query->where('orders.outlet_id', $outletId);
                    })
                    ->where('categories.id', '!=', 2)
                    ->sum(DB::raw('order_items.quantity * order_items.price'));

                $periodFoodByPayment = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->join('categories', 'categories.id', '=', 'products.category_id')
                    ->select('orders.payment_method', DB::raw('SUM(order_items.quantity * order_items.price) as total_sales'), DB::raw('SUM(order_items.quantity) as total_quantity'))
                    ->whereRaw('YEAR(orders.created_at) = ?', [$year])
                    ->whereRaw('WEEK(orders.created_at) = ?', [$week])
                    ->when($outletId, function ($query) use ($outletId) {
                        return $query->where('orders.outlet_id', $outletId);
                    })
                    ->where('categories.id', '!=', 2)
                    ->groupBy('orders.payment_method')
                    ->get();

                $weekData->food_cash_sales = 0;
                $weekData->food_qris_sales = 0;
                $weekData->food_cash_quantity = 0;
                $weekData->food_qris_quantity = 0;

                foreach ($periodFoodByPayment as $paymentData) {
                    $paymentMethod = strtolower($paymentData->payment_method);
                    if ($paymentMethod === 'cash') {
                        $weekData->food_cash_sales = $paymentData->total_sales;
                        $weekData->food_cash_quantity = $paymentData->total_quantity;
                    } elseif ($paymentMethod === 'qris') {
                        $weekData->food_qris_sales = $paymentData->total_sales;
                        $weekData->food_qris_quantity = $paymentData->total_quantity;
                    }
                }

                $weekData->food_breakdown = [
                    'cash' => [
                        'quantity' => $weekData->food_cash_quantity,
                        'amount' => $weekData->food_cash_sales,
                    ],
                    'qris' => [
                        'quantity' => $weekData->food_qris_quantity,
                        'amount' => $weekData->food_qris_sales,
                    ],
                    'total' => [
                        'quantity' => $weekData->food_cash_quantity + $weekData->food_qris_quantity,
                        'amount' => $weekData->food_sales,
                    ],
                ];
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
                [$year, $month] = explode('-', $monthData->period_key);

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

                // Get beverage sales by payment method for this month
                $periodBeverageByPayment = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->join('categories', 'categories.id', '=', 'products.category_id')
                    ->select('orders.payment_method', DB::raw('SUM(order_items.quantity * order_items.price) as total_sales'), DB::raw('SUM(order_items.quantity) as total_quantity'))
                    ->whereRaw('YEAR(orders.created_at) = ?', [$year])
                    ->whereRaw('MONTH(orders.created_at) = ?', [$month])
                    ->when($outletId, function ($query) use ($outletId) {
                        return $query->where('orders.outlet_id', $outletId);
                    })
                    ->where('categories.id', 2) // ID kategori minuman
                    ->groupBy('orders.payment_method')
                    ->get();

                // Initialize period beverage variables
                $monthData->beverage_cash_sales = 0;
                $monthData->beverage_qris_sales = 0;
                $monthData->beverage_cash_quantity = 0;
                $monthData->beverage_qris_quantity = 0;

                // Extract period cash and QRIS values
                foreach ($periodBeverageByPayment as $paymentData) {
                    $paymentMethod = strtolower($paymentData->payment_method);
                    if ($paymentMethod === 'cash') {
                        $monthData->beverage_cash_sales = $paymentData->total_sales;
                        $monthData->beverage_cash_quantity = $paymentData->total_quantity;
                    } elseif ($paymentMethod === 'qris') {
                        $monthData->beverage_qris_sales = $paymentData->total_sales;
                        $monthData->beverage_qris_quantity = $paymentData->total_quantity;
                    }
                }

                // Add structured beverage breakdown
                $monthData->beverage_breakdown = [
                    'cash' => [
                        'quantity' => $monthData->beverage_cash_quantity,
                        'amount' => $monthData->beverage_cash_sales,
                    ],
                    'qris' => [
                        'quantity' => $monthData->beverage_qris_quantity,
                        'amount' => $monthData->beverage_qris_sales,
                    ],
                    'total' => [
                        'quantity' => $monthData->beverage_cash_quantity + $monthData->beverage_qris_quantity,
                        'amount' => $monthData->beverage_sales,
                    ],
                ];

                // Get food sales for this month (non-beverage categories)
                $monthData->food_sales = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->join('categories', 'categories.id', '=', 'products.category_id')
                    ->whereRaw('YEAR(orders.created_at) = ?', [$year])
                    ->whereRaw('MONTH(orders.created_at) = ?', [$month])
                    ->when($outletId, function ($query) use ($outletId) {
                        return $query->where('orders.outlet_id', $outletId);
                    })
                    ->where('categories.id', '!=', 2)
                    ->sum(DB::raw('order_items.quantity * order_items.price'));

                $periodFoodByPayment = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->join('categories', 'categories.id', '=', 'products.category_id')
                    ->select('orders.payment_method', DB::raw('SUM(order_items.quantity * order_items.price) as total_sales'), DB::raw('SUM(order_items.quantity) as total_quantity'))
                    ->whereRaw('YEAR(orders.created_at) = ?', [$year])
                    ->whereRaw('MONTH(orders.created_at) = ?', [$month])
                    ->when($outletId, function ($query) use ($outletId) {
                        return $query->where('orders.outlet_id', $outletId);
                    })
                    ->where('categories.id', '!=', 2)
                    ->groupBy('orders.payment_method')
                    ->get();

                $monthData->food_cash_sales = 0;
                $monthData->food_qris_sales = 0;
                $monthData->food_cash_quantity = 0;
                $monthData->food_qris_quantity = 0;

                foreach ($periodFoodByPayment as $paymentData) {
                    $paymentMethod = strtolower($paymentData->payment_method);
                    if ($paymentMethod === 'cash') {
                        $monthData->food_cash_sales = $paymentData->total_sales;
                        $monthData->food_cash_quantity = $paymentData->total_quantity;
                    } elseif ($paymentMethod === 'qris') {
                        $monthData->food_qris_sales = $paymentData->total_sales;
                        $monthData->food_qris_quantity = $paymentData->total_quantity;
                    }
                }

                $monthData->food_breakdown = [
                    'cash' => [
                        'quantity' => $monthData->food_cash_quantity,
                        'amount' => $monthData->food_cash_sales,
                    ],
                    'qris' => [
                        'quantity' => $monthData->food_qris_quantity,
                        'amount' => $monthData->food_qris_sales,
                    ],
                    'total' => [
                        'quantity' => $monthData->food_cash_quantity + $monthData->food_qris_quantity,
                        'amount' => $monthData->food_sales,
                    ],
                ];
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

        // For admin and staff users, always show their outlet name in the subtitle
        if (Auth::user()->role === 'staff' || Auth::user()->role === 'admin') {
            $outlet = Outlet::find(Auth::user()->outlet_id);
            $subtitle .= ' | Outlet: ' . $outlet->name;
        } elseif ($outletId) {
            // For owner users who selected a specific outlet
            $outlet = Outlet::find($outletId);
            $subtitle .= ' | Outlet: ' . $outlet->name;
        } else {
            // For owner users who didn't select a specific outlet
            $subtitle .= ' | Semua Outlet';
        }

        // Generate laporan berdasarkan format yang diminta
        if ($request->format === 'pdf') {
            // Create PDF with custom view data
            $pdf = PDF::loadView(
                'pages.reports.sales_summary_pdf',
                compact(
                    'title',
                    'subtitle',
                    'totalRevenue',
                    'totalOrders',
                    'totalSubTotal',
                    'totalTax',
                    'totalDiscountAmount',
                    'beverageSales',
                    'beverageCashSales',
                    'beverageQrisSales',
                    'beverageCashQuantity',
                    'beverageQrisQuantity',
                    'beveragePaymentBreakdown', // Add structured breakdown
                    'foodSales',
                    'foodCashSales',
                    'foodQrisSales',
                    'foodCashQuantity',
                    'foodQrisQuantity',
                    'foodPaymentBreakdown',
                    'qrisSales',
                    'cashSales',
                    'totalOpeningBalance',
                    'totalExpenses',
                    'effectiveExpenses',
                    'totalQrisFee',
                    'closingBalance',
                    'finalCashClosing',
                    'dailyData',
                    'salesData',
                    'periodType',
                    'startDate',
                    'endDate',
                ),
            );

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
            $spreadsheet->getProperties()->setCreator('Seblak Sulthane')->setLastModifiedBy('Seblak Sulthane')->setTitle($title)->setSubject($subtitle)->setDescription('Laporan Ringkasan Penjualan Seblak Sulthane')->setKeywords('sales, report, seblak, sulthane')->setCategory('Financial Reports');

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
            $sheet
                ->getStyle('A1')
                ->getFont()
                ->setBold(true)
                ->setSize(20)
                ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_DARKRED));

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
            $sheet
                ->getStyle('B' . $row)
                ->getNumberFormat()
                ->setFormatCode('#,##0');
            $sheet
                ->getStyle('B' . $row)
                ->getFont()
                ->setBold(true);

            $sheet->setCellValue('D' . $row, 'Jumlah Order:');
            $sheet->setCellValue('E' . $row, $totalOrders);
            $sheet
                ->getStyle('E' . $row)
                ->getFont()
                ->setBold(true);

            $row++;
            $sheet->setCellValue('A' . $row, 'Penjualan Kotor:');
            $sheet->setCellValue('B' . $row, $totalSubTotal);
            $sheet
                ->getStyle('B' . $row)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            $sheet->setCellValue('D' . $row, 'Rata-rata Order:');
            $sheet->setCellValue('E' . $row, $avgOrderValue);
            $sheet
                ->getStyle('E' . $row)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            $row++;
            $sheet->setCellValue('A' . $row, 'Total Diskon:');
            $sheet->setCellValue('B' . $row, $totalDiscountAmount);
            $sheet
                ->getStyle('B' . $row)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            $sheet->setCellValue('D' . $row, 'Penjualan Beverage:');
            $sheet->setCellValue('E' . $row, $beverageSales);
            $sheet
                ->getStyle('E' . $row)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            $row++;
            $sheet->setCellValue('A' . $row, 'Total Pajak:');
            $sheet->setCellValue('B' . $row, $totalTax);
            $sheet
                ->getStyle('B' . $row)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            // Apply nice borders to KPI section
            $kpiBoxStyle = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E6F0FF'], // Light blue
                ],
            ];
            $sheet->getStyle('A7:B' . $row)->applyFromArray(array_merge($tableStyle, $kpiBoxStyle));
            $sheet->getStyle('D7:E' . $row)->applyFromArray(array_merge($tableStyle, $kpiBoxStyle));

            // SECTION 1.5: BEVERAGE SALES BREAKDOWN
            $row += 2;
            $sheet->setCellValue('A' . $row, 'DETAIL PENJUALAN MINUMAN');
            $sheet->mergeCells('A' . $row . ':F' . $row);
            $sheet->getStyle('A' . $row)->applyFromArray($sectionStyle);

            $row++;
            // Headers for beverage breakdown
            $sheet->setCellValue('A' . $row, 'Metode Pembayaran');
            $sheet->setCellValue('B' . $row, 'Jumlah Item');
            $sheet->setCellValue('C' . $row, 'Total Penjualan');
            $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($headerStyle);

            $row++;
            // Cash row
            $sheet->setCellValue('A' . $row, 'CASH');
            $sheet->setCellValue('B' . $row, $beverageCashQuantity);
            $sheet->setCellValue('C' . $row, $beverageCashSales);
            $sheet
                ->getStyle('C' . $row)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            $row++;
            // QRIS row
            $sheet->setCellValue('A' . $row, 'QRIS');
            $sheet->setCellValue('B' . $row, $beverageQrisQuantity);
            $sheet->setCellValue('C' . $row, $beverageQrisSales);
            $sheet
                ->getStyle('C' . $row)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            $row++;
            // Total row
            $sheet->setCellValue('A' . $row, 'TOTAL');
            $sheet->setCellValue('B' . $row, $beverageCashQuantity + $beverageQrisQuantity);
            $sheet->setCellValue('C' . $row, $beverageSales);
            $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($totalsStyle);
            $sheet
                ->getStyle('C' . $row)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            // Format the beverage details table
            $beverageBoxStyle = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'EBF1DE'], // Light green for beverages
                ],
            ];
            $sheet->getStyle('A' . ($row - 2) . ':C' . $row)->applyFromArray(array_merge($tableStyle, $beverageBoxStyle));

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
                $sheet
                    ->getStyle('B' . $paymentRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                // Calculate percentage
                $percentage = $totalRevenue > 0 ? $method->total_amount / $totalRevenue : 0;
                $sheet->setCellValue('C' . $paymentRow, $percentage);
                $sheet
                    ->getStyle('C' . $paymentRow)
                    ->getNumberFormat()
                    ->setFormatCode('0.00%');

                $paymentRow++;
            }

            // Add total row for payments
            $sheet->setCellValue('A' . $paymentRow, 'TOTAL');
            $sheet->setCellValue('B' . $paymentRow, $totalRevenue);
            $sheet->setCellValue('C' . $paymentRow, '100%');
            $sheet
                ->getStyle('B' . $paymentRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0');
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
            $sheet
                ->getStyle('B' . $cashFlowRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            $cashFlowRow++;
            $sheet->setCellValue('A' . $cashFlowRow, 'Penjualan CASH:');
            $sheet->setCellValue('B' . $cashFlowRow, $cashSales);
            $sheet
                ->getStyle('B' . $cashFlowRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            $cashFlowRow++;
            $sheet->setCellValue('A' . $cashFlowRow, 'Penjualan QRIS:');
            $sheet->setCellValue('B' . $cashFlowRow, $qrisSales);
            $sheet
                ->getStyle('B' . $cashFlowRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            $cashFlowRow++;
            $sheet->setCellValue('A' . $cashFlowRow, 'Pengeluaran Operasional:');
            $sheet->setCellValue('B' . $cashFlowRow, $totalExpenses);
            $sheet
                ->getStyle('B' . $cashFlowRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            $cashFlowRow++;
            $sheet->setCellValue('A' . $cashFlowRow, 'Modal Awal (Wajib Setor):');
            $sheet->setCellValue('B' . $cashFlowRow, $totalOpeningBalance);
            $sheet
                ->getStyle('B' . $cashFlowRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            $cashFlowRow++;
            $sheet->setCellValue('A' . $cashFlowRow, 'Total Pengeluaran (Modal + Operasional):');
            $sheet->setCellValue('B' . $cashFlowRow, $effectiveExpenses);
            $sheet
                ->getStyle('B' . $cashFlowRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            $cashFlowRow++;
            $sheet->setCellValue('A' . $cashFlowRow, 'Saldo Akhir:');
            $sheet->setCellValue('B' . $cashFlowRow, $closingBalance);
            $sheet
                ->getStyle('A' . $cashFlowRow . ':B' . $cashFlowRow)
                ->getFont()
                ->setBold(true);
            $sheet
                ->getStyle('B' . $cashFlowRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            // Highlight negative closing balance
            if ($closingBalance < 0) {
                $sheet
                    ->getStyle('B' . $cashFlowRow)
                    ->getFont()
                    ->getColor()
                    ->setRGB('FF0000');
            }

            // Add Final Cash Closing row
            $cashFlowRow++;
            $sheet->setCellValue('A' . $cashFlowRow, 'Final Cash Closing:');
            $sheet->setCellValue('B' . $cashFlowRow, $finalCashClosing);
            $sheet
                ->getStyle('A' . $cashFlowRow . ':B' . $cashFlowRow)
                ->getFont()
                ->setBold(true);
            $sheet
                ->getStyle('B' . $cashFlowRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            // Highlight negative final cash closing
            if ($finalCashClosing < 0) {
                $sheet
                    ->getStyle('B' . $cashFlowRow)
                    ->getFont()
                    ->getColor()
                    ->setRGB('FF0000');
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
                // Update cell merging to include beverage cash/QRIS and final cash closing columns
                $sheet->mergeCells('A' . $detailRow . ':Q' . $detailRow);
            } else {
                $sheet->mergeCells('A' . $detailRow . ':M' . $detailRow);
            }

            $sheet->getStyle('A' . $detailRow)->applyFromArray($sectionStyle);

            $detailRow++;

            if ($periodType === 'daily') {
                // Daily data headers - Add beverage breakdown columns
                $sheet->setCellValue('A' . $detailRow, 'TANGGAL');
                $sheet->setCellValue('B' . $detailRow, 'HARI');
                $sheet->setCellValue('C' . $detailRow, 'JUMLAH ORDER');
                $sheet->setCellValue('D' . $detailRow, 'PENJUALAN BERSIH');
                $sheet->setCellValue('E' . $detailRow, 'PENJUALAN KOTOR');
                $sheet->setCellValue('F' . $detailRow, 'DISKON');
                $sheet->setCellValue('G' . $detailRow, 'PAJAK');
                $sheet->setCellValue('H' . $detailRow, 'BEVERAGE');
                $sheet->setCellValue('I' . $detailRow, 'BEVERAGE (CASH)');
                $sheet->setCellValue('J' . $detailRow, 'BEVERAGE (QRIS)');
                $sheet->setCellValue('K' . $detailRow, 'QRIS');
                $sheet->setCellValue('L' . $detailRow, 'BIAYA QRIS');
                $sheet->setCellValue('M' . $detailRow, 'CASH');
                $sheet->setCellValue('N' . $detailRow, 'SALDO AWAL');
                $sheet->setCellValue('O' . $detailRow, 'PENGELUARAN');
                $sheet->setCellValue('P' . $detailRow, 'SALDO AKHIR');
                $sheet->setCellValue('Q' . $detailRow, 'FINAL CASH CLOSING');

                $sheet->getStyle('A' . $detailRow . ':Q' . $detailRow)->applyFromArray($headerStyle);

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
                    $sheet->setCellValue('I' . $detailRow, $day['beverage_cash_sales']);
                    $sheet->setCellValue('J' . $detailRow, $day['beverage_qris_sales']);
                    $sheet->setCellValue('K' . $detailRow, $day['qris_sales']);
                    $sheet->setCellValue('L' . $detailRow, $day['qris_fee']);
                    $sheet->setCellValue('M' . $detailRow, $day['cash_sales']);
                    $sheet->setCellValue('N' . $detailRow, $day['opening_balance']);
                    $sheet->setCellValue('O' . $detailRow, $day['expenses']);
                    $sheet->setCellValue('P' . $detailRow, $day['closing_balance']);
                    $sheet->setCellValue('Q' . $detailRow, $day['final_cash_closing']);

                    // Format numbers
                    $sheet
                        ->getStyle('D' . $detailRow . ':Q' . $detailRow)
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');

                    // Add weekend highlighting
                    if (in_array($day['day_name'], ['Sabtu', 'Minggu'])) {
                        $sheet
                            ->getStyle('A' . $detailRow . ':Q' . $detailRow)
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setRGB('FCE4D6');
                    }

                    // Highlight negative closing balance and final cash closing
                    if ($day['closing_balance'] < 0) {
                        $sheet
                            ->getStyle('P' . $detailRow)
                            ->getFont()
                            ->getColor()
                            ->setRGB('FF0000');
                    }
                    if ($day['final_cash_closing'] < 0) {
                        $sheet
                            ->getStyle('Q' . $detailRow)
                            ->getFont()
                            ->getColor()
                            ->setRGB('FF0000');
                    }

                    // Add zebra striping for better readability
                    if ($detailRow % 2 == 0) {
                        $sheet
                            ->getStyle('A' . $detailRow . ':Q' . $detailRow)
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setRGB('F2F2F2');
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
                $sheet->setCellValue('I' . $detailRow, $beverageCashSales);
                $sheet->setCellValue('J' . $detailRow, $beverageQrisSales);
                $sheet->setCellValue('K' . $detailRow, $qrisSales);
                $sheet->setCellValue('L' . $detailRow, $totalQrisFee);
                $sheet->setCellValue('M' . $detailRow, $cashSales);
                $sheet->setCellValue('N' . $detailRow, $totalOpeningBalance);
                $sheet->setCellValue('O' . $detailRow, $totalExpenses);
                $sheet->setCellValue('P' . $detailRow, $closingBalance);
                $sheet->setCellValue('Q' . $detailRow, $finalCashClosing);

                // Format totals
                $sheet->getStyle('A' . $detailRow . ':Q' . $detailRow)->applyFromArray($totalsStyle);
                $sheet
                    ->getStyle('D' . $detailRow . ':Q' . $detailRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                // Format the entire data table
                $sheet->getStyle('A' . $startDetailRow . ':Q' . $detailRow)->applyFromArray($tableStyle);

                // Enable filtering
                $sheet->setAutoFilter('A' . ($row + 1) . ':Q' . ($detailRow - 1));
            } elseif ($periodType === 'weekly' || $periodType === 'monthly') {
                // Column headers for weekly/monthly data
                $sheet->setCellValue('A' . $detailRow, 'PERIODE');
                $sheet->setCellValue('B' . $detailRow, 'JUMLAH ORDER');
                $sheet->setCellValue('C' . $detailRow, 'PENJUALAN BERSIH');
                $sheet->setCellValue('D' . $detailRow, 'PENJUALAN KOTOR');
                $sheet->setCellValue('E' . $detailRow, 'DISKON');
                $sheet->setCellValue('F' . $detailRow, 'PAJAK');
                $sheet->setCellValue('G' . $detailRow, 'BEVERAGES');
                $sheet->setCellValue('H' . $detailRow, 'BEVERAGES (CASH)');
                $sheet->setCellValue('I' . $detailRow, 'BEVERAGES (QRIS)');
                $sheet->setCellValue('J' . $detailRow, 'QRIS');
                $sheet->setCellValue('K' . $detailRow, 'BIAYA QRIS');
                $sheet->setCellValue('L' . $detailRow, 'CASH');
                $sheet->setCellValue('M' . $detailRow, 'AVG ORDER VALUE');

                $sheet->getStyle('A' . $detailRow . ':M' . $detailRow)->applyFromArray($headerStyle);

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
                    $sheet->setCellValue('H' . $detailRow, $period->beverage_cash_sales ?? 0);
                    $sheet->setCellValue('I' . $detailRow, $period->beverage_qris_sales ?? 0);
                    $sheet->setCellValue('J' . $detailRow, $period->qris_sales ?? 0);
                    $sheet->setCellValue('K' . $detailRow, $period->qris_fee);
                    $sheet->setCellValue('L' . $detailRow, $period->cash_sales ?? 0);

                    // Calculate average order value
                    $avgValue = $period->order_count > 0 ? $period->total_sales / $period->order_count : 0;
                    $sheet->setCellValue('M' . $detailRow, $avgValue);

                    // Format numbers
                    $sheet
                        ->getStyle('C' . $detailRow . ':M' . $detailRow)
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');

                    // Add zebra striping for better readability
                    if ($detailRow % 2 == 0) {
                        $sheet
                            ->getStyle('A' . $detailRow . ':M' . $detailRow)
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setRGB('F2F2F2');
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
                $sheet->setCellValue('H' . $detailRow, $beverageCashSales);
                $sheet->setCellValue('I' . $detailRow, $beverageQrisSales);
                $sheet->setCellValue('J' . $detailRow, $qrisSales);
                $sheet->setCellValue('K' . $detailRow, $totalQrisFee);
                $sheet->setCellValue('L' . $detailRow, $cashSales);
                $sheet->setCellValue('M' . $detailRow, $avgOrderValue);

                // Format totals
                $sheet->getStyle('A' . $detailRow . ':M' . $detailRow)->applyFromArray($totalsStyle);
                $sheet
                    ->getStyle('C' . $detailRow . ':M' . $detailRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                // Format the entire data table
                $sheet->getStyle('A' . $startDetailRow . ':M' . $detailRow)->applyFromArray($tableStyle);

                // Enable filtering
                $sheet->setAutoFilter('A' . ($row + 1) . ':M' . ($detailRow - 1));
            }

            // FOOTER SECTION - Generated information
            $row = $detailRow + 3;
            $sheet->setCellValue('A' . $row, 'Laporan dibuat pada: ' . now()->format('d M Y H:i'));

            if ($periodType === 'daily') {
                $sheet->mergeCells('A' . $row . ':Q' . $row);
            } else {
                $sheet->mergeCells('A' . $row . ':M' . $row);
            }

            $sheet
                ->getStyle('A' . $row)
                ->getFont()
                ->setItalic(true);

            // Auto-size columns for better readability
            if ($periodType === 'daily') {
                foreach (range('A', 'Q') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            } else {
                foreach (range('A', 'M') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            }

            // IMPORTANT: Do not use freeze panes at all to ensure full scrolling ability
            // Instead, we'll use cell styling to make the headers stand out

            // Remove any existing freeze panes
            $sheet->unfreezePane();

            // Make sure all headers are bold and stand out even without freezing
            if ($periodType === 'daily') {
                $sheet
                    ->getStyle('A' . ($row - $detailRow + $startDetailRow - 1) . ':Q' . ($row - $detailRow + $startDetailRow - 1))
                    ->getFont()
                    ->setBold(true);
            } else {
                $sheet
                    ->getStyle('A' . ($row - $detailRow + $startDetailRow - 1) . ':M' . ($row - $detailRow + $startDetailRow - 1))
                    ->getFont()
                    ->setBold(true);
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

        // Jika staff, batasi akses ke outlet mereka saja
        if (Auth::user()->role === 'staff') {
            $outletId = Auth::user()->outlet_id;
        } else {
            $outletId = $request->outlet_id;
        }

        $outletId = $request->outlet_id;

        // Base query for material orders
        $query = MaterialOrder::with(['franchise', 'items.rawMaterial'])->whereBetween('created_at', [$startDate, $endDate]);

        // Filter by outlet if provided
        if ($outletId) {
            $query->where('franchise_id', $outletId);
        } elseif (Auth::user()->role !== 'owner') {
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
            $dailyOrdersQuery = MaterialOrder::with(['items'])->whereDate('created_at', $currentDateStr);

            if ($outletId) {
                $dailyOrdersQuery->where('franchise_id', $outletId);
            } elseif (Auth::user()->role !== 'owner') {
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
                    'total_amount' => $dailyTotalAmount,
                ];
            } else {
                // If no orders on this day, still add the entry with zeros
                $dailyData[] = [
                    'date' => $currentDateStr,
                    'day_name' => $currentDate->locale('id')->isoFormat('dddd'),
                    'order_count' => 0,
                    'item_count' => 0,
                    'payment_methods' => '-',
                    'total_amount' => 0,
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
            $pdf = PDF::loadView('pages.reports.material_purchases_pdf', compact('title', 'subtitle', 'totalPurchaseAmount', 'totalOrderCount', 'dailyData', 'startDate', 'endDate'));

            // Set orientation to landscape
            $pdf->setPaper('a4', 'landscape');

            return $pdf->download('laporan_pembelian_bahan_baku_' . $startDate->format('Y-m-d') . '_sampai_' . $endDate->format('Y-m-d') . '.pdf');
        } else {
            // Create an Excel spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Laporan Bahan Baku');

            // Set document properties
            $spreadsheet->getProperties()->setCreator('Seblak Sulthane')->setLastModifiedBy('Seblak Sulthane')->setTitle($title)->setSubject($subtitle)->setDescription('Laporan Pembelian Bahan Baku Seblak Sulthane')->setKeywords('materials, report, seblak, sulthane')->setCategory('Financial Reports');

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
            $sheet
                ->getStyle('A1')
                ->getFont()
                ->setBold(true)
                ->setSize(20)
                ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_DARKRED));

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
            $sheet
                ->getStyle('B' . $row)
                ->getNumberFormat()
                ->setFormatCode('#,##0');
            $sheet
                ->getStyle('B' . $row)
                ->getFont()
                ->setBold(true);

            $sheet->setCellValue('D' . $row, 'Jumlah Pemesanan:');
            $sheet->setCellValue('E' . $row, $totalOrderCount);
            $sheet
                ->getStyle('E' . $row)
                ->getFont()
                ->setBold(true);

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
                $sheet
                    ->getStyle('F' . $detailRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                // Add weekend highlighting
                if (in_array($day['day_name'], ['Sabtu', 'Minggu'])) {
                    $sheet
                        ->getStyle('A' . $detailRow . ':F' . $detailRow)
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('FCE4D6');
                }

                // Add zebra striping for better readability
                if ($detailRow % 2 == 0) {
                    $sheet
                        ->getStyle('A' . $detailRow . ':F' . $detailRow)
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('F2F2F2');
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
            $sheet
                ->getStyle('F' . $detailRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            // Format the entire data table
            $sheet->getStyle('A' . $startDetailRow . ':F' . $detailRow)->applyFromArray($tableStyle);

            // Enable filtering
            $sheet->setAutoFilter('A' . ($row + 1) . ':F' . ($detailRow - 1));

            // FOOTER SECTION - Generated information
            $row = $detailRow + 3;
            $sheet->setCellValue('A' . $row, 'Laporan dibuat pada: ' . now()->format('d M Y H:i'));
            $sheet->mergeCells('A' . $row . ':F' . $row);
            $sheet
                ->getStyle('A' . $row)
                ->getFont()
                ->setItalic(true);

            // Auto-size columns for better readability
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Remove any existing freeze panes
            $sheet->unfreezePane();

            // Make sure all headers are bold and stand out
            $sheet
                ->getStyle('A' . ($row - $detailRow + $startDetailRow - 1) . ':F' . ($row - $detailRow + $startDetailRow - 1))
                ->getFont()
                ->setBold(true);

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
}
