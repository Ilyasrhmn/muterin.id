<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>Riwayat Servis</h1>
    <p>Total biaya: Rp{{ number_format($totalCost) }}</p>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Motor</th>
                <th>Item</th>
                <th>Odometer (km)</th>
                <th>Biaya</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $l)
                <tr>
                    <td>{{ $l->serviced_at->format('d M Y') }}</td>
                    <td>{{ $l->item->motorcycle->nickname }}</td>
                    <td>{{ $l->item->name }}</td>
                    <td>{{ number_format($l->serviced_at_odometer_km) }}</td>
                    <td>Rp{{ number_format($l->cost) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada riwayat servis.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
