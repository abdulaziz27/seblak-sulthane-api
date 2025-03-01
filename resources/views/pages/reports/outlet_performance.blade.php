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

        .subsection-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 5px;
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

    <div class="section-title">Outlet Performance Summary</div>

    <table class="summary-table">
        <tr>
            <th>Outlet</th>
            <th>Total Orders</th>
            <th>Total Revenue</th>
            <th>Avg Order Value</th>
            <th>Customers</th>
        </tr>
        @foreach($outletPerformance as $outlet)
        <tr>
            <td>{{ $outlet->outlet_name }}</td>
            <td>{{ number_format($outlet->total_orders, 0, ',', '.') }}</td>
            <td>Rp {{ number_format($outlet->total_revenue, 0, ',', '.') }}</td>
            <td>Rp {{ number_format($outlet->total_orders > 0 ? $outlet->total_revenue / $outlet->total_orders : 0, 0, ',', '.') }}</td>
            <td>{{ number_format($outlet->total_customers, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>

    <div class="section-title">Tax and Discount Analysis</div>

    <table class="summary-table">
        <tr>
            <th>Outlet</th>
            <th>Total Tax</th>
            <th>Tax %</th>
            <th>Total Discount</th>
            <th>Discount %</th>
        </tr>
        @foreach($outletPerformance as $outlet)
        <tr>
            <td>{{ $outlet->outlet_name }}</td>
            <td>Rp {{ number_format($outlet->total_tax, 0, ',', '.') }}</td>
            <td>{{ $outlet->total_revenue > 0 ? number_format(($outlet->total_tax / $outlet->total_revenue) * 100, 1) : 0 }}%</td>
            <td>Rp {{ number_format($outlet->total_discount, 0, ',', '.') }}</td>
            <td>{{ $outlet->total_revenue > 0 ? number_format(($outlet->total_discount / $outlet->total_revenue) * 100, 1) : 0 }}%</td>
        </tr>
        @endforeach
    </table>

    <div class="section-title">Daily Trends by Outlet</div>

    @foreach($dailyTrends as $outletId => $trends)
        @php $outletName = $trends->first()->outlet_name; @endphp

        <div class="subsection-title">{{ $outletName }}</div>

        <table class="summary-table">
            <tr>
                <th>Date</th>
                <th>Revenue</th>
                <th>Orders</th>
                <th>Avg Order Value</th>
            </tr>
            @foreach($trends as $trend)
            <tr>
                <td>{{ \Carbon\Carbon::parse($trend->date)->format('d M Y') }}</td>
                <td>Rp {{ number_format($trend->daily_revenue, 0, ',', '.') }}</td>
                <td>{{ $trend->daily_orders }}</td>
                <td>Rp {{ number_format($trend->daily_orders > 0 ? $trend->daily_revenue / $trend->daily_orders : 0, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </table>

        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach

    <div class="footer">
        <p>Generated on {{ now()->format('d M Y H:i') }} | Seblak Sulthane Management System</p>
    </div>
</body>
</html>
