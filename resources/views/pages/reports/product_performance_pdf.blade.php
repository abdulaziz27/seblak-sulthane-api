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

    <div class="section-title">Category Breakdown</div>

    <table class="summary-table">
        <tr>
            <th>Category</th>
            <th>Product Count</th>
            <th>Total Quantity</th>
            <th>Total Revenue</th>
        </tr>
        @foreach($categoryBreakdown as $category)
        <tr>
            <td>{{ $category->category_name }}</td>
            <td>{{ $category->product_count }}</td>
            <td>{{ number_format($category->total_quantity, 0, ',', '.') }}</td>
            <td>Rp {{ number_format($category->total_revenue, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>

    <div class="section-title">Product Performance</div>

    <table class="summary-table">
        <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Quantity Sold</th>
            <th>Revenue</th>
            <th>Order Count</th>
        </tr>
        @foreach($productPerformance as $product)
        <tr>
            <td>{{ $product->product_name }}</td>
            <td>{{ $product->category_name }}</td>
            <td>{{ number_format($product->total_quantity, 0, ',', '.') }}</td>
            <td>Rp {{ number_format($product->total_revenue, 0, ',', '.') }}</td>
            <td>{{ $product->order_count }}</td>
        </tr>
        @endforeach
    </table>

    <div class="footer">
        <p>Generated on {{ now()->format('d M Y H:i') }} | Seblak Sulthane Management System</p>
    </div>
</body>
</html>
