<?php

namespace Bayarcash;

class FpxDirectDebit
{
    /*
     * Application Type
     */
    const ENROLMENT = '01';
    const MAINTENANCE = '02';
    const TERMINATION = '03';

    /*
     * Buyer ID Type
     */
    const NRIC = 1;
    const OLD_IC = 2;
    const PASSPORT = 3;
    const BUSINESS_REGISTRATION = 4;
    const OTHERS = 5;

    /*
     * Frequency Mode
     */
    const MODE_DAILY = 'DL';
    const MODE_WEEKLY = 'WK';
    const MODE_MONTHLY = 'MT';
    const MODE_YEARLY = 'YR';

    /*
     * Status Code
     */
    const STATUS_NEW = 0;
    const STATUS_WAITING_APPROVAL = 1;
    const STATUS_FAILED_BANK_VERIFICATION = 2;
    const STATUS_ACTIVE = 3;
    const STATUS_TERMINATED = 4;
    const STATUS_APPROVED = 5;
    const STATUS_REJECTED = 6;
    const STATUS_CANCELLED = 7;
    const STATUS_ERROR = 8;

    protected static function getStatusLabels()
    {
        return [
            self::STATUS_NEW => 'New',
            self::STATUS_WAITING_APPROVAL => 'Waiting Approval',
            self::STATUS_FAILED_BANK_VERIFICATION => 'Bank Verification Failed',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_ERROR => 'Error',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_TERMINATED => 'Terminated',
        ];
    }

    public static function getStatusText(int $statusCode)
    {
        $statuses = self::getStatusLabels();
        return $statuses[$statusCode] ?? 'UNKNOWN STATUS';
    }

    public static function getApplicationTypeText($applicationType)
    {
        switch ($applicationType) {
            case self::ENROLMENT:
                return 'Enrollment';
            case self::MAINTENANCE:
                return 'Maintenance';
            case self::TERMINATION:
                return 'Termination';
        }
    }

    public static function getFrequencyModeText($frequencyModeCode)
    {
        switch ($frequencyModeCode) {
            case self::MODE_DAILY:
                return 'Daily';
            case self::MODE_WEEKLY:
                return 'Weekly';
            case self::MODE_MONTHLY:
                return 'Monthly';
            case self::MODE_YEARLY:
                return 'Yearly';
        }
    }
}
