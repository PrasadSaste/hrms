<?php

namespace App\Support;

/**
 * The shapes a bank will accept a salary payment file in.
 *
 * A bank file is not the payroll register. The register is a report somebody
 * reads; this is a file a bank's upload screen parses, so the column order is
 * fixed, the headings are the bank's own words and an amount is 1234.50 rather
 * than ₹1,234.50. Getting one column wrong is not a formatting complaint — the
 * upload is rejected, or worse, accepted with the money in the wrong place.
 *
 * Every layout is written from one vocabulary of fields, listed in FIELDS and
 * resolved by `App\Services\Bank\BankFileService::value()`, so a new bank is a
 * column list here and nothing else. What each layout produces is shown on the
 * screen before the file is downloaded, because these specifications are
 * revised by the banks from time to time and the person paying the salaries is
 * the only one who can compare it against the template their bank sent them.
 */
class BankFormats
{
    /**
     * Every column any layout can ask for.
     *
     * The label is what the person choosing a format is shown; the heading is
     * what the bank expects to read in the file, where the layout writes one.
     */
    public const FIELDS = [
        'serial' => 'Row number',
        'transaction_type' => 'Payment type (NEFT, RTGS, IMPS or an internal transfer)',
        'debit_account' => 'The company account the money leaves',
        'beneficiary_name' => 'Employee name as the bank holds it',
        'beneficiary_account' => 'Employee account number',
        'ifsc' => 'Employee branch IFSC',
        'bank_name' => 'Employee bank',
        'amount' => 'Net pay',
        'value_date' => 'The date the payment should be made',
        'narration' => 'What the employee sees on their statement',
        'reference' => 'The payroll reference, for reconciliation',
        'employee_code' => 'Employee code',
        'email' => 'Employee email, where the bank sends an advice',
        'mobile' => 'Employee mobile',
    ];

    /**
     * A payment above this goes by RTGS rather than NEFT.
     *
     * Two lakh is the floor RTGS has had since it was opened to retail
     * payments; below it the banks route salary files over NEFT.
     */
    public const RTGS_THRESHOLD = 200000.0;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'generic' => [
                'label' => 'Generic CSV',
                'description' => 'Every field, plainly headed. Start here: most upload screens let you map columns, and this one shows you what the system holds.',
                'extension' => 'csv',
                'delimiter' => ',',
                'header' => true,
                'date_format' => 'd/m/Y',
                'columns' => [
                    ['field' => 'serial', 'heading' => 'Sr No'],
                    ['field' => 'employee_code', 'heading' => 'Employee Code'],
                    ['field' => 'beneficiary_name', 'heading' => 'Beneficiary Name'],
                    ['field' => 'beneficiary_account', 'heading' => 'Account Number'],
                    ['field' => 'ifsc', 'heading' => 'IFSC'],
                    ['field' => 'bank_name', 'heading' => 'Bank'],
                    ['field' => 'amount', 'heading' => 'Amount'],
                    ['field' => 'transaction_type', 'heading' => 'Payment Type'],
                    ['field' => 'debit_account', 'heading' => 'Debit Account'],
                    ['field' => 'value_date', 'heading' => 'Value Date'],
                    ['field' => 'narration', 'heading' => 'Narration'],
                    ['field' => 'reference', 'heading' => 'Reference'],
                    ['field' => 'email', 'heading' => 'Email'],
                ],
            ],

            'hdfc' => [
                'label' => 'HDFC Bank — bulk transfer',
                'description' => 'The payment-type-first layout HDFC’s corporate net banking accepts, without a heading row.',
                'extension' => 'csv',
                'delimiter' => ',',
                'header' => false,
                'date_format' => 'd/m/Y',
                'columns' => [
                    ['field' => 'transaction_type'],
                    ['field' => 'beneficiary_name'],
                    ['field' => 'beneficiary_account'],
                    ['field' => 'amount'],
                    ['field' => 'value_date'],
                    ['field' => 'ifsc'],
                    ['field' => 'narration'],
                    ['field' => 'email'],
                ],
            ],

            'icici' => [
                'label' => 'ICICI Bank — corporate net banking',
                'description' => 'Debit account first, then the beneficiary, with a heading row.',
                'extension' => 'csv',
                'delimiter' => ',',
                'header' => true,
                'date_format' => 'd/m/Y',
                'columns' => [
                    ['field' => 'debit_account', 'heading' => 'Debit Ac No'],
                    ['field' => 'beneficiary_account', 'heading' => 'Beneficiary Ac No'],
                    ['field' => 'beneficiary_name', 'heading' => 'Beneficiary Name'],
                    ['field' => 'amount', 'heading' => 'Amount'],
                    ['field' => 'value_date', 'heading' => 'Value Date'],
                    ['field' => 'transaction_type', 'heading' => 'Payment Type'],
                    ['field' => 'ifsc', 'heading' => 'IFSC'],
                    ['field' => 'narration', 'heading' => 'Remarks'],
                    ['field' => 'email', 'heading' => 'Email'],
                ],
            ],

            'sbi' => [
                'label' => 'State Bank of India — corporate',
                'description' => 'Tab separated, no heading row, the beneficiary’s own bank named in full.',
                'extension' => 'txt',
                'delimiter' => "\t",
                'header' => false,
                'date_format' => 'd/m/Y',
                'columns' => [
                    ['field' => 'serial'],
                    ['field' => 'beneficiary_account'],
                    ['field' => 'beneficiary_name'],
                    ['field' => 'amount'],
                    ['field' => 'ifsc'],
                    ['field' => 'bank_name'],
                    ['field' => 'transaction_type'],
                    ['field' => 'narration'],
                ],
            ],

            'axis' => [
                'label' => 'Axis Bank — corporate',
                'description' => 'Payment type, the two accounts, then the amount, with a heading row.',
                'extension' => 'csv',
                'delimiter' => ',',
                'header' => true,
                'date_format' => 'd-m-Y',
                'columns' => [
                    ['field' => 'transaction_type', 'heading' => 'PYMT_MODE'],
                    ['field' => 'debit_account', 'heading' => 'DEBIT_ACC_NO'],
                    ['field' => 'beneficiary_account', 'heading' => 'BENE_ACC_NO'],
                    ['field' => 'beneficiary_name', 'heading' => 'BENE_NAME'],
                    ['field' => 'amount', 'heading' => 'AMOUNT'],
                    ['field' => 'value_date', 'heading' => 'VALUE_DATE'],
                    ['field' => 'ifsc', 'heading' => 'IFSC_CODE'],
                    ['field' => 'narration', 'heading' => 'REMARKS'],
                    ['field' => 'email', 'heading' => 'BENE_EMAIL'],
                ],
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return static::all()[$key] ?? null;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, static::all());
    }

    public static function keys(): array
    {
        return array_keys(static::all());
    }

    /** @return array<string, string> for a select box */
    public static function options(): array
    {
        return array_map(fn ($format) => $format['label'], static::all());
    }

    public static function default(): string
    {
        return 'generic';
    }
}
