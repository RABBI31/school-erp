<!DOCTYPE html>
<html lang="{{ $bn ? 'bn' : 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Noto Sans Bengali', 'Noto Sans', sans-serif; font-size: 11px; color: #111; margin: 0; }
        .header { display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #111; padding-bottom: 6px; }
        .header img { width: 48px; height: 48px; object-fit: contain; }
        .header h1 { font-size: 16px; margin: 0; }
        .header p { margin: 1px 0; color: #444; }
        .title { text-align: center; margin: 8px 0; }
        .title span { border: 1px solid #111; border-radius: 4px; padding: 2px 12px; font-weight: bold; font-size: 13px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f1f1f1; }
        .num { text-align: right; white-space: nowrap; }
        .info td { border: none; padding: 2px 0; }
        .total td { font-weight: bold; }
        .footer { display: flex; justify-content: space-between; margin-top: 28px; }
        .footer div { border-top: 1px solid #111; padding-top: 2px; min-width: 120px; text-align: center; }
        .stamp { position: fixed; top: 40%; left: 10%; right: 10%; text-align: center; font-size: 48px; font-weight: bold; color: rgba(200, 0, 0, 0.25); transform: rotate(-20deg); border: 6px solid rgba(200, 0, 0, 0.25); }
    </style>
</head>
<body>
    @if ($cancelled)
        <div class="stamp">{{ __('receipt.cancelled') }}</div>
    @endif

    <div class="header">
        @if ($school['logo'])
            <img src="{{ $school['logo'] }}" alt="">
        @endif
        <div>
            <h1>{{ $school['name'] }}</h1>
            @if ($school['address'])<p>{{ $school['address'] }}</p>@endif
            <p>
                @if ($school['phone']){{ __('receipt.phone') }}: {{ $school['phone'] }}@endif
                @if ($school['eiin']) &nbsp; EIIN: {{ $school['eiin'] }}@endif
            </p>
        </div>
    </div>

    <div class="title"><span>{{ __('receipt.title') }}</span></div>

    <div class="meta">
        <div>{{ __('receipt.no') }}: <strong>{{ $receipt_no }}</strong></div>
        <div>{{ __('receipt.date') }}: {{ $date }}</div>
    </div>

    <table class="info">
        <tr><td>{{ __('receipt.student') }}: <strong>{{ $student['name'] }}</strong></td><td>{{ __('receipt.student_id') }}: {{ $student['uid'] }}</td></tr>
        <tr><td>{{ __('receipt.class') }}: {{ $student['class'] }} — {{ $student['section'] }}</td><td>{{ __('receipt.roll') }}: {{ $student['roll'] }}</td></tr>
    </table>

    <table style="margin-top: 8px">
        <thead>
            <tr>
                <th>{{ __('receipt.month') }}</th>
                <th>{{ __('receipt.fees') }}</th>
                <th class="num">{{ __('receipt.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['month'] }}<br><small>{{ $line['invoice_no'] }}</small></td>
                    <td>{{ $line['heads'] }}</td>
                    <td class="num">{{ $line['amount'] }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">{{ __('receipt.total_paid') }} ({{ $method }}@if ($transaction_ref) — {{ $transaction_ref }}@endif)</td>
                <td class="num">৳ {{ $amount }}</td>
            </tr>
            <tr>
                <td colspan="2">{{ __('receipt.due_now') }}</td>
                <td class="num">৳ {{ $due_now }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <div>{{ __('receipt.received_by') }}@if ($received_by): {{ $received_by }}@endif</div>
        <div>{{ __('receipt.guardian_signature') }}</div>
    </div>
</body>
</html>
