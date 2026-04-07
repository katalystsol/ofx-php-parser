<?php

namespace KatalystSolutions\OFX;

use DateTime;

class Statement
{
    public function __construct(
        public string $currency,
        public array $transactions,
        public DateTime $startDate,
        public DateTime $endDate,
    ) {
    }
}
