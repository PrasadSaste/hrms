<?php

namespace App\Http\Controllers;

use App\Mail\TemplatedMail;
use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Services\NotificationDispatcher;
use App\Services\NotificationTemplateRenderer;
use App\Support\NotificationEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The console where every message the HRMS sends is switched on or off and
 * reworded, without touching a Blade file.
 */
class NotificationTemplateController extends Controller
{
    public function __construct(protected NotificationTemplateRenderer $renderer) {}

    public function index(Request $request): View
    {
        $this->authorise($request);

        return view('notification-templates.index', [
            'groups' => NotificationTemplate::resolveGrouped(),
            'emailEnabled' => (bool) Setting::get('email_notifications_enabled', true),
        ]);
    }

    public function edit(Request $request, string $key): View
    {
        $this->authorise($request);

        $template = $this->findOrFail($key);

        return view('notification-templates.edit', [
            'template' => $template,
            'sample' => $this->renderer->sampleData($key),
            'defaults' => NotificationEvents::find($key),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $this->authorise($request);
        $this->findOrFail($key);

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'action_label' => ['nullable', 'string', 'max:64'],
            'title' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:500'],
            'mail_enabled' => ['nullable', 'boolean'],
            'database_enabled' => ['nullable', 'boolean'],
        ]);

        NotificationTemplate::updateOrCreate(['key' => $key], [
            'mail_enabled' => $request->boolean('mail_enabled'),
            'database_enabled' => $request->boolean('database_enabled'),
            'subject' => $this->overrideOrNull($key, 'subject', $validated['subject'] ?? null),
            'body' => $this->overrideOrNull($key, 'body', $validated['body'] ?? null),
            'action_label' => $this->overrideOrNull($key, 'action_label', $validated['action_label'] ?? null),
            'title' => $this->overrideOrNull($key, 'title', $validated['title'] ?? null),
            'message' => $this->overrideOrNull($key, 'message', $validated['message'] ?? null),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('notification-templates.index')
            ->with('success', 'Saved the wording for “'.NotificationEvents::find($key)['label'].'”.');
    }

    /** Switch one channel on or off from the list, without opening the editor. */
    public function channels(Request $request, string $key): RedirectResponse
    {
        $this->authorise($request);
        $this->findOrFail($key);

        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:mail,database'],
            'enabled' => ['required', 'boolean'],
        ]);

        abort_unless(NotificationEvents::supports($key, $validated['channel']), 422);

        $template = NotificationTemplate::firstOrNew(['key' => $key]);
        $template->fill([
            $validated['channel'].'_enabled' => $validated['enabled'],
            'updated_by' => $request->user()->id,
        ])->save();

        return back()->with('success', $validated['enabled']
            ? 'That notification is switched on.'
            : 'That notification will no longer be sent.');
    }

    /** Put an event back to the wording the application ships with. */
    public function destroy(Request $request, string $key): RedirectResponse
    {
        $this->authorise($request);
        $this->findOrFail($key);

        // Deleted through the model so the resolved-template cache is dropped.
        NotificationTemplate::where('key', $key)->first()?->delete();

        return redirect()->route('notification-templates.edit', $key)
            ->with('success', 'Restored the wording this notification ships with.');
    }

    /** Render what is currently in the form, so the editor can show it live. */
    public function preview(Request $request, string $key): JsonResponse
    {
        $this->authorise($request);
        $this->findOrFail($key);

        $overrides = $request->only(['subject', 'body', 'action_label', 'title', 'message']);
        $sample = $this->renderer->sampleData($key);

        $email = $this->renderer->email($key, $sample, $overrides);
        $database = $this->renderer->database($key, $sample, $overrides);

        return response()->json([
            'subject' => $email['subject'],
            'html' => $this->renderer->html($email['body']),
            'action_label' => $email['action_label'],
            'title' => $database['title'],
            'message' => $database['message'],
        ]);
    }

    /**
     * Send the message on screen as a test, to the address typed on the form
     * or, when none is given, to the signed-in administrator.
     */
    public function test(Request $request, string $key, NotificationDispatcher $dispatcher): RedirectResponse
    {
        $this->authorise($request);
        $this->findOrFail($key);

        $validated = $request->validate([
            'test_email' => ['nullable', 'email:rfc', 'max:255'],
        ], [
            'test_email.email' => 'Enter a valid email address to send the test to.',
        ]);

        $address = $validated['test_email'] ?? $request->user()->email;

        if (! $address) {
            return back()->with('error', 'Enter an email address to send the test to.');
        }

        if (! NotificationEvents::supports($key, 'mail')) {
            return back()->with('error', 'This notification does not go out by email.');
        }

        $overrides = $request->only(['subject', 'body', 'action_label']);
        $sample = $this->renderer->sampleData($key);

        if (strcasecmp($address, (string) $request->user()->email) === 0) {
            $sample['recipient_name'] = $request->user()->name;
        }

        $sent = $dispatcher->deliver($address, new TemplatedMail($key, $sample, $overrides));

        return back()->with(
            $sent ? 'success' : 'error',
            $sent
                ? 'A test message with example details is on its way to '.$address.'.'
                : 'The test could not be sent. Check the mail settings and the application log.',
        );
    }

    /** @return array<string, mixed> */
    protected function findOrFail(string $key): array
    {
        abort_unless(NotificationEvents::exists($key), 404);

        return NotificationTemplate::resolve($key);
    }

    /**
     * Keep an edit only when it differs from the shipped wording, so anything
     * left alone still follows the application's own copy.
     */
    protected function overrideOrNull(string $key, string $field, ?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $event = NotificationEvents::find($key);
        $default = match ($field) {
            'subject' => $event['email']['subject'] ?? null,
            'body' => $event['email']['body'] ?? null,
            'action_label' => $event['email']['action']['label'] ?? null,
            'title' => $event['database']['title'] ?? null,
            'message' => $event['database']['message'] ?? null,
        };

        return $this->normalise($value) === $this->normalise($default) ? null : $value;
    }

    /** Compare wording without tripping over line endings or trailing space. */
    protected function normalise(?string $value): string
    {
        return trim(str_replace("\r\n", "\n", (string) $value));
    }

    protected function authorise(Request $request): void
    {
        abort_unless($request->user()->can('notifications.manage'), 403);
    }
}
