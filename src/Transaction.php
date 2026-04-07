<?php

namespace KatalystSolutions\OFX;

use DateTime;

class Transaction
{
    private static array $types = [
        'CREDIT' => 'Generic credit',
        'DEBIT' => 'Generic debit',
        'INT' => 'Interest earned or paid',
        'DIV' => 'Dividend',
        'FEE' => 'FI fee',
        'SRVCHG' => 'Service charge',
        'DEP' => 'Deposit',
        'ATM' => 'ATM debit or credit',
        'POS' => 'Point of sale debit or credit',
        'XFER' => 'Transfer',
        'CHECK' => 'Cheque',
        'PAYMENT' => 'Electronic payment',
        'CASH' => 'Cash withdrawal',
        'DIRECTDEP' => 'Direct deposit',
        'DIRECTDEBIT' => 'Merchant initiated debit',
        'REPEATPMT' => 'Repeating payment/standing order',
        'OTHER' => 'Other',
    ];

    public function __construct(
        public string $type,
        public float $amount,
        public DateTime $date,
        public ?DateTime $userInitiatedDate,
        public string $uniqueId,
        public string $name,
        public string $memo,
        public string $sic,
        public string $checkNumber,
    ) {
    }

    /**
     * Get the associated type description
     */
    public function typeDescription(): string
    {
        $type = $this->type;

        return array_key_exists($type, self::$types) ? self::$types[$type] : '';
    }
}
