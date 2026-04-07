<?php

namespace KatalystSolutions\OFX;

use DateTime;
use DateTimeZone;
use Exception;
use SimpleXMLElement;

/**
 * Class OFX
 *
 * Parses OFX data and converts it into structured objects.
 */
class OFX
{
    /**
     * Parses OFX data and returns a structured OFXData object or null on failure.
     *
     * @param string $ofxData The OFX data as a string.
     * @return OFXData|null
     * @throws Exception
     */
    public static function parse(string $ofxData): ?OFXData
    {
        // Normalize OFX into a SimpleXML object
        $xml = OFXUtils::normalizeOfx($ofxData);

        if ($xml === false) {
            return null;
        }

        $signOn       = self::parseSignOn($xml->SIGNONMSGSRSV1->SONRS);
        $accountInfo  = self::parseAccountInfo($xml->SIGNUPMSGSRSV1->ACCTINFOTRNRS);
        $bankAccounts = [];

        // Parse bank accounts
        if (isset($xml->BANKMSGSRSV1)) {
            foreach ($xml->BANKMSGSRSV1->STMTTRNRS as $stmtTrnrs) {
                foreach ($stmtTrnrs->STMTRS as $stmt) {
                    $uuid = (string) $stmtTrnrs->TRNUID;
                    $bankAccounts[] = self::parseBankAccount($uuid, $stmt);
                }
            }
        }

        // Parse credit card accounts
        if (isset($xml->CREDITCARDMSGSRSV1)) {
            foreach ($xml->CREDITCARDMSGSRSV1->CCSTMTTRNRS as $ccStmtTrnrs) {
                $uuid = (string) $ccStmtTrnrs->TRNUID;
                $bankAccounts[] = self::parseCreditAccount($uuid, $ccStmtTrnrs);
            }
        }

        return new OFXData($signOn, $accountInfo, $bankAccounts);
    }

    /**
     * Parses the sign-on section of the OFX.
     *
     * @param SimpleXMLElement $xml
     * @return SignOn
     * @throws Exception
     */
    protected static function parseSignOn(SimpleXMLElement $xml): SignOn
    {
        $status    = self::parseStatus($xml->STATUS);
        $dateTime  = self::parseDate((string)$xml->DTSERVER);
        $language  = (string)$xml->LANGUAGE;
        $institute = self::parseInstitute($xml->FI);

        return new SignOn($status, $dateTime, $language, $institute);
    }

    /**
     * Parses the financial institution information.
     *
     * @param SimpleXMLElement $xml
     * @return Institute
     */
    protected static function parseInstitute(SimpleXMLElement $xml): Institute
    {
        $name = (string) $xml->ORG;
        $id   = (string) $xml->FID;

        return new Institute($id, $name);
    }

    /**
     * Parses the status block of OFX.
     *
     * @param SimpleXMLElement $xml
     * @return Status
     */
    protected static function parseStatus(SimpleXMLElement $xml): Status
    {
        $code     = (string) $xml->CODE;
        $severity = (string) $xml->SEVERITY;
        $message  = (string) $xml->MESSAGE;

        return new Status($code, $severity, $message);
    }

    /**
     * Parses an OFX date string and returns a DateTime object.
     * Handles milliseconds and OFX timezone offsets (e.g., [+2:CEST], [-5:EST]).
     *
     * @param string $dateString
     * @return DateTime
     * @throws Exception
     */
    protected static function parseDate(string $dateString): DateTime
    {
        // Remove milliseconds if present
        $dateString = preg_replace('/\.\d+/', '', $dateString) ?? $dateString;
        $dateString = preg_replace('/\[0:GMT]/', '', $dateString) ?? $dateString;

        // Extract offset from patterns like [+2:CEST] or [-5:EST]
        $offset = null;

        if (preg_match('/\[(?<sign>[+-])(?<hh>\d{1,2})(?::[A-Z]{2,5})?\]/', $dateString, $matches)) {
            $sign       = $matches['sign'] === '-' ? '-' : '+';
            $hh         = str_pad($matches['hh'], 2, '0', STR_PAD_LEFT);
            $offset     = "{$sign}{$hh}:00";
            $dateString = preg_replace('/\[[^\]]+\]/', '', $dateString) ?? $dateString;
        }

        try {
            $dateTime = new DateTime($dateString, new DateTimeZone('UTC'));

            if ($offset !== null) {
                $dateTime->setTimezone(new DateTimeZone($offset));
            }
        } catch (Exception $e) {
            throw new Exception("Failed to parse date string: {$dateString}", 0, $e);
        }

        return $dateTime;
    }

    /**
     * Parses a bank account statement.
     *
     * @param string $uuid
     * @param SimpleXMLElement $xml
     * @return BankAccount
     * @throws Exception
     */
    private static function parseBankAccount(string $uuid, SimpleXMLElement $xml): BankAccount
    {
        return self::parseAccountFromNode(
            uuid: $uuid,
            root: $xml,
            stmtRoot: $xml,
            acctNode: $xml->BANKACCTFROM
        );
    }

    /**
     * Parses a credit card account statement.
     *
     * @param string $uuid
     * @param SimpleXMLElement $xml
     * @return BankAccount
     * @throws Exception
     */
    private static function parseCreditAccount(string $uuid, SimpleXMLElement $xml): BankAccount
    {
        $stmtRoot = $xml->CCSTMTRS ?? $xml;
        $acctNode = isset($stmtRoot->CCACCTFROM) ? $stmtRoot->CCACCTFROM : $stmtRoot->BANKACCTFROM;

        return self::parseAccountFromNode(
            $uuid,
            $xml,
            $stmtRoot,
            $acctNode
        );
    }

    /**
     * Generic account parser for both bank and credit accounts.
     *
     * @param string $uuid
     * @param SimpleXMLElement $root
     * @param SimpleXMLElement $stmtRoot
     * @param SimpleXMLElement $acctNode
     * @return BankAccount
     * @throws Exception
     */
    private static function parseAccountFromNode(
        string $uuid,
        SimpleXMLElement $root,
        SimpleXMLElement $stmtRoot,
        SimpleXMLElement $acctNode
    ): BankAccount {
        $accountNumber = (string) $acctNode->ACCTID;
        $accountType   = (string) $acctNode->ACCTTYPE;
        $agencyNumber  = (string) $acctNode->BRANCHID;
        $routingNumber = (string) $acctNode->BANKID;
        $balance       = (float) $stmtRoot->LEDGERBAL->BALAMT;
        $balanceDate   = self::parseDate((string) $stmtRoot->LEDGERBAL->DTASOF);
        $statement     = self::parseStatement($root);

        return new BankAccount(
            $accountNumber,
            $accountType,
            $agencyNumber,
            $routingNumber,
            $balance,
            $balanceDate,
            $uuid,
            $statement
        );
    }

    /**
     * Parses a statement into a Statement object with transactions.
     *
     * @param SimpleXMLElement $xml
     * @return Statement
     * @throws Exception
     */
    private static function parseStatement(SimpleXMLElement $xml): Statement
    {
        $currency  = (string) $xml->CURDEF;
        $startDate = self::parseDate((string) $xml->BANKTRANLIST->DTSTART);
        $endDate   = self::parseDate((string) $xml->BANKTRANLIST->DTEND);

        $transactions = [];
        foreach ($xml->BANKTRANLIST->STMTTRN as $t) {
            $type = (string) $t->TRNTYPE;
            $date = self::parseDate((string) $t->DTPOSTED);

            $userDate = null;
            if (isset($t->DTUSER) && (string) $t->DTUSER !== '') {
                $userDate = self::parseDate((string) $t->DTUSER);
            }

            $amount   = (float) $t->TRNAMT;
            $uniqueId = (string) $t->FITID;
            $name     = rtrim((string) $t->NAME);
            $memo     = rtrim((string) $t->MEMO);
            $sic      = (string) $t->SIC;
            $checkNumber = (string) $t->CHECKNUM;

            $transactions[] = new Transaction(
                $type,
                $amount,
                $date,
                $userDate,
                $uniqueId,
                $name,
                $memo,
                $sic,
                $checkNumber
            );
        }

        return new Statement($currency, $transactions, $startDate, $endDate);
    }

    /**
     * Parses account information section (ACCTINFO).
     *
     * @param SimpleXMLElement|null $xml
     * @return null|AccountInfo[]
     */
    private static function parseAccountInfo(?SimpleXMLElement $xml = null): ?array
    {
        if ($xml === null || ! isset($xml->ACCTINFO)) {
            return null;
        }

        $accounts = [];
        foreach ($xml->ACCTINFO as $account) {
            $accounts[] = new AccountInfo(
                (string) $account->DESC,
                (string) $account->ACCTID
            );
        }

        return $accounts;
    }
}
