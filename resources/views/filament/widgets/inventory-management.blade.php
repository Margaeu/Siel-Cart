@php
    use App\Filament\Widgets\InventoryManagement;
@endphp

{{--
    Filament's stylesheet has no class that tints a table row, and the panel
    builds its CSS without this app's Tailwind, so the highlights for rows that
    need restocking are defined here, alongside the only table that uses them.
    Each row is tinted in its status badge's own colour: red for an empty
    shelf, orange for one that is running down.
--}}
<x-filament-widgets::widget class="fi-wi-table">
    <style>
        .fi-ta-row.{{ InventoryManagement::OUT_OF_STOCK_ROW_CLASS }},
        .fi-ta-record.{{ InventoryManagement::OUT_OF_STOCK_ROW_CLASS }} {
            background-color: var(--danger-50);
            box-shadow: inset 3px 0 0 var(--danger-600);
        }

        .fi-ta-row.{{ InventoryManagement::LOW_STOCK_ROW_CLASS }},
        .fi-ta-record.{{ InventoryManagement::LOW_STOCK_ROW_CLASS }} {
            background-color: var(--warning-50);
            box-shadow: inset 3px 0 0 var(--warning-500);
        }
    </style>

    {{ $this->table }}
</x-filament-widgets::widget>
