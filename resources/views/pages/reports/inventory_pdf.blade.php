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

        .low-stock {
            color: #ff0000;
            font-weight: bold;
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

    <div class="section-title">Current Inventory Status</div>

    <table class="summary-table">
        <tr>
            <th>Material</th>
            <th>Unit</th>
            <th>Current Stock</th>
            <th>Price per Unit</th>
            <th>Total Value</th>
            <th>Status</th>
        </tr>
        @php $totalInventoryValue = 0; @endphp
        @foreach($rawMaterials as $material)
            @php
                $itemValue = $material->stock * $material->price;
                $totalInventoryValue += $itemValue;
                $lowStock = $material->stock < 10;
            @endphp
        <tr>
            <td>{{ $material->name }}</td>
            <td>{{ $material->unit }}</td>
            <td class="{{ $lowStock ? 'low-stock' : '' }}">{{ $material->stock }}</td>
            <td>Rp {{ number_format($material->price, 0, ',', '.') }}</td>
            <td>Rp {{ number_format($itemValue, 0, ',', '.') }}</td>
            <td>{{ $material->is_active ? 'Active' : 'Inactive' }}</td>
        </tr>
        @endforeach
        <tr>
            <th colspan="4" style="text-align: right;">Total Inventory Value:</th>
            <th>Rp {{ number_format($totalInventoryValue, 0, ',', '.') }}</th>
            <th></th>
        </tr>
    </table>

    <div class="section-title">Outlet Material Spending Summary</div>

    <table class="summary-table">
        <tr>
            <th>Outlet</th>
            <th>Order Count</th>
            <th>Total Spending</th>
        </tr>
        @foreach($outletSpending as $outlet)
        <tr>
            <td>{{ $outlet->outlet_name }}</td>
            <td>{{ $outlet->order_count }}</td>
            <td>Rp {{ number_format($outlet->total_spending, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>

    <div class="section-title">Recent Material Orders (Last 30 Days)</div>

    <table class="summary-table">
        <tr>
            <th>Order ID</th>
            <th>Date</th>
            <th>Outlet</th>
            <th>Status</th>
            <th>Total Amount</th>
        </tr>
        @foreach($materialOrders as $order)
        <tr>
            <td>#{{ $order->id }}</td>
            <td>{{ $order->created_at->format('d M Y') }}</td>
            <td>{{ $order->franchise->name }}</td>
            <td>{{ ucfirst($order->status) }}</td>
            <td>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>

    <div class="footer">
        <p>Generated on {{ now()->format('d M Y H:i') }} | Seblak Sulthane Management System</p>
    </div>
</body>
</html>
