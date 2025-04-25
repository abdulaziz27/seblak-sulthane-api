<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Invoice Pesanan Bahan #{{ $materialOrder->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 12px;
            color: #333;
        }

        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 20px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, .15);
            background-color: #fff;
        }

        .invoice-header {
            border-bottom: 2px solid #6777ef;
            padding-bottom: 10px;
            margin-bottom: 20px;
            text-align: center; /* Centered alignment */
        }

        .company-info {
            display: inline-block; /* Allows centering of inline content */
        }

        .logo {
            width: 80px;
            height: auto;
            margin-left: 20px;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #6777ef;
            margin-bottom: 3px;
            text-align: center;
        }

        .company-address {
            margin-bottom: 2px;
            color: #666;
            font-size: 11px;
            text-align: center;
        }

        .company-contact {
            color: #666;
            font-size: 11px;
            text-align: center;
        }

        .title {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            margin: 15px 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        .details {
            margin-bottom: 15px;
        }

        .details-row {
            display: flex;
            margin-bottom: 4px;
        }

        .details-label {
            width: 120px;
            font-weight: bold;
        }

        .details-value {
            flex: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }

        th {
            background-color: #f5f7ff;
            color: #333;
            font-weight: bold;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .text-right {
            text-align: right;
        }

        .footer {
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 10px;
            text-align: center;
            font-size: 10px;
            color: #777;
        }

        /* Signatures styling */
        .signatures {
            display: table;
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
        }

        .signature-box {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 15px;
        }

        .signature-title {
            font-weight: bold;
            margin-bottom: 30px;
            font-size: 11px;
        }

        .signature-line {
            border-top: 1px solid #000;
            width: 70%;
            margin: 0 auto 8px auto;
        }

        .signature-name {
            font-weight: bold;
            font-size: 11px;
        }

        .signature-role {
            font-size: 10px;
            color: #666;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 3px;
            color: white;
            font-weight: bold;
            font-size: 10px;
        }

        .status-pending {
            background-color: #ffc107;
        }

        .status-approved {
            background-color: #17a2b8;
        }

        .status-delivered {
            background-color: #28a745;
        }

        .info-heading {
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
            margin-bottom: 5px;
            font-size: 12px;
        }

        tfoot tr {
            background-color: #f5f7ff;
            font-weight: bold;
        }

        p {
            margin: 5px 0;
        }

        /* For separate notes section */
        .notes-section {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
        }

        /* For delivery information section */
        .delivery-section {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
        }

        /* Tables without borders for info sections */
        .noborder {
            border: none;
        }

        .noborder td {
            border: none;
            padding: 3px;
            vertical-align: top;
        }

        /* Force gap between info sections */
        .info-gap {
            width: 20px;
        }
    </style>
</head>

<body>
    <div class="invoice-box">
        <div class="invoice-header">
            <div class="company-info">
                <div class="company-name">Seblak Sulthane</div>
                {{-- <div class="company-address">{{ config('app.address', 'Jl. Seblak Sulthane No. 123') }}</div>
                <div class="company-contact">{{ config('app.phone', 'Telp: 082123456789') }}</div> --}}
            </div>
            <!-- Logo commented out to avoid display issues -->
            <!-- <img src="data:image/png;base64,..." class="logo" alt="Logo"> -->
        </div>

        <div class="title">INVOICE PESANAN BAHAN #{{ $materialOrder->id }}</div>

        <!-- True table layout for invoice info and outlet info side by side -->
        <table class="invoice-info-box">
            <tr>
                <td width="50%" style="background-color:#f8f9fa; padding:10px; border-radius:4px;">
                    <div class="info-heading">Informasi Invoice</div>
                    <table width="100%" cellpadding="2" cellspacing="0" border="0">
                        <tr>
                            <td width="40%"><strong>No. Invoice:</strong></td>
                            <td>INV-{{ $materialOrder->id }}-{{ date('Ymd') }}</td>
                        </tr>
                        <tr>
                            <td><strong>Tanggal:</strong></td>
                            <td>{{ $materialOrder->created_at->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>
                                <span class="status-badge status-{{ $materialOrder->status }}">
                                    {{ ucfirst($materialOrder->status) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Pembayaran:</strong></td>
                            <td>{{ $materialOrder->formatted_payment_method }}</td>
                        </tr>
                    </table>
                </td>
                <td width="50%" style="background-color:#f8f9fa; padding:10px; border-radius:4px;">
                    <div class="info-heading">Informasi Outlet</div>
                    <table width="100%" cellpadding="2" cellspacing="0" border="0">
                        <tr>
                            <td width="40%"><strong>Nama Outlet:</strong></td>
                            <td>{{ $materialOrder->franchise->name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Alamat:</strong></td>
                            <td>
                                {{ $materialOrder->franchise->address1 }}
                                @if ($materialOrder->franchise->address2)
                                    , {{ $materialOrder->franchise->address2 }}
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Dibuat Oleh:</strong></td>
                            <td>{{ $materialOrder->user->name }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Items Table -->
        <table>
            <thead>
                <tr>
                    <th style="width: 5%">No</th>
                    <th style="width: 40%">Bahan</th>
                    <th style="width: 10%">Satuan</th>
                    <th style="width: 15%">Harga</th>
                    <th style="width: 10%">Jumlah</th>
                    <th style="width: 20%">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($materialOrder->items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item->rawMaterial->name }}</td>
                        <td>{{ $item->rawMaterial->unit }}</td>
                        <td class="text-right">Rp {{ number_format($item->price_per_unit, 0, ',', '.') }}</td>
                        <td class="text-right">{{ $item->quantity }}</td>
                        <td class="text-right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right"><strong>Total:</strong></td>
                    <td class="text-right"><strong>Rp
                            {{ number_format($materialOrder->total_amount, 0, ',', '.') }}</strong></td>
                </tr>
            </tfoot>
        </table>

        <!-- Notes and Delivery Info side by side using HTML table -->
        <table width="100%" cellspacing="0" cellpadding="0" border="0">
            <tr>
                <td width="48%" style="vertical-align:top;">
                    @if ($materialOrder->notes)
                        <div style="background-color:#f8f9fa; border-radius:4px; padding:10px;">
                            <div class="info-heading">Catatan</div>
                            <p>{{ $materialOrder->notes }}</p>
                        </div>
                    @endif
                </td>
                <td width="4%"></td>
                <td width="48%" style="vertical-align:top;">
                    <div style="background-color:#f8f9fa; border-radius:4px; padding:10px;">
                        <div class="info-heading">Informasi Pengiriman</div>
                        <table class="noborder" width="100%">
                            @if ($materialOrder->approved_at)
                                <tr>
                                    <td width="40%"><strong>Disetujui Pada:</strong></td>
                                    <td>{{ $materialOrder->approved_at->format('d M Y H:i') }}</td>
                                </tr>
                            @endif
                            @if ($materialOrder->delivered_at)
                                <tr>
                                    <td width="40%"><strong>Dikirim/Diterima Pada:</strong></td>
                                    <td>{{ $materialOrder->delivered_at->format('d M Y H:i') }}</td>
                                </tr>
                            @endif
                            @if (!$materialOrder->approved_at && !$materialOrder->delivered_at)
                                <tr>
                                    <td colspan="2">Pesanan belum diproses.</td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Signature - Improved table structure -->
        <table class="signatures" border="0">
            <tr>
                <td width="50%" align="center">
                    <p class="signature-title">Penerima</p>
                </td>
                <td width="50%" align="center">
                    <p class="signature-title">Pengirim</p>
                </td>
            </tr>
            <tr>
                <td height="60"></td>
                <td height="60"></td>
            </tr>
            <tr>
                <td align="center">
                    <div class="signature-line"></div>
                    <p class="signature-name">{{ $materialOrder->franchise->name }}</p>
                    {{-- <p class="signature-role">{{ $materialOrder->franchise->leader ?? 'Penanggung Jawab Outlet' }}</p> --}}
                </td>
                <td align="center">
                    <div class="signature-line"></div>
                    <p class="signature-name">Gudang Seblak Sulthane</p>
                    {{-- <p class="signature-role">Admin Gudang</p> --}}
                </td>
            </tr>
        </table>

        <div class="footer">
            <p>Invoice ini merupakan bukti pesanan yang sah dan diproses secara otomatis.</p>
            <p>Silakan hubungi gudang jika Anda memiliki pertanyaan tentang pesanan ini.</p>
            <p>Dicetak pada: {{ now()->format('d M Y H:i:s') }}</p>
        </div>
    </div>
</body>

</html>
