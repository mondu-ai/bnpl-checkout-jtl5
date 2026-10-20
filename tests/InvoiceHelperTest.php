<?php

/**
 * Framework-free unit test for InvoiceHelper::parseAmountToCents().
 *
 * This plugin has no PHPUnit setup, so this is a self-contained assertion script.
 * Run:  php tests/InvoiceHelperTest.php   (exit code 0 = all passed)
 *
 * parseAmountToCents() is pure (no JTL/DB/HTTP dependencies), so it is included and
 * exercised directly. It converts a JTL Wawi gross-amount placeholder (shop currency,
 * German or English formatting) into integer cents for the Mondu credit-note payload.
 */

require __DIR__ . '/../Src/Helpers/InvoiceHelper.php';

use Plugin\MonduPayment\Src\Helpers\InvoiceHelper;

$cases = [
    // input            => expected cents
    ['119.00',   11900],   // english decimal
    ['119,00',   11900],   // german decimal
    ['1.234,56', 123456],  // german grouping + decimal
    ['1,234.56', 123456],  // english grouping + decimal
    ['19,00',    1900],
    ['119',      11900],   // integer euros
    ['12,5',     1250],    // single decimal digit
    ['0',        0],
    ['0,00',     0],
    ['  49,99 ', 4999],    // surrounding whitespace
    ['1.000',    100],     // ambiguous lone dot -> treated as decimal separator => 1.000 EUR
    ['',         null],    // empty
    ['abc',      null],    // non-numeric
    [null,       null],    // null
];

$failures = 0;
$passed = 0;

foreach ($cases as [$input, $expected]) {
    $actual = InvoiceHelper::parseAmountToCents($input);
    $ok = $actual === $expected;
    if (!$ok) {
        $failures++;
        printf("FAIL  %-12s expected=%s got=%s\n", json_encode($input), var_export($expected, true), var_export($actual, true));
    } else {
        $passed++;
        printf("ok    %-12s => %s\n", json_encode($input), var_export($actual, true));
    }
}

echo "\n{$passed} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
