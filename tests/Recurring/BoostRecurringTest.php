<?php

namespace Webimpian\BayarcashSdk\Tests\Recurring;

use PHPUnit\Framework\TestCase;
use Webimpian\BayarcashSdk\BoostRecurring;

class BoostRecurringTest extends TestCase
{
    public function test_status_constants_and_labels(): void
    {
        $this->assertSame('canceled', BoostRecurring::STATUS_CANCELED);
        $this->assertSame('Active', BoostRecurring::getStatusText('active'));
        $this->assertSame('UNKNOWN STATUS', BoostRecurring::getStatusText('nope'));
    }

    public function test_terminal_status(): void
    {
        $this->assertTrue(BoostRecurring::isTerminalStatus('canceled'));
        $this->assertTrue(BoostRecurring::isTerminalStatus('terminated'));
        $this->assertFalse(BoostRecurring::isTerminalStatus('active'));
        $this->assertFalse(BoostRecurring::isTerminalStatus('paused'));
    }

    public function test_type_and_interval_constants(): void
    {
        $this->assertSame('subscription', BoostRecurring::TYPE_SUBSCRIPTION);
        $this->assertSame('installment', BoostRecurring::TYPE_INSTALLMENT);
        $this->assertSame('on_demand', BoostRecurring::TYPE_ON_DEMAND);
        $this->assertSame('P1M', BoostRecurring::INTERVAL_MONTHLY);
    }
}
