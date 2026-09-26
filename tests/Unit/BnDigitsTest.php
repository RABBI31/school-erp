<?php

it('converts every ascii digit', function () {
    expect(bn_digits('0123456789'))->toBe('০১২৩৪৫৬৭৮৯');
});

it('accepts integers and floats', function () {
    expect(bn_digits(2026))->toBe('২০২৬')
        ->and(bn_digits(4.5))->toBe('৪.৫')
        ->and(bn_digits(-15))->toBe('-১৫');
});

it('keeps separators in dates and money', function () {
    expect(bn_digits('26/09/2026'))->toBe('২৬/০৯/২০২৬')
        ->and(bn_digits('1,250.50'))->toBe('১,২৫০.৫০');
});

it('leaves non-digit text untouched', function () {
    expect(bn_digits('Roll 12, রোল ১২'))->toBe('Roll ১২, রোল ১২')
        ->and(bn_digits(''))->toBe('');
});
