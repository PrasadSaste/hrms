@php
    $user = auth()->user();

    /** A nav item renders only when the user holds at least one of its permissions. */
    $nav = [
        [
            'label' => null,
            'items' => [
                ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'home', 'can' => null],
            ],
        ],
        [
            'label' => 'My workspace',
            'key' => 'self',
            'items' => [
                ['route' => 'my-verification.edit', 'label' => 'My Verification', 'icon' => 'shield', 'can' => 'bgv.complete-own', 'needs_employee' => true, 'needs_bgv' => true],
                ['route' => 'attendance.index', 'label' => 'My Attendance', 'icon' => 'clock', 'can' => 'attendance.view-own', 'needs_employee' => true, 'after_bgv' => true],
                ['route' => 'leave.index', 'label' => 'Leave', 'icon' => 'calendar', 'can' => ['leave.view-own', 'leave.view-team', 'leave.view-all'], 'after_bgv' => true],
                ['route' => 'leave.balance', 'label' => 'Leave Balance', 'icon' => 'scale', 'can' => 'leave.view-own', 'needs_employee' => true, 'after_bgv' => true],
                ['route' => 'payslips.index', 'label' => 'Salary Slips', 'icon' => 'receipt', 'can' => ['payslips.view-own', 'payslips.view-all'], 'after_bgv' => true],
                ['route' => 'my-letters.index', 'label' => 'My Letters', 'icon' => 'document', 'can' => 'letters.view-own', 'needs_employee' => true],
                ['route' => 'my-assets.index', 'label' => 'My Assets', 'icon' => 'tag', 'can' => 'assets.view-own', 'needs_employee' => true],
                ['route' => 'my-settlement', 'label' => 'My Settlement', 'icon' => 'scale', 'can' => 'settlements.view-own', 'needs_employee' => true],
                ['route' => 'tax.mine', 'label' => 'My Income Tax', 'icon' => 'receipt', 'can' => 'tax.declare', 'needs_employee' => true],
                ['route' => 'holidays.index', 'label' => 'Holidays', 'icon' => 'sun', 'can' => 'holidays.view'],
                ['route' => 'announcements.index', 'label' => 'Announcements', 'icon' => 'megaphone', 'can' => 'announcements.view'],
            ],
        ],
        [
            'label' => 'People',
            'key' => 'people',
            'items' => [
                ['route' => 'employees.index', 'label' => 'Employees', 'icon' => 'users', 'can' => 'employees.view'],
                ['route' => 'background-checks.index', 'label' => 'Verification', 'icon' => 'shield', 'can' => 'bgv.view'],
                ['route' => 'companies.index', 'label' => 'Companies', 'icon' => 'badge', 'can' => 'companies.view'],
                ['route' => 'letters.index', 'label' => 'Letters', 'icon' => 'document', 'can' => ['letters.view', 'letters.issue']],
                ['route' => 'assets.index', 'label' => 'Assets', 'icon' => 'tag', 'can' => 'assets.view'],
                ['route' => 'settlements.index', 'label' => 'Settlements', 'icon' => 'scale', 'can' => 'settlements.view'],
                ['route' => 'branches.index', 'label' => 'Branches', 'icon' => 'building', 'can' => 'branches.view'],
                ['route' => 'departments.index', 'label' => 'Departments', 'icon' => 'squares', 'can' => 'departments.view'],
                ['route' => 'designations.index', 'label' => 'Designations', 'icon' => 'badge', 'can' => 'designations.view'],
                ['route' => 'shifts.index', 'label' => 'Shifts', 'icon' => 'clock', 'can' => 'shifts.view'],
            ],
        ],
        [
            'label' => 'Attendance & leave',
            'key' => 'attendance',
            'items' => [
                ['route' => 'attendance.daily', 'label' => 'Daily Roster', 'icon' => 'list', 'can' => ['attendance.view-team', 'attendance.view-all']],
                ['route' => 'attendance.location-alerts', 'label' => 'Location Alerts', 'icon' => 'map-pin', 'can' => ['attendance.view-team', 'attendance.view-all']],
                ['route' => 'attendance.regularizations.index', 'label' => 'Regularisations', 'icon' => 'pencil', 'can' => 'attendance.view-own', 'needs_employee' => true],
                ['route' => 'leave.calendar', 'label' => 'Leave Calendar', 'icon' => 'calendar', 'can' => ['leave.view-team', 'leave.view-all']],
                ['route' => 'leave-types.index', 'label' => 'Leave Types', 'icon' => 'tag', 'can' => 'leave.manage-types'],
                ['route' => 'leave-allocations.index', 'label' => 'Leave Allocations', 'icon' => 'scale', 'can' => 'leave.manage-allocations'],
            ],
        ],
        [
            'label' => 'Payroll',
            'key' => 'payroll',
            'items' => [
                ['route' => 'payroll.index', 'label' => 'Payroll Runs', 'icon' => 'currency', 'can' => 'payroll.view'],
                ['route' => 'salary-structures.index', 'label' => 'Salary Structures', 'icon' => 'layers', 'can' => 'payroll.manage-structures'],
                ['route' => 'salary-components.index', 'label' => 'Salary Components', 'icon' => 'puzzle', 'can' => 'payroll.manage-components'],
                ['route' => 'tax.index', 'label' => 'Income Tax', 'icon' => 'scale', 'can' => 'tax.view'],
            ],
        ],
        [
            'label' => 'Insights',
            'key' => 'reports',
            'items' => [
                ['route' => 'reports.index', 'label' => 'Reports', 'icon' => 'chart', 'can' => ['reports.attendance', 'reports.leave', 'reports.payroll', 'reports.employees']],
            ],
        ],
        [
            'label' => 'Administration',
            'key' => 'admin',
            'items' => [
                ['route' => 'users.index', 'label' => 'User Accounts', 'icon' => 'key', 'can' => 'users.view'],
                ['route' => 'roles.index', 'label' => 'Roles & Permissions', 'icon' => 'shield', 'can' => 'roles.view'],
                ['route' => 'notification-templates.index', 'label' => 'Notifications', 'icon' => 'bell', 'can' => 'notifications.manage'],
                ['route' => 'letter-templates.index', 'label' => 'Letter Wording', 'icon' => 'pencil', 'can' => 'letters.manage-templates'],
                ['route' => 'help-articles.index', 'label' => 'Self Assistance Guides', 'icon' => 'info', 'can' => 'help.manage'],
                ['route' => 'automations.index', 'label' => 'Automations', 'icon' => 'clock', 'can' => 'automations.manage'],
                ['route' => 'data-import.index', 'label' => 'Data Import', 'icon' => 'upload', 'can' => 'data.import'],
                ['route' => 'settings.edit', 'label' => 'Settings', 'icon' => 'cog', 'can' => 'settings.view'],
                ['route' => 'activity.index', 'label' => 'Activity Log', 'icon' => 'history', 'can' => 'activity.view'],
            ],
        ],
    ];

    // An item shows when the user holds one of its permissions and, where the
    // screen is about "my own" data, when their account has an employee record.
    $hasEmployee = $user->employee !== null;

    // Verification only concerns people who have actually been asked for it.
    $hasBackgroundCheck = $hasEmployee && $user->employee->backgroundCheck !== null;

    // Until it clears, the screens about their own attendance, leave and pay
    // stay in the menu but locked, so the person can see what completing it
    // opens up.
    $awaitingBgv = $hasEmployee && $user->employee->awaitingBackgroundCheck();

    $allowed = function (array $item) use ($user, $hasEmployee, $hasBackgroundCheck) {
        if (($item['needs_employee'] ?? false) && ! $hasEmployee) {
            return false;
        }

        if (($item['needs_bgv'] ?? false) && ! $hasBackgroundCheck) {
            return false;
        }

        return $item['can'] === null || $user->canAny((array) $item['can']);
    };
@endphp

{{-- Every colour here comes from the theme, so the navigation column follows
     the background chosen in Settings rather than a hard-coded slate. --}}
<aside data-sidebar
    class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto border-r transition-transform duration-200 lg:translate-x-0"
    style="background-color: var(--surface-nav); border-color: var(--surface-nav-border)">

    <div class="flex h-16 items-center gap-2.5 border-b px-5" style="border-color: var(--surface-nav-border)">
        <a href="{{ route('dashboard') }}" class="min-w-0">
            <x-brand-mark size="md" on-nav />
        </a>
        <button type="button" data-sidebar-toggle
            class="ml-auto rounded p-1 lg:hidden" style="color: var(--surface-nav-muted)" aria-label="Close menu">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav class="space-y-0.5 px-2.5 py-3 pb-10">
        @foreach ($nav as $group)
            @php
                $items = collect($group['items'])
                    ->filter($allowed)
                    ->map(fn (array $item) => $item + ['locked' => $awaitingBgv && ($item['after_bgv'] ?? false)]);
            @endphp

            @continue($items->isEmpty())

            @if (empty($group['label']))
                @foreach ($items as $item)
                    @include('partials.nav-link', ['item' => $item])
                @endforeach
            @else
                @php
                    $groupActive = $items->contains(fn ($i) => request()->routeIs(str_replace('.index', '.*', $i['route'])));
                @endphp
                {{-- A ruled section head, the way a menu on paper is divided. --}}
                <div data-nav-group="{{ $group['key'] }}" data-nav-open="{{ $groupActive ? 'true' : 'false' }}"
                    class="mt-3 border-t pt-2.5 first:mt-0 first:border-t-0 first:pt-0"
                    style="border-color: var(--surface-nav-border)">
                    <button type="button" data-nav-toggle
                        class="flex w-full items-center justify-between px-2.5 pb-1 text-[10.5px] font-semibold uppercase transition"
                        style="color: var(--surface-nav-muted); letter-spacing: 0.08em">
                        {{ $group['label'] }}
                        <svg data-nav-chevron class="size-3.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                    <div data-nav-panel class="mt-1 space-y-0.5">
                        @foreach ($items as $item)
                            @include('partials.nav-link', ['item' => $item])
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>
</aside>
