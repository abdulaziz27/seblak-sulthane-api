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
            <th width="60%">Total Pengeluaran Bahan Baku</th>
            <td>Rp {{ number_format($totalPurchaseAmount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Jumlah Pemesanan</th>
            <td>{{ number_format($totalOrderCount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Rata-rata Nilai Pemesanan</th>
            <td>Rp {{ $totalOrderCount > 0 ? number_format($totalPurchaseAmount / $totalOrderCount, 0, ',', '.') : 0 }}</td>
        </tr>
    </table>

    <div class="section-title">Data Harian</div>
    <table class="summary-table">
        <tr>
            <th>Tanggal</th>
            <th>Hari</th>
            <th>Jumlah Order</th>
            <th>Jumlah Item</th>
            <th>Metode Pembayaran</th>
            <th>Total Pembelian</th>
        </tr>
        @php
            // Sort dailyData by date, newest first
            usort($dailyData, function ($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
        @endphp

        @foreach ($dailyData as $index => $day)
        <tr class="{{ in_array($day['day_name'], ['Sabtu', 'Minggu']) ? 'weekend' : '' }} {{ $index % 2 == 0 ? '' : 'even-row' }}">
            <td>{{ \Carbon\Carbon::parse($day['date'])->format('d/m/Y') }}</td>
            <td>{{ $day['day_name'] }}</td>
            <td>{{ $day['order_count'] }}</td>
            <td>{{ $day['item_count'] }}</td>
            <td>{{ $day['payment_methods'] }}</td>
            <td>Rp {{ number_format($day['total_amount'], 0, ',', '.') }}</td>
        </tr>
        @endforeach
        <tr style="font-weight: bold; background-color: #f2f2f2;">
            <td colspan="2">TOTAL</td>
            <td>{{ $totalOrderCount }}</td>
            <td>{{ array_sum(array_column($dailyData, 'item_count')) }}</td>
            <td>-</td>
            <td>Rp {{ number_format($totalPurchaseAmount, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="footer">
        <p>Dibuat pada {{ now()->locale('id')->translatedFormat('d M Y H:i') }} | Sistem Manajemen Seblak Sulthane</p>
    </div>
</body>
</html>
