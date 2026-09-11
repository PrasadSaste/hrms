@php
    $user = auth()->user();
    $unread = $user->unreadNotifications()->latest()->take(6)->get();
    $unreadCount = $user->unreadNotifications()->count();
    $employee = $user->employee;

    // The guide for the screen being looked at, when one has been written.
    $pageHelp = App\Models\HelpArticle::forRoute(request()->route()?->getName(), $user);
    $supportAddress = App\Models\Setting::get('company_email');
@endphp

<header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 px-4 backdrop-blur-sm sm:px-6 lg:px-8"
    style="background-color: color-mix(in srgb, var(--surface-topbar) 90%, transparent)">
    <button type="button" data-sidebar-toggle
        class="-ml-1 inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 sm:min-h-0 sm:min-w-0 sm:p-2 lg:hidden"
        aria-label="Open menu">
        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
    </button>

    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium text-slate-900">
            {{ $employee?->full_name ?? $user->name }}
        </p>
        <p class="truncate text-xs text-slate-500">
            {{ $employee?->designation?->name ?? $user->getRoleNames()->map(fn ($r) => \App\Support\Roles::label($r))->implode(', ') }}
            @if ($employee?->branch)
                &middot; {{ $employee->branch->name }}
            @endif
        </p>
    </div>

    {{-- Punching and breaks: not for a joiner whose verification has not cleared --}}
    {{-- The policy, not the permission: it also carries the organisation's
         switch for whether people record their own attendance at all. --}}
    @can('punch', App\Models\Attendance::class)
        @if ($employee && ! $employee->awaitingBackgroundCheck())
            @php
                $today = app(\App\Services\AttendanceService::class)->todayFor($employee);
                $today?->loadMissing(['sessions', 'breaks']);
                $openBreak = $today?->openBreak();
                $punchedIn = (bool) $today?->isPunchedIn();
            @endphp


            <div class="hidden items-center gap-2 sm:flex">
                {{--
                    The counter ticks in the browser from a figure the server
                    worked out, so a page left open all afternoon still shows
                    the truth rather than drifting.
                --}}
                <span class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium tabular-nums text-slate-600"
                    data-worked-timer
                    data-worked-seconds="{{ $today?->workedSecondsSoFar() ?? 0 }}"
                    data-worked-running="{{ $punchedIn && ! $openBreak ? '1' : '0' }}"
                    title="Time at work today, breaks taken out">0h 00m 00s</span>

                @if (! $punchedIn)
                    <x-punch-form :action="route('attendance.check-in')" label="Punch in"
                        variant="success" size="sm">
                        <x-slot:icon><x-icon.check class="size-4" /></x-slot:icon>
                    </x-punch-form>
                @elseif ($openBreak)
                    <form method="POST" action="{{ route('attendance.break.end') }}">
                        @csrf
                        <x-button variant="primary" size="sm">
                            <x-icon.clock class="size-4" /> End {{ strtolower($openBreak->label()) }}
                        </x-button>
                    </form>
                @else
                    <x-button variant="secondary" size="sm" type="button" data-dialog-open="break">
                        <x-icon.clock class="size-4" /> Start break
                    </x-button>

                    <x-punch-form :action="route('attendance.check-out')" label="Punch out"
                        variant="danger" size="sm">
                        <x-slot:icon><x-icon.logout class="size-4" /></x-slot:icon>
                    </x-punch-form>
                @endif
            </div>

        @endif
    @endcan

    {{-- Help --}}
    <div class="relative">
        <button type="button" data-menu-toggle="help" aria-expanded="false"
            class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 sm:min-h-0 sm:min-w-0 sm:p-2"
            aria-label="Help">
            <x-icon.info class="size-5" />
        </button>

        <div data-menu="help"
            class="absolute right-0 z-30 mt-2 hidden w-72 origin-top-right rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg">
            <p class="px-4 pt-2 pb-1 text-xs font-semibold tracking-wide text-slate-400 uppercase">Help</p>

            <a href="{{ route('self-assistance.index') }}"
                class="block px-4 py-2.5 transition hover:bg-slate-50">
                <span class="block text-sm font-medium text-slate-900">Self Assistance</span>
                <span class="block text-xs text-slate-500">Browse the guides</span>
            </a>

            @if ($pageHelp)
                <a href="{{ route('self-assistance.show', $pageHelp) }}"
                    class="block px-4 py-2.5 transition hover:bg-slate-50">
                    <span class="block text-sm font-medium text-slate-900">Help for this page</span>
                    <span class="block text-xs text-slate-500">{{ $pageHelp->title }}</span>
                </a>
            @endif

            <a href="{{ route('self-assistance.index') }}#help-search"
                class="block px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                Search help
            </a>

            <div class="my-1 border-t border-slate-100"></div>

            <a href="{{ $supportAddress ? 'mailto:'.$supportAddress.'?subject='.rawurlencode('Support request from '.$user->name) : route('self-assistance.index') }}"
                class="block px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                Contact support
                @if ($supportAddress)
                    <span class="mt-0.5 block text-xs text-slate-500">{{ $supportAddress }}</span>
                @endif
            </a>

            @can('help.manage')
                <a href="{{ route('help-articles.index') }}"
                    class="block border-t border-slate-100 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                    Manage guides
                </a>
            @endcan
        </div>
    </div>

    {{-- Notifications --}}
    <div class="relative">
        <button type="button" data-menu-toggle="notifications" aria-expanded="false"
            class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 sm:min-h-0 sm:min-w-0 sm:p-2"
            aria-label="Notifications">
            <x-icon.bell class="size-5" />
            @if ($unreadCount > 0)
                <span class="absolute top-1 right-1 flex size-4 items-center justify-center rounded-full bg-rose-500 text-[10px] font-semibold text-white">
                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                </span>
            @endif
        </button>

        <div data-menu="notifications"
            class="absolute right-0 z-30 mt-2 hidden w-80 origin-top-right rounded-xl border border-slate-200 bg-white shadow-lg">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <p class="text-sm font-semibold text-slate-900">Notifications</p>
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="text-xs font-medium link-plain">Mark all read</button>
                    </form>
                @endif
            </div>

            <div class="max-h-80 overflow-y-auto">
                @forelse ($unread as $notification)
                    <a href="{{ route('notifications.read', $notification->id) }}"
                        class="flex gap-3 border-b border-slate-100 px-4 py-3 transition last:border-0 hover:bg-slate-50">
                        <span class="mt-1 size-2 shrink-0 rounded-full bg-brand-500"></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-slate-900">{{ $notification->data['title'] ?? 'Notification' }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ $notification->data['message'] ?? '' }}</span>
                            <span class="mt-1 block text-[11px] text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </a>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-slate-500">You are all caught up.</p>
                @endforelse
            </div>

            <a href="{{ route('notifications.index') }}"
                class="link-plain block border-t border-slate-200 px-4 py-2.5 text-center text-xs font-medium hover:bg-slate-50">
                View all notifications
            </a>
        </div>
    </div>

    {{-- Account menu --}}
    <div class="relative">
        <button type="button" data-menu-toggle="account" aria-expanded="false"
            class="flex min-h-11 items-center justify-center gap-2 rounded-lg px-1 transition hover:bg-slate-100 sm:min-h-0 sm:p-1">
            <x-avatar :name="$user->name" :src="$user->avatarUrl()" size="sm" />
            <svg class="hidden size-4 text-slate-400 sm:block" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <div data-menu="account"
            class="absolute right-0 z-30 mt-2 hidden w-56 origin-top-right rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg">
            <div class="border-b border-slate-100 px-4 py-3">
                <p class="truncate text-sm font-medium text-slate-900">{{ $user->name }}</p>
                <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                <div class="mt-2 flex flex-wrap gap-1">
                    @foreach ($user->getRoleNames() as $role)
                        <x-badge color="brand">{{ \App\Support\Roles::label($role) }}</x-badge>
                    @endforeach
                </div>
            </div>

            <a href="{{ route('profile.edit') }}"
                class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                <x-icon.user class="size-4 text-slate-400" /> My profile
            </a>

            @if ($employee)
                <a href="{{ route('employees.show', $employee) }}"
                    class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                    <x-icon.badge class="size-4 text-slate-400" /> My employee record
                </a>
            @endif

            <div class="my-1 border-t border-slate-100"></div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-rose-600 transition hover:bg-rose-50">
                    <x-icon.logout class="size-4" /> Sign out
                </button>
            </form>
        </div>
    </div>
</header>
