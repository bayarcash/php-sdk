<?php

namespace Webimpian\BayarcashSdk;

class BoostRecurring
{
    /*
     * Recurring resource types (RecurringIntentResource->recurringType)
     */
    const TYPE_SUBSCRIPTION = 'subscription';
    const TYPE_INSTALLMENT = 'installment';
    const TYPE_ON_DEMAND = 'on_demand';

    /*
     * Subscription / Installment lifecycle statuses (note: American spelling 'canceled')
     */
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_CANCELED = 'canceled';
    const STATUS_TERMINATED = 'terminated';

    /*
     * Billing intervals (plan / installment 'interval') — the only values the API
     * accepts and returns. Charge statuses are transaction codes: see Fpx::STATUS_*.
     */
    const INTERVAL_DAILY = 'day';
    const INTERVAL_WEEKLY = 'week';
    const INTERVAL_MONTHLY = 'month';
    const INTERVAL_YEARLY = 'year';

    protected static function getStatusLabels()
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PAUSED => 'Paused',
            self::STATUS_CANCELED => 'Canceled',
            self::STATUS_TERMINATED => 'Terminated',
        ];
    }

    public static function getStatusText($status)
    {
        $statuses = self::getStatusLabels();

        return $statuses[$status] ?? 'UNKNOWN STATUS';
    }

    public static function isTerminalStatus($status): bool
    {
        return in_array($status, [self::STATUS_CANCELED, self::STATUS_TERMINATED], true);
    }
}
