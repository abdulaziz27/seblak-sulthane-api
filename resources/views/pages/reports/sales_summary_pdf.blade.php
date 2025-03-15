<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px; /* Dikurangi dari 12px */
            color: #333;
            line-height: 1.3; /* Dikurangi dari 1.5 */
        }

        .header {
            text-align: center;
            margin-bottom: 15px; /* Dikurangi dari 20px */
            border-bottom: 1px solid #ccc;
            padding-bottom: 8px; /* Dikurangi dari 10px */
        }

        .header h1 {
            font-size: 20px; /* Dikurangi dari 24px */
            margin-bottom: 3px; /* Dikurangi dari 5px */
        }

        .header p {
            font-size: 12px; /* Dikurangi dari 14px */
            color: #666;
        }

        .summary-table {
            width: 100%;
            margin-bottom: 15px; /* Dikurangi dari 20px */
            border-collapse: collapse;
        }

        .summary-table th {
            text-align: left;
            padding: 5px; /* Dikurangi dari 8px */
            background-color: #f2f2f2;
            border-bottom: 1px solid #ddd;
            font-size: 9px; /* Tambahan untuk header tabel */
            white-space: nowrap; /* Tambahan agar teks tidak wrap */
        }

        .summary-table td {
            padding: 5px; /* Dikurangi dari 8px */
            border-bottom: 1px solid #ddd;
            white-space: nowrap; /* Tambahan agar teks tidak wrap */
        }

        .section-title {
            font-size: 14px; /* Dikurangi dari 16px */
            font-weight: bold;
            margin-top: 15px; /* Dikurangi dari 20px */
            margin-bottom: 8px; /* Dikurangi dari 10px */
            background-color: #f8f8f8;
            padding: 4px; /* Dikurangi dari 5px */
        }

        .daily-chart {
            width: 100%;
            height: 180px; /* Dikurangi dari 200px */
            margin-bottom: 15px; /* Dikurangi dari 20px */
        }

        .footer {
            text-align: center;
            font-size: 9px; /* Dikurangi dari 10px */
            color: #666;
            margin-top: 20px; /* Dikurangi dari 30px */
            padding-top: 3px; /* Dikurangi dari 5px */
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

    <div class="section-title">Breakdown Harian</div>

    <table class="summary-table">
        <tr>
            <th>Tanggal</th>
            <th>Hari</th>
            <th>Penjualan Bersih</th>
            <th>Penjualan Kotor</th>
            <th>Diskon</th>
            <th>Pajak</th>
            <th>Beverage</th>
            <th>QRIS</th>
            <th>Biaya QRIS</th>
            <th>CASH</th>
            <th>Pengeluaran</th>
            <th>Saldo Awal</th>
            <th>Saldo Akhir</th>
        </tr>
        @foreach ($dailyData as $day)
        <tr>
            <td>{{ \Carbon\Carbon::parse($day['date'])->format('d/m/Y') }}</td>
            <td>{{ \Carbon\Carbon::parse($day['date'])->translatedFormat('l') }}</td>
            <td>Rp {{ number_format($day['revenue'], 0, ',', '.') }}</td>
            <td>Rp {{ number_format($day['sub_total'], 0, ',', '.') }}</td>
            <td>Rp {{ number_format($day['discount_amount'], 0, ',', '.') }}</td>
            <td>Rp {{ number_format($day['tax'], 0, ',', '.') }}</td>
            <td>Rp {{ number_format($day['beverage_sales'], 0, ',', '.') }}</td>
            <td>Rp {{ number_format($day['qris_sales'], 0, ',', '.') }}</td>
            <td>Rp {{ number_format($day['qris_fee'], 0, ',', '.') }}</td>
            <td>Rp {{ number_format($day['cash_sales'], 0, ',', '.') }}</td>
            <td>Rp {{ number_format($day['expenses'], 0, ',', '.') }}</td>
            <td>Rp {{ number_format($day['opening_balance'], 0, ',', '.') }}</td>
            <td>Rp {{ number_format($day['closing_balance'], 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>

    <div class="footer">
        <p>Dibuat pada {{ now()->translatedFormat('d M Y H:i') }} | Sistem Manajemen Seblak Sulthane</p>
    </div>
</body>

</html>
