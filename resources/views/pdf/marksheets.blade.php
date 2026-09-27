<!DOCTYPE html>
<html lang="{{ $bn ? 'bn' : 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $exam }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Noto Sans Bengali', 'Noto Sans', sans-serif; font-size: 11px; color: #111; margin: 0; }
        .sheet { page-break-after: always; padding: 4px; }
        .sheet:last-child { page-break-after: auto; }
        .header { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #111; padding-bottom: 6px; }
        .header img { width: 56px; height: 56px; object-fit: contain; }
        .header h1 { font-size: 18px; margin: 0; }
        .header p { margin: 1px 0; color: #444; }
        .title { text-align: center; margin: 10px 0; }
        .title span { border: 1px solid #111; border-radius: 4px; padding: 3px 16px; font-weight: bold; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: center; }
        th { background: #f1f1f1; }
        td.name { text-align: left; }
        .info td { border: none; text-align: left; padding: 2px 0; }
        .fail { color: #b00; font-weight: bold; }
        .summary { margin-top: 10px; }
        .summary td { text-align: left; }
        .footer { display: flex; justify-content: space-between; margin-top: 48px; }
        .footer div { border-top: 1px solid #111; padding-top: 2px; min-width: 150px; text-align: center; position: relative; }
        .footer img { position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); height: 40px; }
    </style>
</head>
<body>
@foreach ($sheets as $sheet)
    <div class="sheet">
        <div class="header">
            @if ($school['logo'])<img src="{{ $school['logo'] }}" alt="">@endif
            <div>
                <h1>{{ $school['name'] }}</h1>
                @if ($school['address'])<p>{{ $school['address'] }}</p>@endif
                @if ($school['eiin'])<p>EIIN: {{ $school['eiin'] }}</p>@endif
            </div>
        </div>

        <div class="title"><span>{{ __('marksheet.title') }} — {{ $exam }} {{ $year }}</span></div>

        <table class="info">
            <tr>
                <td>{{ __('marksheet.name') }}: <strong>{{ $sheet['student']['name'] }}</strong></td>
                <td>{{ __('marksheet.student_id') }}: {{ $sheet['student']['uid'] }}</td>
            </tr>
            @if ($sheet['student']['father'] || $sheet['student']['mother'])
                <tr>
                    <td>{{ __('marksheet.father') }}: {{ $sheet['student']['father'] }}</td>
                    <td>{{ __('marksheet.mother') }}: {{ $sheet['student']['mother'] }}</td>
                </tr>
            @endif
            <tr>
                <td>{{ __('marksheet.class') }}: {{ $sheet['student']['class'] }} — {{ $sheet['student']['section'] }}@if ($sheet['student']['group']) ({{ $sheet['student']['group'] }})@endif</td>
                <td>{{ __('marksheet.roll') }}: {{ $sheet['student']['roll'] }}</td>
            </tr>
        </table>

        <table style="margin-top: 8px">
            <thead>
                <tr>
                    <th style="text-align: left">{{ __('marksheet.subject') }}</th>
                    <th>{{ __('marksheet.full') }}</th>
                    <th>{{ __('marksheet.cq') }}</th>
                    <th>{{ __('marksheet.mcq') }}</th>
                    <th>{{ __('marksheet.practical') }}</th>
                    <th>{{ __('marksheet.total') }}</th>
                    <th>{{ __('marksheet.grade') }}</th>
                    <th>{{ __('marksheet.point') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sheet['groups'] as $group)
                    @foreach ($group['papers'] as $i => $paper)
                        <tr>
                            <td class="name">{{ $paper['name'] }}@if ($group['optional'] && $i === 0) <small>({{ __('marksheet.optional') }})</small>@endif</td>
                            <td>{{ $paper['full'] }}</td>
                            <td>{{ $paper['cq'] }}</td>
                            <td>{{ $paper['mcq'] }}</td>
                            <td>{{ $paper['practical'] }}</td>
                            <td>{{ $paper['total'] }}</td>
                            @if ($i === 0)
                                <td rowspan="{{ count($group['papers']) }}" class="{{ $group['letter'] === 'F' ? 'fail' : '' }}">{{ $group['letter'] }}</td>
                                <td rowspan="{{ count($group['papers']) }}">{{ $group['point'] }}</td>
                            @endif
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>

        <table class="summary">
            <tr>
                <td>{{ __('marksheet.total_marks') }}: <strong>{{ $sheet['total_marks'] }}</strong></td>
                <td>{{ __('marksheet.gpa') }}: <strong>{{ $sheet['gpa'] }}</strong>@if ($sheet['has_optional']) <small>({{ __('marksheet.without_optional') }}: {{ $sheet['gpa_without_optional'] }})</small>@endif</td>
                <td>{{ __('marksheet.grade') }}: <strong class="{{ $sheet['letter'] === 'F' ? 'fail' : '' }}">{{ $sheet['letter'] }}</strong></td>
            </tr>
            <tr>
                <td>{{ __('marksheet.result') }}: <strong class="{{ $sheet['passed'] ? '' : 'fail' }}">{{ __($sheet['passed'] ? 'marksheet.passed' : 'marksheet.failed') }}</strong></td>
                <td>{{ __('marksheet.position_section') }}: {{ $sheet['position_section'] }}</td>
                <td>{{ __('marksheet.position_class') }}: {{ $sheet['position_class'] }}</td>
            </tr>
        </table>

        <div class="footer">
            <div>{{ __('marksheet.guardian') }}</div>
            <div>{{ __('marksheet.class_teacher') }}</div>
            <div>@if ($signature)<img src="{{ $signature }}" alt="">@endif{{ __('marksheet.principal') }}</div>
        </div>
    </div>
@endforeach
</body>
</html>
