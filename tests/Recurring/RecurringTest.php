<?php

namespace Bayarcash\Tests\Recurring;

use PHPUnit\Framework\TestCase;
use Bayarcash\Recurring;

class RecurringTest extends TestCase
{
    public function test_status_constants_and_labels(): void
    {
        $this->assertSame('canceled', Recurring::STATUS_CANCELED);
        $this->assertSame('Active', Recurring::getStatusText('active'));
        $this->assertSame('UNKNOWN STATUS', Recurring::getStatusText('nope'));
    }

    public function test_terminal_status(): void
    {
        $this->assertTrue(Recurring::isTerminalStatus('canceled'));
        $this->assertTrue(Recurring::isTerminalStatus('terminated'));
        $this->assertFalse(Recurring::isTerminalStatus('active'));
        $this->assertFalse(Recurring::isTerminalStatus('paused'));
        $this->assertFalse(Recurring::isTerminalStatus('pending'));
    }

    public function test_ended_statuses_the_api_reports_are_known_and_terminal(): void
    {
        // The console's vocabulary (decision 2026-10-12): `failed` is an enrolment whose
        // card authorization failed; `completed` is an installment plan whose every
        // instalment was collected. Both are over - no polling, no pause/cancel.
        $this->assertSame('failed', Recurring::STATUS_FAILED);
        $this->assertSame('completed', Recurring::STATUS_COMPLETED);
        $this->assertSame('Failed', Recurring::getStatusText('failed'));
        $this->assertSame('Completed', Recurring::getStatusText('completed'));
        $this->assertTrue(Recurring::isTerminalStatus('failed'));
        $this->assertTrue(Recurring::isTerminalStatus('completed'));
        $this->assertSame('expired', Recurring::STATUS_EXPIRED);
        $this->assertTrue(Recurring::isTerminalStatus('expired'));
        $this->assertSame('Expired', Recurring::getStatusText('expired'));
    }

    public function test_type_and_interval_constants(): void
    {
        $this->assertSame('subscription', Recurring::TYPE_SUBSCRIPTION);
        $this->assertSame('installment', Recurring::TYPE_INSTALLMENT);
        $this->assertSame('on_demand', Recurring::TYPE_ON_DEMAND);
        // The API accepts and returns only these words, never ISO durations.
        $this->assertSame('day', Recurring::INTERVAL_DAILY);
        $this->assertSame('week', Recurring::INTERVAL_WEEKLY);
        $this->assertSame('month', Recurring::INTERVAL_MONTHLY);
        $this->assertSame('year', Recurring::INTERVAL_YEARLY);
    }
}
