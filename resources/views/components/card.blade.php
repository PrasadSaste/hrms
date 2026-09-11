@props(['title' => null, 'subtitle' => null, 'padded' => true])

<div {{ $attributes->class(['card']) }}>
    @if ($title || isset($actions))
        <div class="card-header">
            <div>
                @if ($title)
                    <h2 class="card-title">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div @class(['card-body' => $padded])>
        {{ $slot }}
    </div>
</div>
