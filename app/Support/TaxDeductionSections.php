<?php

namespace App\Support;

/**
 * What somebody may take off their income before it is taxed.
 *
 * One entry per section of the Act an employee can declare against. The screen
 * the employee fills in, the one HR verifies on, and `IncomeTaxService` all
 * read this list, so a section cannot appear on a form and be quietly ignored
 * by the arithmetic.
 *
 * Two fields carry the weight:
 *
 * - **`ceiling`** is the most that section allows in a year. A declaration
 *   above it is accepted and *capped* rather than refused — somebody may well
 *   have paid that much, and telling them their own bank statement is wrong is
 *   not the software's job. The computation sheet shows both figures.
 * - **`regimes`** is which regime the section survives in. Almost none of them
 *   survive the new regime, which is the whole trade: lower rates for fewer
 *   deductions. An employee on the new regime still sees what they declared,
 *   marked as not counting, because the comparison between regimes is the
 *   thing they are actually deciding.
 *
 * `group` only decides where a section sits on the form. `shares_ceiling_with`
 * marks the sections that draw on one pot — 80C, 80CCC and 80CCD(1) share a
 * ceiling of a lakh and a half between them, and declaring the full amount
 * under each does not get anybody three times the relief.
 *
 * **Check the ceilings against the current Finance Act.** They move.
 */
class TaxDeductionSections
{
    public const GROUPS = [
        'investments' => 'Investments and savings',
        'insurance' => 'Insurance and medical',
        'housing' => 'Housing',
        'other' => 'Other reliefs',
    ];

    /** Sections drawing on the combined ceiling of section 80CCE. */
    public const EIGHTY_C_POOL = '80CCE';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            '80c' => [
                'label' => 'Section 80C',
                'group' => 'investments',
                'description' => 'Provident fund, life insurance premiums, ELSS, PPF, the principal on a home loan, children’s tuition fees, five-year deposits.',
                'ceiling' => 150000.0,
                'shares_ceiling_with' => self::EIGHTY_C_POOL,
                'regimes' => [TaxRegimes::OLD],
                'proof' => 'Premium receipts, a PPF passbook, an ELSS statement, or the lender’s principal certificate.',
            ],
            '80ccd1' => [
                'label' => 'Section 80CCD(1) — your own pension contribution',
                'group' => 'investments',
                'description' => 'What you put into the National Pension System yourself. Counts inside the same ceiling as 80C.',
                'ceiling' => 150000.0,
                'shares_ceiling_with' => self::EIGHTY_C_POOL,
                'regimes' => [TaxRegimes::OLD],
                'proof' => 'Your NPS transaction statement.',
            ],
            '80ccd1b' => [
                'label' => 'Section 80CCD(1B) — additional pension',
                'group' => 'investments',
                'description' => 'A further fifty thousand into the National Pension System, on top of the 80C ceiling rather than inside it.',
                'ceiling' => 50000.0,
                'regimes' => [TaxRegimes::OLD],
                'proof' => 'Your NPS transaction statement, showing the contribution separately.',
            ],
            '80d_self' => [
                'label' => 'Section 80D — health cover for yourself and your family',
                'group' => 'insurance',
                'description' => 'Premiums for you, your spouse and your children. The ceiling is higher if you are sixty or over.',
                'ceiling' => 25000.0,
                'senior_ceiling' => 50000.0,
                'regimes' => [TaxRegimes::OLD],
                'proof' => 'The insurer’s premium certificate.',
            ],
            '80d_parents' => [
                'label' => 'Section 80D — health cover for your parents',
                'group' => 'insurance',
                'description' => 'Premiums paid for your parents, whether or not they depend on you. Higher again where a parent is sixty or over.',
                'ceiling' => 25000.0,
                'senior_ceiling' => 50000.0,
                'regimes' => [TaxRegimes::OLD],
                'proof' => 'The insurer’s premium certificate, naming the parent.',
            ],
            '80ddb' => [
                'label' => 'Section 80DDB — treatment of a specified illness',
                'group' => 'insurance',
                'description' => 'What you actually spent treating one of the illnesses the rules name, for yourself or a dependant.',
                'ceiling' => 40000.0,
                'senior_ceiling' => 100000.0,
                'regimes' => [TaxRegimes::OLD],
                'proof' => 'A prescription from the specialist the rules require, and the bills.',
            ],
            'hra' => [
                'label' => 'House rent allowance',
                'group' => 'housing',
                'description' => 'Rent you actually pay. What is exempt is the least of three figures, worked out from your salary and your city — declare the rent and the arithmetic is done for you.',
                'ceiling' => null,
                'computed' => true,
                'regimes' => [TaxRegimes::OLD],
                'proof' => 'Rent receipts, and the landlord’s PAN where the year’s rent is over a lakh.',
            ],
            'section24b' => [
                'label' => 'Section 24(b) — interest on a home loan',
                'group' => 'housing',
                'description' => 'Interest on a loan for a house you live in. Two lakh a year for a self-occupied property.',
                'ceiling' => 200000.0,
                'regimes' => [TaxRegimes::OLD],
                'proof' => 'The lender’s interest certificate for the year.',
            ],
            '80e' => [
                'label' => 'Section 80E — interest on an education loan',
                'group' => 'other',
                'description' => 'Interest on a loan for higher education, with no ceiling, for eight years from when you start repaying.',
                'ceiling' => null,
                'regimes' => [TaxRegimes::OLD],
                'proof' => 'The lender’s interest certificate.',
            ],
            '80g' => [
                'label' => 'Section 80G — donations',
                'group' => 'other',
                'description' => 'Donations to funds and institutions that qualify. Some allow half, some the whole amount — declare what you gave and HR will apply the right share.',
                'ceiling' => null,
                'regimes' => [TaxRegimes::OLD],
                'proof' => 'The receipt, showing the institution’s registration number.',
            ],
            '80tta' => [
                'label' => 'Section 80TTA — interest on a savings account',
                'group' => 'other',
                'description' => 'Interest a savings account paid you, up to ten thousand.',
                'ceiling' => 10000.0,
                'regimes' => [TaxRegimes::OLD],
                'proof' => 'Your bank’s interest certificate.',
            ],
            'other_income' => [
                'label' => 'Other income to be taxed with your salary',
                'group' => 'other',
                'description' => 'Interest, rent or anything else you would like taken into account, so the tax deducted each month is closer to what you actually owe.',
                'ceiling' => null,
                'addition' => true,
                'regimes' => [TaxRegimes::OLD, TaxRegimes::NEW],
                'proof' => 'Whatever evidences it — a bank certificate, a rent agreement.',
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

    /** Sections that reduce income, as opposed to adding to it. */
    public static function reliefs(): array
    {
        return array_filter(static::all(), fn ($section) => ! ($section['addition'] ?? false));
    }

    /** Sections that add to income rather than reducing it. */
    public static function additions(): array
    {
        return array_filter(static::all(), fn ($section) => $section['addition'] ?? false);
    }

    /** Whether a section counts at all under a given regime. */
    public static function appliesUnder(string $key, string $regime): bool
    {
        $section = static::get($key);

        return $section !== null && in_array($regime, $section['regimes'], true);
    }

    /**
     * The sections in one group, in the order they are written above.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function inGroup(string $group): array
    {
        return array_filter(static::all(), fn ($section) => $section['group'] === $group);
    }

    /** The ceiling on a section, which is higher for somebody over sixty. */
    public static function ceilingFor(string $key, bool $senior = false): ?float
    {
        $section = static::get($key);

        if ($section === null) {
            return null;
        }

        return $senior && isset($section['senior_ceiling'])
            ? (float) $section['senior_ceiling']
            : ($section['ceiling'] === null ? null : (float) $section['ceiling']);
    }
}
