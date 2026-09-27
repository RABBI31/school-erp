<?php

use App\Support\Money;

it('converts taka to paisa without floats', function (string|int|float|null $taka, int $paisa) {
    expect(Money::toPaisa($taka))->toBe($paisa);
})->with([
    ['1250', 125000],
    ['1250.5', 125050],
    ['1,250.50', 125050],
    [1250, 125000],
    [0.1 + 0.2, 30],
    ['10.005', 1001],
    [null, 0],
]);

it('formats paisa as a decimal string', function () {
    expect(Money::fromPaisa(125050))->toBe('1250.50')
        ->and(Money::fromPaisa(5))->toBe('0.05')
        ->and(Money::fromPaisa(0))->toBe('0.00');
});

it('takes a percentage rounded half up to the paisa', function () {
    expect(Money::percentOf(120000, 50))->toBe(60000)
        ->and(Money::percentOf(100000, '33.33'))->toBe(33330)
        ->and(Money::percentOf(101, 50))->toBe(51);
});

it('formats amounts with lakh grouping', function () {
    expect(Money::format('125000.50'))->toBe('1,25,000.50')
        ->and(Money::format('1250'))->toBe('1,250')
        ->and(Money::format('999'))->toBe('999')
        ->and(Money::format('12345678'))->toBe('1,23,45,678')
        ->and(Money::format('1250.00', true))->toBe('১,২৫০');
});
