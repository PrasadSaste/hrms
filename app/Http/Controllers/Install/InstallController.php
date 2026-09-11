<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\BrandPalette;
use App\Services\EnvironmentFile;
use App\Services\Installer;
use App\Support\SystemRequirements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Throwable;

/**
 * The setup wizard: four screens between a fresh deployment and a working HRMS.
 *
 * Requirements, database, organisation, administrator. The controller only
 * asks and validates — every answer is applied by {@see Installer}, so the
 * command line installer reaches the same state by the same route.
 *
 * Each step is entered through `guard()`, which sends anybody who arrives out
 * of order to the step actually outstanding. That is not tidiness: the
 * organisation form writes rows, so it must not be reachable before the schema
 * that holds them exists.
 */
class InstallController extends Controller
{
    public function __construct(
        protected Installer $installer,
        protected EnvironmentFile $environment,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Step one: can this machine run it
    |--------------------------------------------------------------------------
    */

    public function index(SystemRequirements $requirements): View
    {
        return view('install.requirements', [
            'step' => 'requirements',
            'groups' => $requirements->grouped(),
            'satisfied' => $requirements->satisfied(),
            'prepared' => $this->installer->prepareEnvironment(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Step two: where the database is
    |--------------------------------------------------------------------------
    */

    public function database(): View|RedirectResponse
    {
        return $this->guard(Installer::STAGE_DATABASE) ?? view('install.database', [
            'step' => 'database',
            'current' => [
                'driver' => $this->environment->get('DB_CONNECTION') ?: 'mysql',
                'host' => $this->environment->get('DB_HOST') ?: '127.0.0.1',
                'port' => $this->environment->get('DB_PORT') ?: '3306',
                'database' => $this->environment->get('DB_DATABASE') ?: 'hrms',
                'username' => $this->environment->get('DB_USERNAME') ?: '',
            ],
        ]);
    }

    public function storeDatabase(Request $request): RedirectResponse
    {
        if ($redirect = $this->guard(Installer::STAGE_DATABASE)) {
            return $redirect;
        }

        $credentials = $request->validate([
            'driver' => ['required', Rule::in(['mysql', 'mariadb', 'sqlite'])],
            'host' => ['required_unless:driver,sqlite', 'nullable', 'string', 'max:255'],
            'port' => ['required_unless:driver,sqlite', 'nullable', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ], [], [
            'database' => $request->input('driver') === 'sqlite' ? 'database file' : 'database name',
        ]);

        // Pressing "Test connection" proves the credentials and changes
        // nothing, so a wrong guess costs nothing either.
        if ($request->boolean('test_only')) {
            $error = $this->installer->testConnection($credentials);

            return $error
                ? back()->withInput($request->except('password'))->withErrors(['database' => $error])
                : back()->withInput($request->except('password'))->with('status', 'The database answered. Carry on.');
        }

        try {
            $this->installer->useDatabase($credentials);
            $this->installer->migrate();
        } catch (Throwable $e) {
            return back()->withInput($request->except('password'))->withErrors(['database' => $e->getMessage()]);
        }

        return redirect()->route('install.organisation')
            ->with('status', 'The database is connected and the tables are in place.');
    }

    /*
    |--------------------------------------------------------------------------
    | Step three: whose system this is
    |--------------------------------------------------------------------------
    */

    public function organisation(): View|RedirectResponse
    {
        return $this->guard(Installer::STAGE_ORGANISATION) ?? view('install.organisation', [
            'step' => 'organisation',
            'timezones' => \DateTimeZone::listIdentifiers(),
            'colours' => BrandPalette::ROLES,
            'current' => [
                'company_name' => '',
                'legal_name' => '',
                'company_email' => '',
                'company_phone' => '',
                'company_website' => '',
                'address_line1' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => 'India',
                'company_tax_id' => '',
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'brand_color' => BrandPalette::DEFAULT,
                'brand_secondary_color' => BrandPalette::DEFAULT_SECONDARY,
                'brand_tertiary_color' => BrandPalette::DEFAULT_TERTIARY,
            ],
        ]);
    }

    public function storeOrganisation(Request $request): RedirectResponse
    {
        if ($redirect = $this->guard(Installer::STAGE_ORGANISATION)) {
            return $redirect;
        }

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'company_email' => ['nullable', 'email', 'max:150'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'company_website' => ['nullable', 'url', 'max:200'],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'company_tax_id' => ['nullable', 'string', 'max:50'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'timezone'],
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_tertiary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // allow_svg because a logo usually is one; it is served through
            // BrandingController with headers that let it draw and nothing else.
            'company_logo' => ['nullable', 'image:allow_svg', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'],
        ]);

        $data['company_address'] = collect([
            $request->input('address_line1'),
            trim(implode(' ', array_filter([$request->input('city'), $request->input('postal_code')]))),
            trim(implode(', ', array_filter([$request->input('state'), $request->input('country')]))),
        ])->filter()->implode("\n");

        if ($request->hasFile('company_logo')) {
            $data['company_logo'] = $request->file('company_logo')->store('branding', 'public');
        } else {
            unset($data['company_logo']);
        }

        $this->installer->saveOrganisation($data);

        return redirect()->route('install.administrator')
            ->with('status', 'Saved. One account to create and you are done.');
    }

    /*
    |--------------------------------------------------------------------------
    | Step four: who runs it
    |--------------------------------------------------------------------------
    */

    public function administrator(): View|RedirectResponse
    {
        return $this->guard(Installer::STAGE_ADMINISTRATOR) ?? view('install.administrator', [
            'step' => 'administrator',
            'company' => Setting::get('company_name'),
        ]);
    }

    public function storeAdministrator(Request $request): RedirectResponse
    {
        if ($redirect = $this->guard(Installer::STAGE_ADMINISTRATOR)) {
            return $redirect;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $admin = $this->installer->createAdministrator($data);

        /*
         * A signed link rather than a flashed message, because this is the one
         * moment the session cannot be trusted to carry anything: until this
         * request the session lived on disk, since the database it is
         * configured to use did not exist. The next request reads the database
         * instead and would find an empty session.
         *
         * Signed with the application key and good for half an hour, so the
         * finish line belongs to whoever just crossed it and to nobody else.
         */
        return redirect()->to(URL::temporarySignedRoute(
            'install.complete',
            now()->addMinutes(30),
            ['admin' => $admin->email],
        ));
    }

    public function complete(Request $request): View|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('login');
        }

        $logo = Setting::get('company_logo');

        return view('install.complete', [
            'step' => 'complete',
            'email' => $request->query('admin'),
            'company' => Setting::get('company_name'),
            'logo' => $logo && Storage::disk('public')->exists($logo),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Order
    |--------------------------------------------------------------------------
    */

    /**
     * Send anybody who arrives at the wrong step to the right one.
     *
     * Returns null when the step asked for is the step due, and a redirect
     * otherwise.
     */
    protected function guard(string $stage): ?RedirectResponse
    {
        $due = $this->installer->stage();

        if ($due === $stage) {
            return null;
        }

        return redirect()->route('install.'.$due)
            ->with('status', 'Finish this step first.');
    }
}
