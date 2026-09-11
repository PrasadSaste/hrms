<x-app-layout title="Roles and permissions">
    <x-page-header title="Roles and permissions" subtitle="What each role can reach, screen by screen">
        <x-slot:actions>
            @if ($canManage)
                <x-button href="{{ route('roles.create') }}"><x-icon.plus class="size-4" /> New role</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($roles as $role)
            <x-card class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold text-slate-900">{{ $labels[$role->name] ?? $role->name }}</h2>
                        <p class="font-mono text-xs text-slate-500">{{ $role->name }}</p>
                    </div>
                    @if ($role->name === App\Support\Roles::SUPER_ADMIN)
                        <x-badge color="violet">Full access</x-badge>
                    @endif
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Users</dt>
                        <dd class="mt-0.5 text-xl font-semibold text-slate-900">{{ $role->users_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Permissions</dt>
                        <dd class="mt-0.5 text-xl font-semibold text-slate-900">{{ $role->permissions_count }}</dd>
                    </div>
                </dl>

                @if ($canManage)
                    <div class="mt-4 flex gap-2">
                        <x-button href="{{ route('roles.edit', $role) }}" variant="secondary" size="sm" class="flex-1">
                            Edit permissions
                        </x-button>
                        @unless (in_array($role->name, App\Support\Roles::all(), true))
                            <form method="POST" action="{{ route('roles.destroy', $role) }}">
                                @csrf @method('DELETE')
                                <x-button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50"
                                    data-confirm="Delete the {{ $role->name }} role?"><x-icon.trash class="size-3.5" /> Delete</x-button>
                            </form>
                        @endunless
                    </div>
                @endif
            </x-card>
        @endforeach
    </div>
</x-app-layout>
