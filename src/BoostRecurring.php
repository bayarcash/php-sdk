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
     * On-demand payment statuses (PaymentResource->status)
     */
    const PAYMENT_SUCCEEDED = 'succeeded';
    const PAYMENT_FAILED = 'failed';
    const PAYMENT_PROCESSING = 'processing';
    const PAYMENT_CANCELED = 'canceled';

    /*
     * Common ISO-8601 billing intervals (plan / installment 'interval')
     */
    const INTERVAL_DAILY = 'P1D';
    const INTERVAL_WEEKLY = 'P1W';
    const INTERVAL_MONTHLY = 'P1M';
    const INTERVAL_YEARLY = 'P1Y';

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
