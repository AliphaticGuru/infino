<?php

declare(strict_types=1);

use App\Modules\Pricing\Support\Money;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Money::init(array_merge(config('pricing.exponents'), [
        'JPY' => 0,
        'BIF' => 3,
    ]));
});

test('parses decimal major-unit strings into minor units', function () {
    expect(Money::parse('12.34', 'NGN'))->toBe(1234)
        ->and(Money::parse('0.05', 'NGN'))->toBe(5)
        ->and(Money::parse('.56', 'NGN'))->toBe(56)
        ->and(Money::parse('12.3', 'NGN'))->toBe(1230);
});

test('parses whole-unit strings into minor units', function () {
    expect(Money::parse('12', 'NGN'))->toBe(1200)
        ->and(Money::parse('1234', 'NGN'))->toBe(123400)
        ->and(Money::parse('1200', 'JPY'))->toBe(1200)
        ->and(Money::parse('1', 'BIF'))->toBe(1000);
});

test('parses zero for every exponent', function () {
    expect(Money::parse('0', 'NGN'))->toBe(0)
        ->and(Money::parse('0.00', 'NGN'))->toBe(0)
        ->and(Money::parse('0', 'JPY'))->toBe(0);
});

test('rejects precision beyond the currency exponent', function () {
    Money::parse('12.345', 'NGN');
})->throws(ValidationException::class);

test('rejects negative and malformed amounts', function () {
    expect(fn () => Money::parse('-1', 'NGN'))->toThrow(ValidationException::class);
    expect(fn () => Money::parse('1,00', 'NGN'))->toThrow(ValidationException::class);
    expect(fn () => Money::parse('01', 'NGN'))->toThrow(ValidationException::class);
});

test('rejects unknown currencies', function () {
    Money::parse('1.00', 'XYZ');
})->throws(ValidationException::class);

test('formats minor units into decimal strings', function () {
    expect(Money::format(1234, 'NGN'))->toBe('12.34')
        ->and(Money::format(5, 'NGN'))->toBe('0.05')
        ->and(Money::format(1230, 'NGN'))->toBe('12.30')
        ->and(Money::format(0, 'NGN'))->toBe('0.00')
        ->and(Money::format(-1234, 'NGN'))->toBe('-12.34');
});

test('formats exponent-zero currencies without a decimal point', function () {
    expect(Money::format(1200, 'JPY'))->toBe('1200')
        ->and(Money::format(0, 'JPY'))->toBe('0')
        ->and(Money::format(-5, 'JPY'))->toBe('-5');
});

test('adds, subtracts and compares integer minor units', function () {
    expect(Money::add(1200, 345))->toBe(1545)
        ->and(Money::subtract(1545, 345))->toBe(1200)
        ->and(Money::compare(1200, 1200, 'NGN'))->toBe(0)
        ->and(Money::compare(1201, 1200, 'NGN'))->toBe(1)
        ->and(Money::compare(1199, 1200, 'NGN'))->toBe(-1);
});
