@php $t = $summary['totals']; @endphp

<dl class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
    <div>
        <dt class="text-xs tracking-wide text-slate-500 uppercase">Working days</dt>
        <dd class="mt-1 text-2xl font-semibold text-slate-900">{{ $t['working_days'] }}</dd>
    </div>
    <div>
        <dt class="text-xs tracking-wide text-slate-500 uppercase">Present</dt>
        <dd class="mt-1 text-2xl font-semibold text-emerald-600">
            {{ rtrim(rtrim(number_format($t['present_days'], 1), '0'), '.') }}
        </dd>
    </div>
    <div>
        <dt class="text-xs tracking-wide text-slate-500 uppercase">Absent</dt>
        <dd class="mt-1 text-2xl font-semibold text-rose-600">
            {{ rtrim(rtrim(number_format($t['absent_days'], 1), '0'), '.') }}
        </dd>
    </div>
    <div>
        <dt class="text-xs tracking-wide text-slate-500 uppercase">On leave</dt>
        <dd class="mt-1 text-2xl font-semibold text-sky-600">
            {{ rtrim(rtrim(number_format($t['paid_leave_days'] + $t['unpaid_leave_days'], 1), '0'), '.') }}
        </dd>
    </div>
    <div>
        <dt class="text-xs tracking-wide text-slate-500 uppercase">Late arrivals</dt>
        <dd class="mt-1 text-2xl font-semibold text-amber-600">{{ $t['late_days'] }}</dd>
    </div>
    <div>
        <dt class="text-xs tracking-wide text-slate-500 uppercase">Hours worked</dt>
        <dd class="mt-1 text-2xl font-semibold text-slate-900">{{ $t['worked_hours'] }}</dd>
    </div>
</dl>
