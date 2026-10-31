<?php
/**
 * Settlement FX rates (USD base), refreshed nightly by the treasury batch.
 */
function fx_rate(string $currency): float
{
    static $table = ['USD' => 1.0, 'EUR' => 1.0853, 'GBP' => 1.2718, 'CAD' => 0.7342];
    if (!isset($table[$currency])) {
        throw new UnexpectedValueException("Unsupported settlement currency '{$currency}'");
    }
    return $table[$currency];
}
