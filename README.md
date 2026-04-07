# OFX PHP Parser
This project consists of a PHP parser for OFX (Open Financial Exchange) files, implemented using PHP 8.2. Our aim is to make the process of importing OFX files as straightforward and hassle-free as possible.

[![Build Status](https://scrutinizer-ci.com/g/kalisport-com/ofx-php-parser/badges/build.png?b=main)](https://scrutinizer-ci.com/g/kalisport-com/ofx-php-parser/build-status/main)
[![Latest Stable Version](https://img.shields.io/github/v/release/kalisport-com/ofx-php-parser.svg)](https://packagist.org/packages/kalisport/ofx-php-parser)
[![Code Coverage](https://scrutinizer-ci.com/g/kalisport-com/ofx-php-parser/badges/coverage.png?b=main)](https://scrutinizer-ci.com/g/kalisport/ofx-php-parser/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/kalisport-com/ofx-php-parser/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/kalisport/ofx-php-parser/?branch=master)
[![Downloads](https://img.shields.io/packagist/dt/kalisport/ofx-php-parser.svg)](https://packagist.org/packages/kalisport/ofx-php-parser)
[![Downloads](https://img.shields.io/badge/license-MIT-brightgreen.svg)](./LICENSE)


## Installation
Simply require the package using [Composer](https://getcomposer.org/):

```bash
$ composer require kalisport/ofx-php-parser
```

## Usage
This project primarily revolves around the `OFX` class in the `Kalisport\OFX` namespace. This class provides a static function `parse()` which is used to parse OFX data and return the parsed information. Here is a basic usage example:
```php
<?php

require 'vendor/autoload.php';

use KatalystSolutions\OFX\OFX;

try {
    // Load the OFX data
    $ofxData = file_get_contents('path_to_your_ofx_file.ofx');

    // Parse the OFX data
    $parsedData = OFX::parse($ofxData);

    // $parsedData is an instance of OFXData which gives you access to all parsed data

    // Access the sign-on status code
    $statusCode = $parsedData->signOn->status->code;

    // Accessing bank accounts data
    $bankAccounts = $parsedData->bankAccounts;
    foreach($bankAccounts as $account) {
        echo 'Account ID: ' .$account->accountNumber . PHP_EOL;
        echo 'Bank ID: ' .$account->routingNumber . PHP_EOL;

        // Loop through each transaction
        foreach ($account->statement->transactions as $transaction) {
            echo 'Transaction Type: ' . $transaction->type . PHP_EOL;
            echo 'Date: ' . $transaction->date . PHP_EOL;
            echo 'Amount: ' . $transaction->amount . PHP_EOL;
        }
    }

} catch (Exception $e) {
    echo 'An error occurred: ' . $e->getMessage();
}
```

## Acknowledgements

This library is an independent project; however, it draws significant inspiration from the work done on [endeken-com/ofx-php-parser](https://github.com/endeken-com/ofx-php-parser), which is itself a fork of [asgrim/ofxparser](https://github.com/asgrim/ofxparser), originally derived from [grimfor/ofxparser](https://github.com/grimfor/ofxparser).

We would like to express our appreciation to the developers of these projects for their valuable contributions. Our intention was not to create a direct fork, but rather to build upon their efforts and guide the library in a slightly different direction to better address our specific requirements.
