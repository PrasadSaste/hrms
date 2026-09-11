<?php

namespace Tests\Feature;

use App\Listeners\RedirectOutgoingMail;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Anywhere but production, every message goes to one inbox.
 *
 * A staging server is usually seeded from a copy of the real database, so an
 * approval tested there would otherwise reach the employee it names.
 */
class MailRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected string $inbox = 'api@shrigodatechlabs.com';

    protected function redirectTo(?string $address): void
    {
        config([
            'mail.redirect.to' => $address,
            'mail.redirect.name' => 'HRMS test inbox',
        ]);
    }

    /** Send one real message and hand back what actually went out. */
    protected function send(callable $build): Email
    {
        $sent = [];

        Mail::raw('Body', $build);

        foreach (Mail::mailer()->getSymfonyTransport()->messages() as $message) {
            /** @var SentMessage $message */
            $sent[] = $message->getOriginalMessage();
        }

        $this->assertNotEmpty($sent, 'Nothing was sent.');

        return end($sent);
    }

    // ---------------------------------------------------------- redirecting

    public function test_every_message_goes_to_the_one_inbox(): void
    {
        $this->redirectTo($this->inbox);

        $email = $this->send(fn ($message) => $message
            ->to('priya.raghavan@beyondsure.example', 'Priya Raghavan')
            ->subject('Leave approved'));

        $this->assertSame([$this->inbox], $this->addressesOf($email->getTo()));
        $this->assertSame('HRMS test inbox', $email->getTo()[0]->getName());
    }

    public function test_the_real_recipients_travel_with_the_message(): void
    {
        $this->redirectTo($this->inbox);

        $email = $this->send(fn ($message) => $message
            ->to('priya.raghavan@beyondsure.example', 'Priya Raghavan')
            ->cc('vikram.desai@beyondsure.example')
            ->bcc('audit@beyondsure.example')
            ->subject('Leave approved'));

        $headers = $email->getHeaders();

        $this->assertStringContainsString(
            'priya.raghavan@beyondsure.example',
            $headers->get('X-Original-To')->getBodyAsString(),
        );
        $this->assertStringContainsString(
            'vikram.desai@beyondsure.example',
            $headers->get('X-Original-Cc')->getBodyAsString(),
        );
        $this->assertStringContainsString(
            'audit@beyondsure.example',
            $headers->get('X-Original-Bcc')->getBodyAsString(),
        );
    }

    public function test_copies_are_dropped_rather_than_redirected_too(): void
    {
        $this->redirectTo($this->inbox);

        // Otherwise one test message would arrive three times in the same inbox.
        $email = $this->send(fn ($message) => $message
            ->to('priya.raghavan@beyondsure.example')
            ->cc('vikram.desai@beyondsure.example')
            ->bcc('audit@beyondsure.example')
            ->subject('Leave approved'));

        $this->assertSame([], $email->getCc());
        $this->assertSame([], $email->getBcc());
    }

    public function test_the_subject_and_body_are_untouched(): void
    {
        $this->redirectTo($this->inbox);

        $email = $this->send(fn ($message) => $message
            ->to('priya.raghavan@beyondsure.example')
            ->subject('Leave approved'));

        $this->assertSame('Leave approved', $email->getSubject());
        $this->assertStringContainsString('Body', $email->getTextBody());
    }

    // ------------------------------------------------------- and when not to

    public function test_without_an_address_configured_nothing_is_diverted(): void
    {
        $this->redirectTo(null);

        $email = $this->send(fn ($message) => $message
            ->to('priya.raghavan@beyondsure.example')
            ->subject('Leave approved'));

        $this->assertSame(['priya.raghavan@beyondsure.example'], $this->addressesOf($email->getTo()));
        $this->assertFalse($email->getHeaders()->has('X-Original-To'));
    }

    public function test_production_is_never_redirected(): void
    {
        // The guard is in config, not in the deployment: an address left in a
        // production .env by mistake must still not divert live mail.
        $this->assertNull($this->configuredRedirectFor('production', $this->inbox));
        $this->assertSame($this->inbox, $this->configuredRedirectFor('staging', $this->inbox));
        $this->assertSame($this->inbox, $this->configuredRedirectFor('local', $this->inbox));
    }

    public function test_a_message_addressed_to_nobody_is_left_alone(): void
    {
        $this->redirectTo($this->inbox);

        $message = (new Email)->subject('Nobody')->text('Body');
        $listener = new RedirectOutgoingMail;

        $listener->handle(new MessageSending($message));

        $this->assertSame([], $message->getTo());
    }

    // -------------------------------------------------------- the interface

    public function test_the_console_says_that_mail_is_being_diverted(): void
    {
        $this->redirectTo($this->inbox);
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)->get(route('notification-templates.index'))
            ->assertOk()
            ->assertSee($this->inbox);
    }

    public function test_nothing_is_claimed_when_mail_is_not_being_diverted(): void
    {
        $this->redirectTo(null);
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)->get(route('notification-templates.index'))
            ->assertOk()
            ->assertDontSee('instead of the person it names');
    }

    /**
     * What config/mail.php actually resolves to for a given environment.
     *
     * The file is evaluated afresh with the environment variables swapped, so
     * this exercises the real guard rather than restating it. Laravel reads env
     * through $_ENV and $_SERVER — putenv is disabled — so those are what is
     * swapped, and put back afterwards whatever happens.
     */
    protected function configuredRedirectFor(string $environment, string $address): ?string
    {
        $previous = [
            'APP_ENV' => $_ENV['APP_ENV'] ?? null,
            'MAIL_REDIRECT_ALL_TO' => $_ENV['MAIL_REDIRECT_ALL_TO'] ?? null,
        ];

        try {
            foreach (['APP_ENV' => $environment, 'MAIL_REDIRECT_ALL_TO' => $address] as $key => $value) {
                $_ENV[$key] = $_SERVER[$key] = $value;
            }

            return (require config_path('mail.php'))['redirect']['to'];
        } finally {
            foreach ($previous as $key => $value) {
                if ($value === null) {
                    unset($_ENV[$key], $_SERVER[$key]);

                    continue;
                }

                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, string>
     */
    protected function addressesOf(array $addresses): array
    {
        return array_map(fn ($address) => $address->getAddress(), $addresses);
    }
}
