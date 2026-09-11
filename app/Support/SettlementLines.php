<?php

namespace App\Support;

/**
 * What a full and final settlement can be made of.
 *
 * A catalogue like the others, and for the same reason: the statement an
 * employee receives on their last day has to be explainable line by line, and
 * a line nobody can name is a line somebody will dispute. Each entry says what
 * it is, which way the money goes, and — where the law decides the answer
 * rather than the company — where the rule comes from.
 *
 * `computed` marks the lines this system works out for itself. The rest are
 * there for whoever prepares the settlement to fill in, because the system
 * cannot know about a bonus that was promised in a meeting.
 */
final class SettlementLines
{
    public const FINAL_SALARY = 'final_salary';

    public const LEAVE_ENCASHMENT = 'leave_encashment';

    public const GRATUITY = 'gratuity';

    public const BONUS = 'bonus';

    public const REIMBURSEMENT = 'reimbursement';

    public const OTHER_EARNING = 'other_earning';

    public const NOTICE_RECOVERY = 'notice_recovery';

    public const ASSET_RECOVERY = 'asset_recovery';

    public const ADVANCE_RECOVERY = 'advance_recovery';

    public const STATUTORY = 'statutory';

    public const OTHER_DEDUCTION = 'other_deduction';

    public const EARNING = 'earning';

    public const DEDUCTION = 'deduction';

    /**
     * @return array<string, array{
     *     label: string, type: string, description: string,
     *     computed: bool, basis: ?string, sequence: int
     * }>
     */
    public static function all(): array
    {
        return [
            self::FINAL_SALARY => [
                'label' => 'Salary for the final month',
                'type' => self::EARNING,
                'description' => 'Pay for the days actually worked in the month somebody leaves.',
                'computed' => true,
                'basis' => 'Days worked up to and including the last working day, prorated the same way payroll prorates any other month.',
                'sequence' => 10,
            ],

            self::LEAVE_ENCASHMENT => [
                'label' => 'Leave encashment',
                'type' => self::EARNING,
                'description' => 'Unused leave paid out rather than taken.',
                'computed' => true,
                'basis' => 'Remaining days on encashable leave types, at basic pay divided by the month\'s paid days.',
                'sequence' => 20,
            ],

            self::GRATUITY => [
                'label' => 'Gratuity',
                'type' => self::EARNING,
                'description' => 'The statutory payment for long service.',
                'computed' => true,
                'basis' => 'Payment of Gratuity Act 1972: fifteen days\' wages for each completed year, at last drawn basic '
                    .'multiplied by 15/26, once five years are complete, capped at the statutory maximum.',
                'sequence' => 30,
            ],

            self::BONUS => [
                'label' => 'Bonus or incentive',
                'type' => self::EARNING,
                'description' => 'Anything owed that payroll did not already pay.',
                'computed' => false,
                'basis' => null,
                'sequence' => 40,
            ],

            self::REIMBURSEMENT => [
                'label' => 'Reimbursement owed',
                'type' => self::EARNING,
                'description' => 'Expenses claimed and approved but not yet paid.',
                'computed' => false,
                'basis' => null,
                'sequence' => 50,
            ],

            self::OTHER_EARNING => [
                'label' => 'Other payment',
                'type' => self::EARNING,
                'description' => 'Anything else owed to them, named on the statement.',
                'computed' => false,
                'basis' => null,
                'sequence' => 60,
            ],

            self::NOTICE_RECOVERY => [
                'label' => 'Notice period not served',
                'type' => self::DEDUCTION,
                'description' => 'Recovery where somebody leaves before working their notice.',
                'computed' => true,
                'basis' => 'Days short of the notice their record requires, at basic pay divided by the month\'s paid days. '
                    .'Nothing is recovered where the notice was waived or fully served.',
                'sequence' => 70,
            ],

            self::ASSET_RECOVERY => [
                'label' => 'Company property not returned',
                'type' => self::DEDUCTION,
                'description' => 'The value of anything still with them on their last day.',
                'computed' => false,
                'basis' => 'The register lists what is outstanding; what to charge for it is a judgement, so it is typed in.',
                'sequence' => 80,
            ],

            self::ADVANCE_RECOVERY => [
                'label' => 'Advance or loan outstanding',
                'type' => self::DEDUCTION,
                'description' => 'The balance of anything lent to them.',
                'computed' => false,
                'basis' => null,
                'sequence' => 90,
            ],

            self::STATUTORY => [
                'label' => 'Statutory deduction',
                'type' => self::DEDUCTION,
                'description' => 'Provident fund, state insurance, professional tax or income tax on the final payment.',
                'computed' => false,
                'basis' => null,
                'sequence' => 100,
            ],

            self::OTHER_DEDUCTION => [
                'label' => 'Other recovery',
                'type' => self::DEDUCTION,
                'description' => 'Anything else being recovered, named on the statement.',
                'computed' => false,
                'basis' => null,
                'sequence' => 110,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function label(string $key): string
    {
        return self::find($key)['label'] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public static function type(string $key): string
    {
        return self::find($key)['type'] ?? self::EARNING;
    }

    public static function sequence(string $key): int
    {
        return self::find($key)['sequence'] ?? 999;
    }

    public static function basis(string $key): ?string
    {
        return self::find($key)['basis'] ?? null;
    }

    /** The lines this system works out for itself. */
    public static function computed(): array
    {
        return array_keys(array_filter(self::all(), fn (array $line) => $line['computed']));
    }

    /** The ones somebody has to decide and type in. */
    public static function manual(): array
    {
        return array_keys(array_filter(self::all(), fn (array $line) => ! $line['computed']));
    }

    /** @return array<string, array<string, mixed>> */
    public static function ofType(string $type): array
    {
        return array_filter(self::all(), fn (array $line) => $line['type'] === $type);
    }

    /** @return array<string, string> */
    public static function options(string $type): array
    {
        return array_map(fn (array $line) => $line['label'], self::ofType($type));
    }
}
