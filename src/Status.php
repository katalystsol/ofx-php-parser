<?php

namespace KatalystSolutions\OFX;

class Status
{
    /**
     * @var array<string, string>
     */
    private static array $codes = [
        '0'       => 'Success',
        '2000'    => 'General error',
        '15000'   => 'Must change USERPASS',
        '15500'   => 'Signon invalid',
        '15501'   => 'Customer account already in use',
        '15502'   => 'USERPASS Lockout'
    ];

    public function __construct(
        public string $code,
        public string $severity,
        public string $message,
    ) {
    }

    /**
     * Get the associated code description
     *
     * @return string
     */
    public function codeDescription(): string
    {
        $code = $this->code;

        return array_key_exists($code, self::$codes) ? self::$codes[$code] : '';
    }
}
