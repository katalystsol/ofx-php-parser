<?php

namespace KatalystSolutions\OFX;

use DateTime;

class SignOn
{
    public function __construct(
        public Status $status,
        public DateTime $date,
        public string $language,
        public Institute $institute,
    ) {
    }
}
