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

    <div class="section-title">Member vs Non-Member Comparison</div>

    <table class="summary-table">
        <tr>
            <th>Customer Type</th>
            <th>Order Count</th>
            <th>Total Revenue</th>
            <th>Avg Order Value</th>
        </tr>
        @foreach($memberStats as $stat)
        <tr>
            <td>{{ $stat->customer_type }}</td>
            <td>{{ number_format($stat->order_count, 0, ',', '.') }}</td>
            <td>Rp {{ number_format($stat->total_revenue, 0, ',', '.') }}</td>
            <td>Rp {{ number_format($stat->avg_order_value, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>

    <div class="section-title">Member Growth</div>

    <table class="summary-table">
        <tr>
            <th>Month</th>
            <th>New Members</th>
        </tr>
        @foreach($memberGrowth as $growth)
        <tr>
            <td>{{ $growth->month }}</td>
            <td>{{ $growth->new_members }}</td>
        </tr>
        @endforeach
    </table>

    <div class="section-title">Top Customers</div>

    <table class="summary-table">
        <tr>
            <th>Rank</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Transactions</th>
            <th>Total Spent</th>
            <th>Avg Order Value</th>
        </tr>
        @php $rank = 1; @endphp
        @foreach($topCustomers as $customer)
        <tr>
            <td>{{ $rank++ }}</td>
            <td>{{ $customer->member_name }}</td>
            <td>{{ $customer->member_phone }}</td>
            <td>{{ $customer->total_transactions }}</td>
            <td>Rp {{ number_format($customer->total_spent, 0, ',', '.') }}</td>
            <td>Rp {{ number_format($customer->avg_order_value, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>

    <div class="footer">
        <p>Generated on {{ now()->format('d M Y H:i') }} | Seblak Sulthane Management System</p>
    </div>
</body>
</html>
