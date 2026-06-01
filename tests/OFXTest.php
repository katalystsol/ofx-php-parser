<?php

use KatalystSolutions\OFX\OFX;
use PHPUnit\Framework\TestCase;

class OFXTest extends TestCase
{
    private string $ofxTestFilesDir = __DIR__ . '/fixtures';

    /**
     * @throws Exception
     */
    public function testMultipleAccountsXML()
    {
        $filePath   = $this->ofxTestFilesDir . '/ofx-multiple-accounts-xml.ofx';
        $ofxContent = file_get_contents($filePath);

        $parsedData = OFX::parse($ofxContent);

        var_dump($parsedData);

        $this->assertNotEmpty($parsedData);
    }

    /**
     * @throws Exception
     */
    public function testOfxData()
    {
        $filePath = $this->ofxTestFilesDir . '/ofxdata.ofx';
        $ofxContent = file_get_contents($filePath);

        $parsedData = OFX::parse($ofxContent);

        var_dump($parsedData);

        $this->assertNotEmpty($parsedData);
    }

    /**
     * @throws Exception
     */
    public function testOneLineSgmlOfx()
    {
        $ofxContent = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE
<OFX><SIGNONMSGSRSV1><SONRS><STATUS><CODE>0<SEVERITY>INFO</STATUS><DTSERVER>20240102000000<LANGUAGE>ENG</SONRS></SIGNONMSGSRSV1><BANKMSGSRSV1><STMTTRNRS><TRNUID>0<STATUS><CODE>0<SEVERITY>INFO</STATUS><STMTRS><CURDEF>USD<BANKACCTFROM><BANKID>123456789<ACCTID>987654321<ACCTTYPE>CHECKING</BANKACCTFROM><BANKTRANLIST><DTSTART>20240101000000<DTEND>20240102000000<STMTTRN><TRNTYPE>DEBIT<DTPOSTED>20240101120000<TRNAMT>-10.00<FITID>REF123<NAME>Vendor & Sons<MEMO>One line OFX Transaction</STMTTRN><STMTTRN><TRNTYPE>CREDIT<DTPOSTED>20240102120000<TRNAMT>25.00<FITID>REF124<NAME>Deposit</STMTTRN></BANKTRANLIST><LEDGERBAL><BALAMT>1015.00<DTASOF>20240102000000</LEDGERBAL></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>
OFX;

        $parsedData = OFX::parse($ofxContent);

        $transactions = $parsedData->bankAccounts[0]->statement->transactions;

        $this->assertCount(2, $transactions);
        $this->assertSame('Vendor & Sons', $transactions[0]->name);
        $this->assertSame('One line OFX Transaction', $transactions[0]->memo);
        $this->assertSame(-10.00, $transactions[0]->amount);
        $this->assertSame('REF123', $transactions[0]->uniqueId);
        $this->assertSame('Deposit', $transactions[1]->name);
        $this->assertSame(25.00, $transactions[1]->amount);
        $this->assertSame('REF124', $transactions[1]->uniqueId);
    }

    /**
     * @throws Exception
     */
    public function testSgmlOfxIgnoresTrailingContentAfterRoot()
    {
        $ofxContent = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE
<OFX><SIGNONMSGSRSV1><SONRS><STATUS><CODE>0<SEVERITY>INFO</STATUS><DTSERVER>20240102000000<LANGUAGE>ENG</SONRS></SIGNONMSGSRSV1><BANKMSGSRSV1><STMTTRNRS><TRNUID>0<STATUS><CODE>0<SEVERITY>INFO</STATUS><STMTRS><CURDEF>USD<BANKACCTFROM><BANKID>123456789<ACCTID>987654321<ACCTTYPE>CHECKING</BANKACCTFROM><BANKTRANLIST><DTSTART>20240101000000<DTEND>20240102000000<STMTTRN><TRNTYPE>CREDIT<DTPOSTED>20240102120000<TRNAMT>25.00<FITID>REF124<NAME>Deposit</STMTTRN></BANKTRANLIST><LEDGERBAL><BALAMT>1015.00<DTASOF>20240102000000</LEDGERBAL></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>TRAILING BANK FOOTER
OFX;

        $parsedData = OFX::parse($ofxContent);

        $transactions = $parsedData->bankAccounts[0]->statement->transactions;

        $this->assertCount(1, $transactions);
        $this->assertSame('Deposit', $transactions[0]->name);
    }

    /**
     * @throws Exception
     */
    public function testXmlOfxIgnoresTrailingContentAfterRoot()
    {
        $ofxContent = <<<'OFX'
<?xml version="1.0" encoding="UTF-8"?>
<?OFX OFXHEADER="200" VERSION="203" SECURITY="NONE" OLDFILEUID="NONE" NEWFILEUID="NONE"?>
<OFX><SIGNONMSGSRSV1><SONRS><STATUS><CODE>0</CODE><SEVERITY>INFO</SEVERITY></STATUS><DTSERVER>20240102000000</DTSERVER><LANGUAGE>ENG</LANGUAGE></SONRS></SIGNONMSGSRSV1><BANKMSGSRSV1><STMTTRNRS><TRNUID>0</TRNUID><STATUS><CODE>0</CODE><SEVERITY>INFO</SEVERITY></STATUS><STMTRS><CURDEF>USD</CURDEF><BANKACCTFROM><BANKID>123456789</BANKID><ACCTID>987654321</ACCTID><ACCTTYPE>CHECKING</ACCTTYPE></BANKACCTFROM><BANKTRANLIST><DTSTART>20240101000000</DTSTART><DTEND>20240102000000</DTEND><STMTTRN><TRNTYPE>CREDIT</TRNTYPE><DTPOSTED>20240102120000</DTPOSTED><TRNAMT>25.00</TRNAMT><FITID>REF124</FITID><NAME>Deposit</NAME></STMTTRN></BANKTRANLIST><LEDGERBAL><BALAMT>1015.00</BALAMT><DTASOF>20240102000000</DTASOF></LEDGERBAL></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>TRAILING BANK FOOTER
OFX;

        $parsedData = OFX::parse($ofxContent);

        $transactions = $parsedData->bankAccounts[0]->statement->transactions;

        $this->assertCount(1, $transactions);
        $this->assertSame('Deposit', $transactions[0]->name);
    }

    /**
     * @throws Exception
     */
    public function testSgmlHeaderWithXmlBalancedBody()
    {
        $ofxContent = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE
<OFX><SIGNONMSGSRSV1><SONRS><STATUS><CODE>0</CODE><SEVERITY>INFO</SEVERITY></STATUS><DTSERVER>20250308151855.302[-6]</DTSERVER><LANGUAGE>ENG</LANGUAGE><FI><ORG>Bank</ORG><FID>123</FID></FI><INTU.BID>123</INTU.BID></SONRS></SIGNONMSGSRSV1><BANKMSGSRSV1><STMTTRNRS><TRNUID>0</TRNUID><STATUS><CODE>0</CODE><SEVERITY>INFO</SEVERITY></STATUS><STMTRS><CURDEF>USD</CURDEF><BANKACCTFROM><BANKID>123456789</BANKID><ACCTID>987654321</ACCTID><ACCTTYPE>CHECKING</ACCTTYPE></BANKACCTFROM><BANKTRANLIST><DTSTART>20240101000000</DTSTART><DTEND>20240102000000</DTEND><STMTTRN><TRNTYPE>CREDIT</TRNTYPE><DTPOSTED>20240102120000</DTPOSTED><TRNAMT>25.00</TRNAMT><FITID>REF124</FITID><NAME>Deposit</NAME></STMTTRN></BANKTRANLIST><LEDGERBAL><BALAMT>1015.00</BALAMT><DTASOF>20240102000000</DTASOF></LEDGERBAL></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>
OFX;

        $parsedData = OFX::parse($ofxContent);

        $transactions = $parsedData->bankAccounts[0]->statement->transactions;

        $this->assertCount(1, $transactions);
        $this->assertSame('Deposit', $transactions[0]->name);
    }
}
