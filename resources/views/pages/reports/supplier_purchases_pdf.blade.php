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
            <th colspan="2" style="background-color: #e3f2fd;">Pembelian dari Supplier (Masuk)</th>
        </tr>
        <tr>
            <th>Total Nilai Pembelian</th>
            <td>Rp {{ number_format($summaryData['total_purchase_amount'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Jumlah Transaksi Pembelian</th>
            <td>{{ number_format($summaryData['total_purchase_count'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Item Dibeli</th>
            <td>{{ number_format($summaryData['total_purchase_items'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th colspan="2" style="background-color: #fbe9e7;">Pengurangan Stok (Keluar)</th>
        </tr>
        <tr>
            <th>Jumlah Transaksi Pengurangan</th>
            <td>{{ number_format($summaryData['total_reduction_count'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Item Berkurang</th>
            <td>{{ number_format($summaryData['total_reduction_items'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Nilai Pengurangan</th>
            <td>Rp {{ number_format($summaryData['total_reduction_amount'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th colspan="2" style="background-color: #f5f5f5;">Saldo Akhir</th>
        </tr>
        <tr>
            <th>Saldo Item</th>
            <td>{{ number_format($summaryData['saldo_qty'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Saldo Nilai</th>
            <td>Rp {{ number_format($summaryData['saldo_amount'], 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="section-title">Data Harian</div>
    <table class="summary-table">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Hari</th>
                <th style="text-align:center;">Order</th>
                <th style="text-align:center;">Masuk</th>
                <th style="text-align:center;">Keluar</th>
                <th style="text-align:center;">Saldo</th>
                <th style="text-align:right;">Nilai Masuk</th>
                <th style="text-align:right;">Nilai Keluar</th>
                <th style="text-align:right;">Saldo Nilai</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dailyData as $dayData)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($dayData['date'])->format('d/m/Y') }}</td>
                    <td>{{ $dayData['day_name'] }}</td>
                    <td style="text-align: center;">{{ $dayData['order_count'] }}</td>
                    <td style="text-align: center;">{{ $dayData['in_qty'] }}</td>
                    <td style="text-align: center;">{{ $dayData['out_qty'] }}</td>
                    <td style="text-align: center;">{{ $dayData['saldo_qty'] }}</td>
                    <td style="text-align: right;">Rp {{ number_format($dayData['in_amount'], 0, ',', '.') }}</td>
                    <td style="text-align: right;">Rp {{ number_format($dayData['out_amount'], 0, ',', '.') }}</td>
                    <td style="text-align: right;">Rp {{ number_format($dayData['saldo_amount'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" style="text-align: right;">TOTAL</td>
                <td style="text-align: center;">{{ $summaryData['total_purchase_items'] }}</td>
                <td style="text-align: center;">{{ $summaryData['total_reduction_items'] }}</td>
                <td style="text-align: center;">{{ $summaryData['saldo_qty'] }}</td>
                <td style="text-align: right;">Rp {{ number_format($summaryData['total_purchase_amount'], 0, ',', '.') }}</td>
                <td style="text-align: right;">Rp {{ number_format($summaryData['total_reduction_amount'], 0, ',', '.') }}</td>
                <td style="text-align: right;">Rp {{ number_format($summaryData['saldo_amount'], 0, ',', '.') }}</td>
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
                        <th style="width: 20%;">Nama Bahan</th>
                        <th style="width: 10%; text-align: center;">Satuan</th>
                        <th style="width: 10%; text-align: center;">Jumlah</th>
                        <th style="width: 15%; text-align: right;">Harga Satuan</th>
                        <th style="width: 20%; text-align: right;">Total Nilai</th>
                        <th style="width: 10%; text-align: center;">Tipe</th>
                        <th style="width: 15%;">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dayData['detailed_purchases'] as $purchase)
                        <tr>
                            <td style="text-align: center;">{{ $purchase['no'] }}</td>
                            <td>{{ $purchase['name'] }}</td>
                            <td style="text-align: center;">{{ $purchase['unit'] }}</td>
                            <td style="text-align: center;">
                                {{ $purchase['quantity'] }}
                            </td>
                            <td style="text-align: right;">
                                @if($purchase['is_purchase'])
                                    Rp {{ number_format($purchase['purchase_price'], 0, ',', '.') }}
                                @else
                                    @if($purchase['purchase_price'])
                                        Rp {{ number_format($purchase['purchase_price'], 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                @endif
                            </td>
                            <td style="text-align: right;">
                                Rp {{ number_format($purchase['subtotal'], 0, ',', '.') }}
                            </td>
                            <td style="text-align: center;">
                                @php
                                    $typeLabels = [
                                        'purchase' => 'Pembelian',
                                        'usage' => 'Penggunaan',
                                        'damage' => 'Rusak',
                                        'other' => 'Lainnya'
                                    ];
                                @endphp
                                {{ $typeLabels[$purchase['adjustment_type']] ?? ucfirst($purchase['adjustment_type']) }}
                            </td>
                            <td>{{ $purchase['notes'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;">TOTAL</td>
                        <td style="text-align: center;">{{ $dayData['saldo_qty'] }}</td>
                        <td></td>
                        <td style="text-align: right;">Rp {{ number_format($dayData['saldo_amount'], 0, ',', '.') }}</td>
                        <td colspan="2"></td>
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
