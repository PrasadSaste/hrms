@php
    $isBuiltIn = in_array($role->name ?? '', App\Support\Roles::all(), true);
    $isSuperAdmin = ($role->name ?? '') === App\Support\Roles::SUPER_ADMIN;
@endphp

<div class="space-y-6">
    @if (! $role->exists)
        <x-card title="Role">
            <x-input label="Role name" name="name" required placeholder="team-lead" class="sm:w-80"
                help="Lowercase letters, numbers and hyphens only. This is the identifier used in code." />
        </x-card>
    @endif

    @if ($isSuperAdmin)
        <x-alert type="info" :dismissible="false">
            The super admin role always holds every permission, including any added later.
            Its permission list cannot be narrowed.
        </x-alert>
    @endif

    {{-- Three columns on a wide screen, and the permission name only on hover,
         so the whole catalogue fits without a long scroll. --}}
    <div class="columns-1 gap-4 md:columns-2 xl:columns-3">
        @foreach ($catalogue as $module => $permissions)
            @php $granted = collect($permissions)->keys()->intersect(old('permissions', $assigned))->count(); @endphp
            <x-card class="mb-4 break-inside-avoid" :padded="false">
                <div class="flex items-center justify-between gap-2 border-b border-slate-200 px-4 py-2.5">
                    <h2 class="text-sm font-semibold text-slate-900">{{ $module }}</h2>
                    <span class="flex items-center gap-2">
                        <span data-permission-count class="text-xs tabular-nums text-slate-400">
                            {{ $isSuperAdmin ? count($permissions) : $granted }}/{{ count($permissions) }}
                        </span>
                        @unless ($isSuperAdmin)
                            <button type="button" data-permission-toggle
                                class="rounded px-1.5 py-0.5 text-xs font-medium text-brand-600 transition hover:bg-brand-50">
                                All
                            </button>
                        @endunless
                    </span>
                </div>

                <div class="space-y-2 px-4 py-3">
                    @foreach ($permissions as $permission => $label)
                        <label class="flex cursor-pointer items-center gap-2.5" title="{{ $permission }}">
                            <input type="checkbox" name="permissions[]" value="{{ $permission }}"
                                class="form-checkbox shrink-0"
                                @checked(in_array($permission, old('permissions', $assigned)) || $isSuperAdmin)
                                @disabled($isSuperAdmin)>
                            <span class="text-sm text-slate-700">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </x-card>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-2">
        <x-button size="lg" :disabled="$isSuperAdmin">
            {{ $role->exists ? 'Save permissions' : 'Create role' }}
        </x-button>
        <x-button href="{{ route('roles.index') }}" variant="secondary" size="lg">Cancel</x-button>
    </div>
</div>
