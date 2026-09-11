{{--
    A miniature of the real layout — navigation column, top bar, a stat row and
    a chart — drawn entirely from the theme variables. The script repaints those
    variables on this element as the pickers move, so what is shown is the same
    arithmetic the server does, not an approximation of it.
--}}
<div class="mt-3 overflow-hidden rounded-xl ring-1 ring-slate-200" data-theme-preview
    style="{!! $brand->cssVariables() !!}">
    <div class="flex" style="background-color: var(--surface-app)">

        {{-- Navigation column --}}
        <div class="w-40 shrink-0 border-r p-3"
            style="background-color: var(--surface-nav); border-color: var(--surface-nav-border)">
            <div class="flex items-center gap-2">
                <span class="flex size-6 items-center justify-center rounded-md text-[10px] font-bold"
                    style="background-image: linear-gradient(135deg, var(--color-brand-600), var(--color-accent-600));
                           color: var(--color-brand-foreground)">BS</span>
                <span class="truncate text-[11px] font-semibold" style="color: var(--surface-nav-text)">
                    {{ $settings['company_name'] ?? config('app.name') }}
                </span>
            </div>

            <div class="mt-3 space-y-1">
                <div class="flex items-center gap-2 rounded-md px-2 py-1.5"
                    style="background-color: var(--surface-nav-active); color: var(--surface-nav-active-text)">
                    <span class="size-3 rounded-sm" style="background-color: var(--surface-nav-active-icon)"></span>
                    <span class="text-[10px] font-medium">Dashboard</span>
                </div>
                @foreach (['Attendance', 'Leave', 'Payroll'] as $label)
                    <div class="flex items-center gap-2 rounded-md px-2 py-1.5" style="color: var(--surface-nav-text)">
                        <span class="size-3 rounded-sm" style="background-color: var(--surface-nav-icon)"></span>
                        <span class="text-[10px]">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- The page itself --}}
        <div class="min-w-0 flex-1">
            <div class="flex h-9 items-center gap-2 border-b border-slate-200 px-3"
                style="background-color: var(--surface-topbar)">
                <span class="h-2 w-20 rounded-full bg-slate-200"></span>
                <span class="ml-auto size-5 rounded-full" style="background-color: var(--color-brand-100)"></span>
            </div>

            <div class="space-y-2.5 p-3">
                <div class="grid grid-cols-3 gap-2">
                    @foreach ([['brand', 'Present'], ['accent', 'On leave'], ['tertiary', 'Late']] as [$token, $label])
                        <div class="rounded-lg border border-slate-200 bg-white p-2">
                            <div class="flex items-center gap-1.5">
                                <span class="size-4 rounded" style="background-color: var(--color-{{ $token }}-100)"></span>
                                <span class="text-[8px] tracking-wide text-slate-500 uppercase">{{ $label }}</span>
                            </div>
                            <p class="mt-1 text-sm font-semibold" style="color: var(--color-{{ $token }}-700)">
                                {{ ['22 / 28', '4', '2'][$loop->index] }}
                            </p>
                        </div>
                    @endforeach
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-2.5">
                    <p class="text-[8px] font-medium tracking-wide text-slate-500 uppercase">Working time breakdown</p>
                    <div class="mt-2 space-y-1.5">
                        @foreach ([['brand', '82%'], ['accent', '68%'], ['tertiary', '41%']] as [$token, $width])
                            <div class="h-2 rounded-full bg-slate-100">
                                <div class="h-2 rounded-full"
                                    style="width: {{ $width }}; background-color: var(--color-{{ $token }}-600)"></div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-2">
                    <span class="rounded-md px-2.5 py-1 text-[9px] font-medium"
                        style="background-color: var(--color-brand-600); color: var(--color-brand-foreground)">Primary action</span>
                    <span class="rounded-md px-2.5 py-1 text-[9px] font-medium"
                        style="background-color: var(--color-accent-50); color: var(--color-accent-700)">Secondary</span>
                    <span class="rounded-md px-2.5 py-1 text-[9px] font-medium"
                        style="background-color: var(--color-tertiary-50); color: var(--color-tertiary-700)">Tertiary</span>
                </div>
            </div>
        </div>
    </div>
</div>

<p class="form-help">
    The layout as it will look. Nothing is saved until you press Save settings.
</p>
