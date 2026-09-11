<x-app-layout title="Notifications">
    <x-page-header
        title="Notifications"
        subtitle="Every message the system sends, what it says and where it goes">
        <x-slot:actions>
            <x-button variant="secondary" :href="route('settings.edit')">
                <x-icon.cog class="size-4" /> Email settings
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @include('partials.mail-redirect-notice')

    @unless ($emailEnabled)
        <x-alert type="warning" class="mb-4" :dismissible="false">
            Email is switched off for the whole system in
            <a href="{{ route('settings.edit') }}" class="font-medium underline">Settings</a>,
            so nothing below goes out by email until it is switched back on.
            In-app notifications are unaffected.
        </x-alert>
    @endunless

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Notification</th>
                        <th class="w-28 text-center">Email</th>
                        <th class="w-28 text-center">In app</th>
                        <th class="w-36"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $group => $events)
                        <tr class="bg-slate-50/80">
                            <th colspan="4"
                                class="px-4 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                {{ $group }}
                            </th>
                        </tr>

                        @foreach ($events as $key => $event)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('notification-templates.edit', $key) }}"
                                            class="font-medium text-slate-900 hover-ink">
                                            {{ $event['label'] }}
                                        </a>
                                        @if ($event['customised'])
                                            <x-badge color="brand">Edited</x-badge>
                                        @endif
                                    </div>
                                    <p class="mt-0.5 max-w-2xl text-xs text-slate-500">{{ $event['description'] }}</p>
                                </td>

                                @foreach (['mail' => 'email', 'database' => 'in-app'] as $channel => $channelLabel)
                                    <td class="text-center">
                                        @if (in_array($channel, $event['channels'], true))
                                            <form method="POST"
                                                action="{{ route('notification-templates.channels', $key) }}"
                                                class="inline">
                                                @csrf
                                                <input type="hidden" name="channel" value="{{ $channel }}">
                                                <input type="hidden" name="enabled"
                                                    value="{{ $event[$channel.'_enabled'] ? 0 : 1 }}">
                                                <button type="submit"
                                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset transition
                                                        {{ $event[$channel.'_enabled']
                                                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 hover:bg-emerald-100'
                                                            : 'bg-slate-100 text-slate-500 ring-slate-500/20 hover:bg-slate-200' }}"
                                                    title="{{ $event[$channel.'_enabled'] ? 'Stop sending' : 'Start sending' }} this {{ $channelLabel }}">
                                                    <span class="size-1.5 rounded-full
                                                        {{ $event[$channel.'_enabled'] ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                                    {{ $event[$channel.'_enabled'] ? 'On' : 'Off' }}
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs text-slate-400" title="This notification has no {{ $channelLabel }} version">—</span>
                                        @endif
                                    </td>
                                @endforeach

                                <td class="num">
                                    <x-button variant="secondary" size="sm"
                                        :href="route('notification-templates.edit', $key)">
                                        <x-icon.pencil class="size-3.5" /> Edit wording
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
