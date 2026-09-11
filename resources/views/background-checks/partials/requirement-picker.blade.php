{{--
    The list of documents that can be asked for, as tickable cards.

    Expects:
      $requirements  the catalogue, key => definition
      $ticked        the keys that should start ticked
      $uploaded      key => file name, for items the employee has already provided
--}}
<div class="grid gap-2 sm:grid-cols-2">
    @foreach ($requirements as $key => $requirement)
        @php $file = $uploaded[$key] ?? null; @endphp
        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-slate-300 has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50/50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500/30">
            <input type="checkbox" name="requirements[]" value="{{ $key }}"
                class="form-checkbox mt-0.5 shrink-0" @checked(in_array($key, $ticked, true))>
            <span class="min-w-0">
                <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                    <span class="text-sm font-medium text-slate-800">{{ $requirement['label'] }}</span>
                    @if ($file)
                        <span class="rounded-full bg-emerald-50 px-1.5 py-px text-[10px] font-semibold tracking-wide text-emerald-700 uppercase">Provided</span>
                    @elseif ($requirement['default'])
                        <span class="rounded-full bg-slate-100 px-1.5 py-px text-[10px] font-semibold tracking-wide text-slate-500 uppercase">Standard</span>
                    @endif
                </span>
                <span class="mt-0.5 block text-xs leading-relaxed text-slate-500">{{ $requirement['description'] }}</span>
                @if ($file)
                    <span class="mt-1 block text-xs text-rose-600">Unticking this deletes {{ $file }} from the record.</span>
                @endif
            </span>
        </label>
    @endforeach
</div>
