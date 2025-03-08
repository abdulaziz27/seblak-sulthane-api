<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 14px;
            color: #666;
        }

        .summary-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .summary-table th {
            text-align: left;
            padding: 8px;
            background-color: #f2f2f2;
            border-bottom: 1px solid #ddd;
        }

        .summary-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            background-color: #f8f8f8;
            padding: 5px;
        }

        .daily-chart {
            width: 100%;
            height: 200px;
            margin-bottom: 20px;
        }

        .footer {
            text-align: center;
            font-size: 10px;
            color: #666;
            margin-top: 30px;
            padding-top: 5px;
            border-top: 1px solid #ccc;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <p>{{ $subtitle }}</p>
    </div>

    <div class="section-title">Sales Summary</div>

    <table class="summary-table">
        <tr>
            <th width="30%">Total Revenue</th>
            <td>Rp {{ number_format($totalRevenue, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Orders</th>
            <td>{{ number_format($totalOrders, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Average Order Value</th>
            <td>Rp {{ number_format($avgOrderValue, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Tax</th>
            <td>Rp {{ number_format($taxTotal, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Discounts</th>
            <td>Rp {{ number_format($discountTotal, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="section-title">Payment Methods</div>

    <table class="summary-table">
        <tr>
            <th>Payment Method</th>
            <th>Number of Orders</th>
            <th>Total Amount</th>
        </tr>
        @foreach ($paymentMethods as $method)
            <tr>
                <td>{{ strtoupper($method->payment_method) }}</td>
                <td>{{ number_format($method->count, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($method->total, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <div class="section-title">Cash Flow Summary</div>

    <table class="summary-table">
        <tr>
            <th width="30%">Opening Balance</th>
            <td>Rp {{ number_format($totalOpeningBalance, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Cash Sales</th>
            <td>Rp {{ number_format($totalCashSales, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Expenses</th>
            <td>Rp {{ number_format($totalExpenses, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Closing Balance</th>
            <td>Rp {{ number_format($closingBalance, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="section-title">Daily Sales</div>

    <table class="summary-table">
        <tr>
            <th>Date</th>
            <th>Total Sales</th>
            <th>Number of Orders</th>
            <th>Average Order Value</th>
        </tr>
        @foreach ($dailySales as $sale)
            <tr>
                <td>{{ \Carbon\Carbon::parse($sale->date)->format('d M Y') }}</td>
                <td>Rp {{ number_format($sale->total_sales, 0, ',', '.') }}</td>
                <td>{{ $sale->order_count }}</td>
                <td>Rp
                    {{ number_format($sale->order_count > 0 ? $sale->total_sales / $sale->order_count : 0, 0, ',', '.') }}
                </td>
            </tr>
        @endforeach
    </table>

    <div class="section-title">Beverage Sales Summary</div>
    <table class="summary-table">
        <tr>
            <th width="30%">Total Beverage Sales</th>
            <td>Rp {{ number_format($beverageSales->total_amount ?? 0, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="footer">
        <p>Generated on {{ now()->format('d M Y H:i') }} | Seblak Sulthane Management System</p>
    </div>
</body>

</html>
