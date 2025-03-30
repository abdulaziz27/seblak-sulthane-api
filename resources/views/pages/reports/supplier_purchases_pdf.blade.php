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
            table-layout: fixed;
        }

        .summary-table th,
        .summary-table td {
            padding: 5px;
            border: 1px solid #ddd;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .summary-table th {
            text-align: left;
            background-color: #f2f2f2;
            font-size: 9px;
            font-weight: bold;
        }

        .summary-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 8px;
            background-color: #f8f8f8;
            padding: 4px;
        }

        .day-title {
            font-size: 12px;
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 5px;
            background-color: #e9ecef;
            padding: 3px;
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

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .total-row {
            font-weight: bold;
            background-color: #f2f2f2;
        }

        .summary-table tfoot tr td {
            border-top: 2px solid #ddd !important;
            background-color: #f2f2f2;
            font-weight: bold;
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
            <th style="width: 60%;">Total Pembelian Bahan Baku ke Supplier</th>
            <td style="text-align: left;">Rp {{ number_format($summaryData['total_purchase_amount'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th style="width: 60%;">Total Jumlah Pemesanan ke Supplier</th>
            <td style="text-align: left;">{{ number_format($summaryData['total_order_count'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th style="width: 60%;">Total Item yang Dipesan</th>
            <td style="text-align: left;">{{ number_format($summaryData['total_item_count'], 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="section-title">Data Harian</div>
    <table class="summary-table">
        <thead>
            <tr>
                <th style="width: 15%;">Tanggal</th>
                <th style="width: 15%;">Hari</th>
                <th style="width: 20%; text-align: center;">Jumlah Order</th>
                <th style="width: 20%; text-align: center;">Jumlah Item</th>
                <th style="width: 30%; text-align: right;">Total Pembelian</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dailyData as $dayData)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($dayData['date'])->format('d/m/Y') }}</td>
                    <td>{{ $dayData['day_name'] }}</td>
                    <td style="text-align: center;">{{ $dayData['order_count'] }}</td>
                    <td style="text-align: center;">{{ $dayData['item_count'] }}</td>
                    <td style="text-align: right;">Rp {{ number_format($dayData['purchase_amount'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" style="text-align: right;">TOTAL</td>
                <td style="text-align: center;">{{ $summaryData['total_order_count'] }}</td>
                <td style="text-align: center;">{{ $summaryData['total_item_count'] }}</td>
                <td style="text-align: right;">Rp {{ number_format($summaryData['total_purchase_amount'], 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="section-title">Detail Pembelian Harian</div>

    @foreach($dailyData as $dayData)
        @if(count($dayData['detailed_purchases']) > 0)
            <div class="day-title">{{ $dayData['day_name'] }}, {{ \Carbon\Carbon::parse($dayData['date'])->format('d M Y') }}</div>

            <table class="summary-table">
                <thead>
                    <tr>
                        <th style="width: 5%; text-align: center;">No</th>
                        <th style="width: 25%;">Nama Bahan</th>
                        <th style="width: 10%; text-align: center;">Satuan</th>
                        <th style="width: 15%; text-align: right;">Harga Beli</th>
                        <th style="width: 15%; text-align: right;">Harga Jual</th>
                        <th style="width: 10%; text-align: center;">Quantity</th>
                        <th style="width: 20%; text-align: right;">Total Nilai Beli</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dayData['detailed_purchases'] as $purchase)
                        <tr>
                            <td style="text-align: center;">{{ $purchase['no'] }}</td>
                            <td>{{ $purchase['name'] }}</td>
                            <td style="text-align: center;">{{ $purchase['unit'] }}</td>
                            <td style="text-align: right;">Rp {{ number_format($purchase['purchase_price'], 0, ',', '.') }}</td>
                            <td style="text-align: right;">Rp {{ number_format($purchase['selling_price'], 0, ',', '.') }}</td>
                            <td style="text-align: center;">{{ $purchase['quantity'] }}</td>
                            <td style="text-align: right;">Rp {{ number_format($purchase['subtotal'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="5"></td>
                        <td style="text-align: center;">TOTAL</td>
                        <td style="text-align: right;">Rp {{ number_format($dayData['purchase_amount'], 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>

            @if(!$loop->last)
                <div style="margin-bottom: 20px;"></div>
            @endif
        @endif
    @endforeach

    <div class="footer">
        <p>Dibuat pada {{ now()->locale('id')->translatedFormat('d M Y H:i') }} | Sistem Manajemen Seblak Sulthane</p>
    </div>
</body>
</html>
