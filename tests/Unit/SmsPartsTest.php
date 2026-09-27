<?php

// business-rules §5: Bangla is Unicode — 70 chars per part (67 when multipart); English 160 (153).

use App\Services\Sms\SmsParts;

test('English messages use 160 / 153 characters per part', function () {
    expect(SmsParts::count(str_repeat('a', 160)))->toBe(1)
        ->and(SmsParts::count(str_repeat('a', 161)))->toBe(2)
        ->and(SmsParts::count(str_repeat('a', 306)))->toBe(2)
        ->and(SmsParts::count(str_repeat('a', 307)))->toBe(3);
});

test('Bangla (Unicode) messages use 70 / 67 characters per part', function () {
    expect(SmsParts::count(str_repeat('ক', 70)))->toBe(1)
        ->and(SmsParts::count(str_repeat('ক', 71)))->toBe(2)
        ->and(SmsParts::count(str_repeat('ক', 134)))->toBe(2)
        ->and(SmsParts::count(str_repeat('ক', 135)))->toBe(3);
});

test('one Bangla character makes the whole message Unicode', function () {
    expect(SmsParts::count(str_repeat('a', 100).'ক'))->toBe(2);
});

test('an empty message is one part', function () {
    expect(SmsParts::count(''))->toBe(1);
});
