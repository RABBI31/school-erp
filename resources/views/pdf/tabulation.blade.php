<!DOCTYPE html>
<html lang="{{ $bn ? 'bn' : 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $exam }} — {{ $class }} {{ $section }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Noto Sans Bengali', 'Noto Sans', sans-serif; font-size: 9px; color: #111; margin: 0; }
        h1 { font-size: 15px; margin: 0; text-align: center; }
        h2 { font-size: 12px; margin: 2px 0 8px; text-align: center; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 2px 3px; text-align: center; }
        th { background: #f1f1f1; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        td.name { text-align: left; white-space: nowrap; }
        .letter { display: block; color: #555; }
        .fail { color: #b00; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $school['name'] }}</h1>
    <h2>{{ __('marksheet.tabulation') }} — {{ $exam }} {{ $year }} · {{ $class }} — {{ $section }}</h2>

    <table>
        <thead>
            <tr>
                <th>{{ __('marksheet.roll') }}</th>
                <th>{{ __('marksheet.name') }}</th>
                @foreach ($subjects as $subject)
                    <th>{{ $subject['name'] }}<br><small>({{ $subject['full'] }})</small></th>
                @endforeach
                <th>{{ __('marksheet.total') }}</th>
                <th>{{ __('marksheet.gpa') }}</th>
                <th>{{ __('marksheet.grade') }}</th>
                <th>{{ __('marksheet.position') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['roll'] }}</td>
                    <td class="name">{{ $row['name'] }}</td>
                    @foreach ($row['cells'] as $cell)
                        <td class="{{ $cell['fail'] ? 'fail' : '' }}">{{ $cell['marks'] }}<span class="letter">{{ $cell['letter'] }}</span></td>
                    @endforeach
                    <td>{{ $row['total'] }}</td>
                    <td>{{ $row['gpa'] }}</td>
                    <td class="{{ $row['letter'] === 'F' ? 'fail' : '' }}">{{ $row['letter'] }}</td>
                    <td>{{ $row['position'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
