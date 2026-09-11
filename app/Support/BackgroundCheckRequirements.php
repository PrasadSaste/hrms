<?php

namespace App\Support;

/**
 * What a new joiner has to produce before they are onboarded.
 *
 * Each requirement says what to upload and what to type alongside it, because
 * a certificate on its own does not say which university issued it or when.
 * HR chooses which of these apply when they invite someone, so a contractor
 * and a graduate hire are not asked for the same things.
 */
final class BackgroundCheckRequirements
{
    public const IDENTITY = 'identity';

    public const ADDRESS = 'address';

    public const EDUCATION = 'education';

    public const EXPERIENCE = 'experience';

    public const PAYSLIP = 'payslip';

    public const BANK = 'bank';

    public const PHOTOGRAPH = 'photograph';

    /**
     * @return array<string, array{
     *     label: string, description: string, default: bool,
     *     fields: array<string, array{label: string, type: string, required: bool}>
     * }>
     */
    public static function all(): array
    {
        return [
            self::IDENTITY => [
                'label' => 'Identity document',
                'description' => 'A government photo identity document — passport, driving licence or national identity card.',
                'default' => true,
                'fields' => [
                    'document_type' => ['label' => 'Which document', 'type' => 'text', 'required' => true],
                    'document_number' => ['label' => 'Document number', 'type' => 'text', 'required' => true],
                    'issued_on' => ['label' => 'Issued on', 'type' => 'date', 'required' => false],
                    'expires_on' => ['label' => 'Expires on', 'type' => 'date', 'required' => false],
                ],
            ],

            self::ADDRESS => [
                'label' => 'Proof of address',
                'description' => 'Something recent showing where you live — a utility bill, a bank statement or a rental agreement.',
                'default' => true,
                'fields' => [
                    'document_type' => ['label' => 'Which document', 'type' => 'text', 'required' => true],
                    'address' => ['label' => 'The address shown on it', 'type' => 'textarea', 'required' => true],
                ],
            ],

            self::EDUCATION => [
                'label' => 'Education certificate',
                'description' => 'Your highest qualification: the degree or diploma certificate, not the mark sheet.',
                'default' => true,
                'fields' => [
                    'qualification' => ['label' => 'Qualification', 'type' => 'text', 'required' => true],
                    'institution' => ['label' => 'University or college', 'type' => 'text', 'required' => true],
                    'completed_year' => ['label' => 'Year completed', 'type' => 'text', 'required' => true],
                ],
            ],

            self::EXPERIENCE => [
                'label' => 'Experience letter',
                'description' => 'The relieving or experience letter from your most recent employer. Skip this if we are your first.',
                'default' => true,
                'fields' => [
                    'employer' => ['label' => 'Employer', 'type' => 'text', 'required' => true],
                    'job_title' => ['label' => 'Your job title there', 'type' => 'text', 'required' => true],
                    'from' => ['label' => 'From', 'type' => 'date', 'required' => true],
                    'to' => ['label' => 'To', 'type' => 'date', 'required' => true],
                    'reference_name' => ['label' => 'Someone we may contact', 'type' => 'text', 'required' => false],
                    'reference_contact' => ['label' => 'Their email or phone', 'type' => 'text', 'required' => false],
                ],
            ],

            self::PAYSLIP => [
                'label' => 'Previous salary slip',
                'description' => 'Your last salary slip from your previous employer.',
                'default' => false,
                'fields' => [
                    'employer' => ['label' => 'Employer', 'type' => 'text', 'required' => true],
                    'period' => ['label' => 'Which month', 'type' => 'text', 'required' => true],
                ],
            ],

            self::BANK => [
                'label' => 'Bank account proof',
                'description' => 'A cancelled cheque or a bank statement header showing your name and account number.',
                'default' => false,
                'fields' => [
                    'bank_name' => ['label' => 'Bank', 'type' => 'text', 'required' => true],
                    'account_number' => ['label' => 'Account number', 'type' => 'text', 'required' => true],
                ],
            ],

            self::PHOTOGRAPH => [
                'label' => 'Photograph',
                'description' => 'A recent passport-style photograph for your record and your identity card.',
                'default' => false,
                'fields' => [],
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

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

    /** The requirements asked for unless HR says otherwise. */
    public static function defaults(): array
    {
        return array_keys(array_filter(self::all(), fn (array $r) => $r['default']));
    }

    /** @return array<string, array{label: string, type: string, required: bool}> */
    public static function fieldsFor(string $key): array
    {
        return self::find($key)['fields'] ?? [];
    }
}
