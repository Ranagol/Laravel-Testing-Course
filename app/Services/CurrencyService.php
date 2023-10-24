<?php

namespace App\Services;

use App\Exceptions\CurrencyRateNotFoundException;
use Illuminate\Support\Arr;

/**
 * In our app we display a list of products with their price in USD. But, we want anoother column,
 * with prices in EUR. So we need something that will convert the USD price to EUR price.
 * That is: app/Services/CurrencyService.php
 */
class CurrencyService
{
    /**
     * 1 $ is 0.98 eur. Also, there is place for other currencies.
     */
    const RATES = [
        'usd' => [
            'eur' => 0.98
        ]
    ];

    /**
     * Converts $ to EUR.
     */
    public function convert(float $amount, string $currencyFrom, string $currencyTo): float
    {
        //In case if somebody want to use for example CHF, and CHF is not defined in self:RATES
        if (! Arr::exists(self::RATES, $currencyFrom)) {
            throw new CurrencyRateNotFoundException('Currency rate not found');
        }

        //Example ['usd']['eur'] would be 0.98, but if it is not defined in self:RATES than it is 0.
        $rate = self::RATES[$currencyFrom][$currencyTo] ?? 0;

        return round($amount * $rate, 2);
    }

}
