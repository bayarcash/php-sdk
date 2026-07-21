<?php

namespace Bayarcash\DuitNow;

class Dobw
{
    const CASA = '01';
    const CREDIT_CARD = '02';
    const EWALLET = '03';

    /*
     * Status Code
     */
    const STATUS_NEW = 0;
    const STATUS_PENDING = 1;
    const STATUS_FAILED = 2;
    const STATUS_SUCCESS = 3;
    const STATUS_CANCELLED = 4;

    protected static function getStatusLabels()
    {
        return [
            self::STATUS_NEW => 'New',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_SUCCESS => 'Successful',
            self::STATUS_FAILED => 'Failed',
        ];
    }

    public static function getStatusText(int $statusCode)
    {
        $statuses = self::getStatusLabels();
        return $statuses[$statusCode] ?? 'UNKNOWN STATUS';
    }
}
