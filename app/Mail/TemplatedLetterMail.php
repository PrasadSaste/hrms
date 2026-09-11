<?php

namespace App\Mail;

use App\Models\Letter;
use App\Services\LetterPdfService;
use Illuminate\Mail\Mailables\Attachment;

/**
 * The covering email for a letter, with the letter itself attached.
 *
 * The words in the body are the notification console's; the words in the
 * attachment are the letter's own, frozen when it was issued.
 */
class TemplatedLetterMail extends TemplatedMail
{
    public function __construct(
        public Letter $letter,
        string $eventKey,
        array $data = [],
        array $overrides = [],
    ) {
        parent::__construct($eventKey, $data, $overrides);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $service = app(LetterPdfService::class);

        return [
            Attachment::fromData(
                fn () => $service->bytes($this->letter),
                $service->filename($this->letter),
            )->withMime('application/pdf'),
        ];
    }
}
