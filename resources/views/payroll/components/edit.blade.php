<x-app-layout :title="'Edit ' . $salaryComponent->name">
    <x-page-header :title="'Edit ' . $salaryComponent->name" :back="route('salary-components.index')" />
    <form method="POST" action="{{ route('salary-components.update', $salaryComponent) }}">
        @csrf @method('PUT')
        @include('payroll.components._form')
    </form>
</x-app-layout>
