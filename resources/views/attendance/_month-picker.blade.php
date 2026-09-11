@php $action = $action ?? url()->current(); @endphp

<form method="GET" action="{{ $action }}" class="flex items-center gap-2">
    <select name="month" class="form-select w-32 py-1.5 text-xs" data-auto-submit>
        @foreach (range(1, 12) as $m)
            <option value="{{ $m }}" @selected((int) $month->format('n') === $m)>
                {{ \Illuminate\Support\Carbon::create(null, $m, 1)->format('F') }}
            </option>
        @endforeach
    </select>
    <select name="year" class="form-select w-24 py-1.5 text-xs" data-auto-submit>
        @foreach (range((int) date('Y') + 1, (int) date('Y') - 4) as $y)
            <option value="{{ $y }}" @selected((int) $month->format('Y') === $y)>{{ $y }}</option>
        @endforeach
    </select>
</form>
