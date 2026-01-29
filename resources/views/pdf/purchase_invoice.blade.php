<!DOCTYPE html>
<html>
<head>
    <title>Purchase Invoice</title>
    <style>
        body { font-family: DejaVu Sans; }
        .header { text-align: center; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .total { font-weight: bold; font-size: 1.2em; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Purchase Invoice</h1>
        <p>Invoice: {{ $purchase->invoice_number }}</p>
        <p>Date: {{ $purchase->purchase_date }}</p>
        <p>Supplier: {{ $purchase->supplier->name ?? $purchase->supplier_name ?? 'Walking Supplier' }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Warehouse</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchase->items as $item)
            <tr>
                <td>{{ $item->product->name }}</td>
                <td>{{ $item->warehouse->name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ number_format($item->unit_price, 2) }}</td>
                <td>{{ number_format($item->total_price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="4" style="text-align: right;">Subtotal</td>
                <td>{{ number_format($purchase->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td colspan="4" style="text-align: right;">Tax (15%)</td>
                <td>{{ number_format($purchase->tax_amount, 2) }}</td>
            </tr>
            <tr class="total">
                <td colspan="4" style="text-align: right;">Grand Total</td>
                <td>{{ number_format($purchase->grand_total, 2) }} ETB</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>