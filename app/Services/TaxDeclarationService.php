<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TaxDeclaration;
use App\Models\TaxDeclarationItem;
use App\Models\User;
use App\Support\FinancialYear;
use App\Support\TaxDeductionSections;
use App\Support\TaxRegimes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A declaration is what somebody says they will invest, and what HR found.
 *
 * The two figures are kept apart deliberately. An employee declares in April
 * what they intend to do by March, and payroll has to deduct tax on something
 * in the meantime; HR sees the proof in January and may accept less. So an
 * item carries both, `effectiveAmount()` prefers the verified one once it
 * exists, and neither overwrites the other — a declaration that was cut down
 * still shows what was originally claimed.
 *
 * Proofs go to the private disk and are streamed, never linked: they are bank
 * statements and insurance certificates.
 */
class TaxDeclarationService
{
    public const PROOF_DISK = 'local';

    /** The declaration for a year, made if it does not exist yet. */
    public function forEmployee(Employee $employee, ?int $financialYear = null): TaxDeclaration
    {
        $financialYear ??= FinancialYear::current();

        return TaxDeclaration::firstOrCreate(
            ['employee_id' => $employee->id, 'financial_year' => $financialYear],
            ['regime' => TaxRegimes::default(), 'status' => TaxDeclaration::DRAFT],
        )->load('items');
    }

    /**
     * Save what the employee typed.
     *
     * A section left blank is deleted rather than stored as a zero, so the
     * verification screen shows what somebody actually claimed instead of a
     * column of noughts. An amount HR has already verified is not touched by
     * a later edit to the declared figure — the two are separate answers to
     * separate questions.
     *
     * @param  array<string, float|string|null>  $amounts  section key => amount
     */
    public function save(TaxDeclaration $declaration, array $amounts, array $attributes = []): TaxDeclaration
    {
        return DB::transaction(function () use ($declaration, $amounts, $attributes) {
            $declaration->fill(array_intersect_key($attributes, array_flip(['regime', 'metro'])));

            if (! TaxRegimes::has((string) $declaration->regime)) {
                $declaration->regime = TaxRegimes::default();
            }

            $declaration->save();

            foreach (TaxDeductionSections::keys() as $section) {
                $amount = round((float) ($amounts[$section] ?? 0), 2);

                if ($amount <= 0) {
                    $this->forget($declaration, $section);

                    continue;
                }

                TaxDeclarationItem::updateOrCreate(
                    ['tax_declaration_id' => $declaration->id, 'section' => $section],
                    ['declared_amount' => $amount],
                );
            }

            return $declaration->fresh('items');
        });
    }

    /** Hand it to HR. */
    public function submit(TaxDeclaration $declaration): TaxDeclaration
    {
        $declaration->update([
            'status' => TaxDeclaration::SUBMITTED,
            'submitted_at' => Carbon::now(),
            'verified_at' => null,
            'verified_by' => null,
        ]);

        return $declaration->fresh('items');
    }

    /**
     * Record what HR accepted.
     *
     * A blank box is not zero: it means nobody has ruled on that section yet,
     * and the projection goes on using the declared figure. Accepting nothing
     * is typing a nought, which is a different act and is stored as one.
     *
     * @param  array<string, float|string|null>  $verified  section key => amount or null
     */
    public function verify(TaxDeclaration $declaration, array $verified, User $verifier, ?string $remarks = null): TaxDeclaration
    {
        return DB::transaction(function () use ($declaration, $verified, $verifier, $remarks) {
            foreach ($declaration->items as $item) {
                $value = $verified[$item->section] ?? null;

                $item->update([
                    'verified_amount' => ($value === null || $value === '')
                        ? null
                        : round((float) $value, 2),
                ]);
            }

            $declaration->update([
                'status' => TaxDeclaration::VERIFIED,
                'verified_at' => Carbon::now(),
                'verified_by' => $verifier->id,
                'remarks' => $remarks,
            ]);

            return $declaration->fresh('items');
        });
    }

    /** Send it back for the employee to correct. */
    public function sendBack(TaxDeclaration $declaration, User $actor, string $remarks): TaxDeclaration
    {
        $declaration->update([
            'status' => TaxDeclaration::RETURNED,
            'verified_at' => null,
            'verified_by' => $actor->id,
            'remarks' => $remarks,
        ]);

        return $declaration->fresh('items');
    }

    /** Attach a proof to one section, replacing whatever was there. */
    public function attachProof(TaxDeclaration $declaration, string $section, UploadedFile $file): TaxDeclarationItem
    {
        $item = TaxDeclarationItem::firstOrCreate(
            ['tax_declaration_id' => $declaration->id, 'section' => $section],
            ['declared_amount' => 0],
        );

        $this->deleteProof($item);

        $item->update([
            'proof_path' => $file->store(
                'tax-proofs/'.$declaration->employee_id.'/'.$declaration->financial_year,
                self::PROOF_DISK,
            ),
        ]);

        return $item->fresh();
    }

    /** Streamed rather than linked: these are bank statements. */
    public function downloadProof(TaxDeclarationItem $item): StreamedResponse
    {
        abort_unless(
            $item->proof_path && Storage::disk(self::PROOF_DISK)->exists($item->proof_path),
            404,
            'The stored proof is missing.',
        );

        return Storage::disk(self::PROOF_DISK)->download(
            $item->proof_path,
            $item->section.'-'.basename($item->proof_path),
        );
    }

    public function deleteProof(TaxDeclarationItem $item): void
    {
        if ($item->proof_path && Storage::disk(self::PROOF_DISK)->exists($item->proof_path)) {
            Storage::disk(self::PROOF_DISK)->delete($item->proof_path);
        }
    }

    /**
     * Drop a section, keeping its proof if HR has already ruled on it.
     *
     * Somebody clearing a figure they typed by mistake should not silently
     * destroy the certificate HR accepted against it.
     */
    protected function forget(TaxDeclaration $declaration, string $section): void
    {
        $item = $declaration->items()->where('section', $section)->first();

        if (! $item || $item->isVerified()) {
            return;
        }

        $this->deleteProof($item);
        $item->delete();
    }
}
