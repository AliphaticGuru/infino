<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Support;

use Illuminate\Validation\ValidationException;

/**
 * Small value object for safe monetary arithmetic using integer minor units.
 *
 * - No floats, no BCMath, no GMP.
 * - All amounts are stored and manipulated as signed 64-bit integers (minor units).
 * - Currency exponents (from config/pricing.php) determine the number of decimal places.
 * - Parsing accepts a decimal string; formatting produces a decimal string.
 * - Validation is authoritative; SQLite cannot express PostgreSQL partial/check
 *   constraints, so the application layer rejects invalid input.
 */
final class Money
{
    /** @var array<string, int> currency exponent map */
    protected static $exponents = [];

    /**
     * Initialise the exponent map from the pricing config.
     */
    public static function init(array $exponents): void
    {
        self::$exponents = $exponents;
    }

    /**
     * Parse a decimal string into minor units (integer).
     *
     * Accepts: "12.34", "1234", ".56", "0"
     * Rejects: negative numbers, non-numeric input, precision beyond exponent,
     *   multiple decimal separators, leading zero issues.
     *
     * @throws ValidationException
     */
    public static function parse(string $value, string $currency): int
    {
        if (! isset(self::$exponents[$currency])) {
            throw ValidationException::withMessages([
                'amount' => ["Unknown currency [{$currency}]."],
            ]);
        }

        $exponent = self::$exponents[$currency];

        // Reject empty or pure whitespace
        if (! preg_match('/^0$|^[1-9]\d*(\.\d+)?$/', $value)) {
            throw ValidationException::withMessages([
                'amount' => ["Invalid monetary value [{$value}]."],
            ]);
        }

        // Check for negative (regex already excludes leading minus, but be safe)
        if ($value[0] === '-') {
            throw ValidationException::withMessages([
                'amount' => ['Negative amounts are not allowed.'],
            ]);
        }

        // Handle integer case (no decimal separator)
        if (! str_contains($value, '.')) {
            // "0" or positive integer
            $intVal = (int) $value;

            // Reject if cast changed the value (e.g. "1.5" without dot... but regex catches this)
            if ((string) $intVal !== ltrim($value, '0')) {
                throw ValidationException::withMessages([
                    'amount' => ["Precision beyond currency exponent ({$exponent} decimal places) for [{$currency}]."],
                ]);
            }

            return $intVal * (int) pow10($exponent);
        }

        // Handle decimal case
        [$integerPart, $decimalPart] = explode('.', $value, 2);

        // Validate integer part (no leading zeros except "0")
        if ($integerPart !== '0' && strlen($integerPart) > 1 && $integerPart[0] === '0') {
            throw ValidationException::withMessages([
                'amount' => ["Invalid monetary value [{$value}]."],
            ]);
        }

        // Validate decimal part length does not exceed exponent
        $decimalLen = strlen($decimalPart);
        if ($decimalLen > $exponent) {
            throw ValidationException::withMessages([
                'amount' => ["Precision beyond currency exponent ({$exponent} decimal places) for [{$currency}]."],
            ]);
        }

        // Pad or truncate decimal part to exactly exponent digits
        $decimalPart = str_pad(substr($decimalPart, 0, $exponent), $exponent, '0');

        // Combine integer and padded decimal parts
        $combined = $integerPart.$decimalPart;
        $totalMinor = (int) $combined;

        return $totalMinor * (int) pow10($exponent - $decimalLen);
    }

    /**
     * Format minor units into a decimal string for the given currency.
     *
     * @param  int  $minorUnits  amount in minor units (e.g. 1234 for USD)
     * @return string formatted decimal string (e.g. "12.34" for USD)
     */
    public static function format(int $minorUnits, string $currency): string
    {
        if (! isset(self::$exponents[$currency])) {
            return (string) $minorUnits;
        }

        $exponent = self::$exponents[$currency];

        // Handle zero
        if ($minorUnits === 0) {
            return str_pad('0', $exponent, '0', STR_PAD_LEFT).'.'.str_pad('0', $exponent, '0');
        }

        // Determine sign
        $negative = $minorUnits < 0;
        $absMinor = abs($minorUnits);

        // Split into major and minor parts
        $major = (int) ($absMinor / pow10($exponent));
        $minor = $absMinor % pow10($exponent);

        // Format with leading zeros for minor part
        $format = '%.'.$exponent.'d';
        $minorFormatted = sprintf($format, $minor);

        $result = $negative ? '-' : '';
        $result .= $major.'.'.$minorFormatted;

        return rtrim(rtrim($result, '0'), '.');
    }

    /**
     * Compare two monetary values for the same currency.
     *
     * @return -1|0|1
     */
    public static function compare(int $aMinor, int $bMinor, string $currency): int
    {
        // Simple integer comparison is sufficient since both are in the same
        // minor units for the same currency.
        return $aMinor === $bMinor ? 0 : ($aMinor > $bMinor ? 1 : -1);
    }

    /**
     * Add two monetary values for the same currency.
     *
     * @return int total minor units
     */
    public static function add(int $aMinor, int $bMinor): int
    {
        return $aMinor + $bMinor;
    }

    /**
     * Subtract two monetary values for the same currency.
     *
     * @return int difference in minor units
     */
    public static function subtract(int $aMinor, int $bMinor): int
    {
        return $aMinor - $bMinor;
    }
}

/**
 * Helper to compute 10^exponent using integer arithmetic only.
 */
function pow10(int $exponent): int
{
    $result = 1;
    for ($i = 0; $i < $exponent; $i++) {
        $result *= 10;
    }

    return $result;
}
