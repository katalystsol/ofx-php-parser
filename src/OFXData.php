<?php

namespace KatalystSolutions\OFX;

readonly class OFXData
{
    /**
     * @param SignOn $signOn
     * @param AccountInfo[]|null $accountInfo
     * @param BankAccount[] $bankAccounts
     */
    public function __construct(
        public SignOn $signOn,
        public array|null $accountInfo,
        public array $bankAccounts,
    ) {
    }
}
