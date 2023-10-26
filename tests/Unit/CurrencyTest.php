<?php

namespace Tests\Unit;

use App\Exceptions\CurrencyRateNotFoundException;
use App\Services\CurrencyService;
use PHPUnit\Framework\TestCase;

/**
 * In our app we display a list of products with their price in USD. But, we want anoother column,
 * with prices in EUR. So we need something that will convert the USD price to EUR price.
 * That is: app/Services/CurrencyService.php
 *
 * Here we test 3 cases that could happen.
 */
class CurrencyTest extends TestCase
{
    // /**
    //  * 100 $ should be 98 eur
    //  *
    //  * @return void
    //  */
    // public function test_convert_usd_to_eur_successful()
    // {
    //     $this->assertEquals(
    //         98,
    //         (new CurrencyService())->convert(100, 'usd', 'eur')//100 $ should be 98 eur
    //     );
    // }

    // /**
    //  * It should return 0, because gbp is simply not defined nowhere.
    //  */
    // public function test_convert_usd_to_gbp_returns_zero()
    // {
    //     $this->assertEquals(
    //         0,
    //         (new CurrencyService())->convert(100, 'usd', 'gbp')
    //     );
    // }

    // /**
    //  * It should be an exception, because no gbp is defined. We also set, that in this case an
    //  * exception should be thrown. And this is what we test here.
    //  */
    // public function test_convert_gbp_to_usd_throws_exception()
    // {
    //     $this->expectException(CurrencyRateNotFoundException::class);

    //     $this->assertEquals(0, (new CurrencyService())->convert(100, 'gbp', 'usd'));
    // }
}
