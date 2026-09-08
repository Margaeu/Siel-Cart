@php
    use App\Filament\Widgets\InventoryManagement;
@endphp

{{--
    Filament's stylesheet has no class that tints a table row, and the panel
    builds its CSS without this app's Tailwind, so the highlight for rows that
    need restocking is defined here, alongside the only table that uses it.
--}}
<x-filament-widgets::widget class="fi-wi-table">
    <style>
        .fi-ta-row.{{ InventoryManagement::ALERT_ROW_CLASS }},
        .fi-ta-record.{{ InventoryManagement::ALERT_ROW_CLASS }} {
            background-color: var(--danger-50);
        }

        .dark .fi-ta-row.{{ InventoryManagement::ALERT_ROW_CLASS }},
        .dark .fi-ta-record.{{ InventoryManagement::ALERT_ROW_CLASS }} {
            background-color: color-mix(in oklab, var(--danger-400) 10%, transparent);
        }
    </style>

    {{ $this->table }}
</x-filament-widgets::widget>
