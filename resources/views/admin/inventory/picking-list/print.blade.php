<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Picking List Print</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; color: #111; margin: 24px; }
        h2 { margin: 0 0 8px; font-size: 20px; }
        .meta { font-size: 12px; margin-bottom: 4px; }
        .meta span { display: inline-block; min-width: 90px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #333; padding: 6px 8px; font-size: 12px; }
        th { background: #f2f2f2; text-align: left; }
        td.text-end { text-align: right; }
        .no-print { margin-top: 16px; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <h2>Picking List</h2>
    <div class="meta"><span>Tanggal</span>: {{ $date }}</div>
    <div class="meta"><span>Status</span>: {{ $status }}</div>
    <div class="meta"><span>Divisi</span>: {{ $divisiName ?: 'Semua' }}</div>
    <div class="meta"><span>Lane</span>: {{ $laneName ?: 'Semua' }}</div>
    <div class="meta"><span>Keyword</span>: {{ $keyword ?: '-' }}</div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>SKU</th>
                <th>Nama</th>
                <th>Divisi</th>
                <th>Lane</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Remaining</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['sku'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['divisi'] }}</td>
                    <td>{{ $row['lane'] }}</td>
                    <td class="text-end">{{ $row['qty'] }}</td>
                    <td class="text-end">{{ $row['remaining_qty'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="no-print">
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <script>
        window.addEventListener('load', () => {
            window.print();
        });
    </script>
</body>
</html>
