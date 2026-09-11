<?php

namespace App\Services;

use App\Models\Letter;
use App\Support\LetterTypes;
use App\Support\Markdown;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWrapper;

/**
 * A letter on the company's letterhead.
 *
 * The words come off the letter itself rather than the template, so reprinting
 * one from two years ago gives back what was signed then.
 */
class LetterPdfService
{
    /** Letters somebody is expected to sign and return. */
    protected const NEEDS_ACCEPTANCE = [
        LetterTypes::OFFER,
        LetterTypes::APPOINTMENT,
        LetterTypes::WARNING,
    ];

    public function __construct(protected Letterhead $letterhead) {}

    public function make(Letter $letter): PdfWrapper
    {
        $letter->loadMissing(['employee.designation', 'employee.department', 'employee.branch', 'company']);

        return Pdf::loadView('pdf.letter', [
            'letter' => $letter,
            'employee' => $letter->employee,
            'company' => $this->letterhead->forCompany($letter->company),
            'body' => Markdown::html($letter->body, hardBreaks: true),
            // The signature frozen onto this letter, not whatever the signatory
            // has uploaded since.
            'signature' => $letter->signatureDataUri(),
            'acknowledgement' => in_array($letter->type, self::NEEDS_ACCEPTANCE, true),
        ])->setPaper('a4');
    }

    /** Raw bytes, for attaching to an email. */
    public function bytes(Letter $letter): string
    {
        return $this->make($letter)->output();
    }

    public function filename(Letter $letter): string
    {
        return $letter->filename();
    }
}
