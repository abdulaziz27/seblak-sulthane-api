<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #333;
            line-height: 1.3;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 8px;
        }

        .header h1 {
            font-size: 20px;
            margin-bottom: 3px;
        }

        .header p {
            font-size: 12px;
            color: #666;
        }

        .summary-table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }

        .summary-table th {
            text-align: left;
            padding: 5px;
            background-color: #f2f2f2;
            border-bottom: 1px solid #ddd;
            font-size: 9px;
            white-space: nowrap;
        }

        .summary-table td {
            padding: 5px;
            border-bottom: 1px solid #ddd;
            white-space: nowrap;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 8px;
            background-color: #f8f8f8;
            padding: 4px;
        }

        .footer {
            text-align: center;
            font-size: 9px;
            color: #666;
            margin-top: 20px;
            padding-top: 3px;
            border-top: 1px solid #ccc;
        }

        .page-break {
            page-break-after: always;
        }

        .card {
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .card-header {
            background-color: #4472C4;
            color: white;
            padding: 5px 10px;
            font-weight: bold;
        }

        .card-body {
            padding: 8px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-striped tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .table-bordered {
            border: 1px solid #ddd;
        }

        .table-bordered th,
        .table-bordered td {
            border: 1px solid #ddd;
            padding: 4px;
        }

        .table-secondary {
            background-color: #e9ecef;
        }

        .font-weight-bold {
            font-weight: bold;
        }

        .table-info {
            background-color: #d1ecf1;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <p>{{ $subtitle }}</p>
    </div>

    <div class="section-title">Ringkasan Periode</div>

    <table class="summary-table">
        <tr>
            <th width="60%">Total Hasil Penjualan Bersih</th>
            <td>Rp {{ number_format($totalRevenue, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Penjualan Kotor</th>
            <td>Rp {{ number_format($totalSubTotal, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Diskon</th>
            <td>Rp {{ number_format($totalDiscountAmount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Pajak</th>
            <td>Rp {{ number_format($totalTax, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Penjualan Beverage</th>
            <td>Rp {{ number_format($beverageSales, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Penjualan QRIS</th>
            <td>Rp {{ number_format($qrisSales, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Biaya Layanan QRIS (0.3%)</th>
            <td>Rp {{ number_format($totalQrisFee, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Penjualan CASH</th>
            <td>Rp {{ number_format($cashSales, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Pengeluaran</th>
            <td>Rp {{ number_format($totalExpenses, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Saldo Awal</th>
            <td>Rp {{ number_format($totalOpeningBalance, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Saldo Akhir</th>
            <td>Rp {{ number_format($closingBalance, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Jumlah Orderan</th>
            <td>{{ number_format($totalOrders, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Detail Penjualan Minuman</h5>
        </div>
        <div class="card-body">
            <table class="table table-striped table-bordered">
                <thead class="table-secondary">
                    <tr>
                        <th>Metode Pembayaran</th>
                        <th>Jumlah Item</th>
                        <th>Total Penjualan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Cash</td>
                        <td>{{ number_format($beveragePaymentBreakdown['cash']['quantity'], 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($beveragePaymentBreakdown['cash']['amount'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>QRIS</td>
                        <td>{{ number_format($beveragePaymentBreakdown['qris']['quantity'], 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($beveragePaymentBreakdown['qris']['amount'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="font-weight-bold table-info">
                        <td>TOTAL</td>
                        <td>{{ number_format($beveragePaymentBreakdown['total']['quantity'], 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($beveragePaymentBreakdown['total']['amount'], 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tampilkan data berdasarkan tipe periode -->
    @if ($periodType == 'daily')
        <div class="section-title">Data Harian</div>
        <table class="summary-table">
            <tr>
                <th>Tanggal</th>
                <th>Hari</th>
                <th>Jumlah Order</th>
                <th>Penjualan Bersih</th>
                <th>Pajak</th>
                <th>Diskon</th>
                <th>Total Minuman</th>
                <th>Minuman (CASH)</th>
                <th>Minuman (QRIS)</th>
                <th>QRIS</th>
                <th>Biaya QRIS</th>
                <th>CASH</th>
                <th>Saldo Awal</th>
                <th>Pengeluaran</th>
                <th>Saldo Akhir</th>
            </tr>
            @foreach (collect($dailyData)->sortByDesc('date') as $day)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($day['date'])->format('d/m/Y') }}</td>
                    <td>{{ $day['day_name'] }}</td>
                    <td>{{ number_format($day['orders_count'], 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($day['revenue'], 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($day['tax'], 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($day['discount_amount'], 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($day['beverage_sales'], 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($day['beverage_breakdown']['cash']['amount'], 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($day['beverage_breakdown']['qris']['amount'], 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($day['qris_sales'], 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($day['qris_fee'], 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($day['cash_sales'], 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($day['opening_balance'], 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($day['expenses'], 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($day['closing_balance'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </table>
    @elseif($periodType == 'weekly')
        <div class="section-title">Data Mingguan</div>
        <table class="summary-table">
            <tr>
                <th>Minggu</th>
                <th>Jumlah Order</th>
                <th>Penjualan Bersih</th>
                <th>Diskon</th>
                <th>Pajak</th>
                <th>Minuman</th>
                <th>Minuman (CASH)</th>
                <th>Minuman (QRIS)</th>
                <th>QRIS</th>
                <th>Biaya QRIS</th>
                <th>CASH</th>
            </tr>
            @foreach (collect($salesData)->sortByDesc('period_key') as $data)
                <tr>
                    <td>{{ $data->period_label }}</td>
                    <td>{{ number_format($data->order_count, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->total_sales, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->discount_amount, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->tax, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->beverage_sales ?? 0, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->beverage_cash_sales ?? 0, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->beverage_qris_sales ?? 0, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->qris_sales ?? 0, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->qris_fee, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->cash_sales ?? 0, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </table>
    @elseif($periodType == 'monthly')
        <div class="section-title">Data Bulanan</div>
        <table class="summary-table">
            <tr>
                <th>Bulan</th>
                <th>Jumlah Order</th>
                <th>Penjualan Bersih</th>
                <th>Diskon</th>
                <th>Pajak</th>
                <th>Minuman</th>
                <th>Minuman (CASH)</th>
                <th>Minuman (QRIS)</th>
                <th>QRIS</th>
                <th>Biaya QRIS</th>
                <th>CASH</th>
            </tr>
            @foreach (collect($salesData)->sortByDesc('period_key') as $data)
                <tr>
                    <td>{{ $data->period_label }}</td>
                    <td>{{ number_format($data->order_count, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->total_sales, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->discount_amount, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->tax, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->beverage_sales ?? 0, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->beverage_cash_sales ?? 0, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->beverage_qris_sales ?? 0, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->qris_sales ?? 0, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->qris_fee, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($data->cash_sales ?? 0, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="footer">
        <p>Dibuat pada {{ now()->locale('id')->translatedFormat('d M Y H:i') }} | Sistem Manajemen Seblak Sulthane</p>
    </div>
</body>

</html>
