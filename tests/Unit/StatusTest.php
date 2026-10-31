<?php

namespace Bayarcash\Tests\Unit;

use Bayarcash\DuitNow\Dobw;
use Bayarcash\Fpx;
use Bayarcash\FpxDirectDebit;
use PHPUnit\Framework\TestCase;

class StatusTest extends TestCase
{
    public function test_payment_statuses_match_the_gateway(): void
    {
        $this->assertSame([0, 1, 2, 3, 4, 5], [Fpx::STATUS_NEW, Fpx::STATUS_PENDING, Fpx::STATUS_FAILED, Fpx::STATUS_SUCCESS, Fpx::STATUS_CANCELLED, Fpx::STATUS_ABANDONED]);
        $this->assertSame('Abandoned', Fpx::getStatusText(5));
        $this->assertSame('Abandoned', Dobw::getStatusText(Dobw::STATUS_ABANDONED));
    }

    public function test_mandate_statuses_match_the_gateway(): void
    {
        $this->assertSame(9, FpxDirectDebit::STATUS_EXPIRED);
        $this->assertSame(10, FpxDirectDebit::STATUS_INACTIVE);
        $this->assertSame('Expired', FpxDirectDebit::getStatusText(9));
        $this->assertSame('Inactive', FpxDirectDebit::getStatusText(10));

        foreach (range(0, 10) as $code) {
            $this->assertNotSame('UNKNOWN STATUS', FpxDirectDebit::getStatusText($code));
        }
    }

    public function test_an_unknown_code_is_named_as_such(): void
    {
        $this->assertSame('UNKNOWN STATUS', FpxDirectDebit::getStatusText(11));
        $this->assertSame('UNKNOWN STATUS', Fpx::getStatusText(6));
    }
}
