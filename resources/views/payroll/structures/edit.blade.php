<x-app-layout title="Edit salary structure">
    <x-page-header :title="'Edit structure for ' . $structure->employee->full_name"
        :subtitle="'Effective from ' . $structure->effective_from->format('d M Y')"
        :back="route('salary-structures.show', $structure)" />

    <form method="POST" action="{{ route('salary-structures.update', $structure) }}">
        @csrf @method('PUT')
        @include('payroll.structures._form')
    </form>
</x-app-layout>
