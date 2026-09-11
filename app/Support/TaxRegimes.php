<?php

namespace App\Support;

/**
 * What the state takes, and at which point.
 *
 * Every figure here is set by a Finance Act and revised most years, so the
 * catalogue is keyed by **financial year** and never by "current": a payslip
 * issued last March was taxed under last March's rules and must stay that way,
 * the same reason a letter freezes its own words. Asking for a year the
 * catalogue does not know returns the latest year it does, and says so, so a
 * system left un-updated over a budget reports an assumption rather than
 * quietly inventing one.
 *
 * **Check these against the Finance Act before your first run.** They are the
 * published figures as this was written, and the software cannot tell when
 * Parliament has moved them. The computation sheet names the year whose rules
 * produced it for exactly this reason.
 *
 * Two regimes coexist. The new one is the default an employee is taxed under
 * unless they choose otherwise; the old one keeps the deductions people have
 * arranged their affairs around. `App\Services\IncomeTaxService` works out both
 * and shows the difference rather than choosing for anybody.
 *
 * A slab is `[ceiling, rate]`, lowest first, and a null ceiling is the top
 * band — the same shape professional tax uses, read the same way.
 */
class TaxRegimes
{
    public const NEW = 'new';

    public const OLD = 'old';

    /** Health and education cess, charged on tax plus surcharge. */
    public const CESS_RATE = 0.04;

    public static function labels(): array
    {
        return [
            self::NEW => 'New regime',
            self::OLD => 'Old regime',
        ];
    }

    public static function default(): string
    {
        return self::NEW;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            // FY 2025-26 onwards, as set by the Finance Act 2025.
            2025 => [
                'label' => 'FY 2025–26',
                'regimes' => [
                    self::NEW => [
                        'standard_deduction' => 75000.0,
                        'slabs' => [
                            ['ceiling' => 400000.0, 'rate' => 0.0],
                            ['ceiling' => 800000.0, 'rate' => 0.05],
                            ['ceiling' => 1200000.0, 'rate' => 0.10],
                            ['ceiling' => 1600000.0, 'rate' => 0.15],
                            ['ceiling' => 2000000.0, 'rate' => 0.20],
                            ['ceiling' => 2400000.0, 'rate' => 0.25],
                            ['ceiling' => null, 'rate' => 0.30],
                        ],
                        // Section 87A. Below the ceiling the rebate wipes the
                        // tax out; a rupee over it and the whole tax is due,
                        // which is why marginal relief exists beside it.
                        'rebate' => ['ceiling' => 1200000.0, 'maximum' => 60000.0],
                        // The new regime's surcharge stops at 25%.
                        'surcharge' => [
                            ['ceiling' => 5000000.0, 'rate' => 0.0],
                            ['ceiling' => 10000000.0, 'rate' => 0.10],
                            ['ceiling' => 20000000.0, 'rate' => 0.15],
                            ['ceiling' => null, 'rate' => 0.25],
                        ],
                    ],
                    self::OLD => [
                        'standard_deduction' => 50000.0,
                        'slabs' => [
                            ['ceiling' => 250000.0, 'rate' => 0.0],
                            ['ceiling' => 500000.0, 'rate' => 0.05],
                            ['ceiling' => 1000000.0, 'rate' => 0.20],
                            ['ceiling' => null, 'rate' => 0.30],
                        ],
                        'rebate' => ['ceiling' => 500000.0, 'maximum' => 12500.0],
                        'surcharge' => [
                            ['ceiling' => 5000000.0, 'rate' => 0.0],
                            ['ceiling' => 10000000.0, 'rate' => 0.10],
                            ['ceiling' => 20000000.0, 'rate' => 0.15],
                            ['ceiling' => 50000000.0, 'rate' => 0.25],
                            ['ceiling' => null, 'rate' => 0.37],
                        ],
                        // Age raises the point at which tax starts, under the
                        // old regime only. Read first-match on age.
                        'senior_exemptions' => [
                            ['from_age' => 80, 'exemption' => 500000.0],
                            ['from_age' => 60, 'exemption' => 300000.0],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** The years the catalogue actually knows about, newest last. */
    public static function years(): array
    {
        $years = array_keys(static::all());
        sort($years);

        return $years;
    }

    /**
     * The rules that applied in a financial year.
     *
     * A year the catalogue has never heard of falls back to the latest it
     * knows, and `assumed` says so — the screens print that, because a figure
     * computed under last year's Finance Act is not wrong so much as stale,
     * and the difference matters to whoever signs it off.
     *
     * @return array<string, mixed>
     */
    public static function forYear(int $year): array
    {
        $all = static::all();
        $known = static::years();
        $applicable = $year;

        if (! isset($all[$year])) {
            // The latest year at or below the one asked for; failing that, the
            // earliest known, for a query about a year before the catalogue.
            $earlier = array_filter($known, fn ($known) => $known <= $year);
            $applicable = $earlier ? max($earlier) : min($known);
        }

        return [
            'year' => $year,
            'rules_year' => $applicable,
            'assumed' => $applicable !== $year,
        ] + $all[$applicable];
    }

    /**
     * One regime's rules for a year.
     *
     * @return array<string, mixed>
     */
    public static function rules(int $year, string $regime): array
    {
        $year = static::forYear($year);
        $regime = array_key_exists($regime, $year['regimes']) ? $regime : static::default();

        return $year['regimes'][$regime] + [
            'regime' => $regime,
            'rules_year' => $year['rules_year'],
            'assumed' => $year['assumed'],
        ];
    }

    public static function has(string $regime): bool
    {
        return array_key_exists($regime, static::labels());
    }

    public static function label(string $regime): string
    {
        return static::labels()[$regime] ?? $regime;
    }
}
