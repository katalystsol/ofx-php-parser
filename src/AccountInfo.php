<?php

namespace KatalystSolutions\OFX;

class AccountInfo
{
    public function __construct(public string $description, public string $number)
    {
    }
}
