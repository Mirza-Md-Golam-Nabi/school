<?php

namespace App\Filament\Concerns;

use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

/**
 * The "Card Fields" modal pieces shared by the student and teacher ID card
 * pages: tick which fields a side prints, then drag them into order.
 */
trait ConfiguresIdCardFields
{
    /**
     * The checkboxes decide which fields a side prints. Ticking or unticking
     * one keeps that side's order list in step: a newly ticked field joins the
     * end, an unticked one drops out, and the rest keep their arranged order.
     *
     * @param  array<string, string>  $options
     * @param  array<string, string>  $descriptions
     */
    protected function cardFieldsCheckboxList(string $name, string $orderName, string $label, array $options, int $limit, array $descriptions = []): CheckboxList
    {
        return CheckboxList::make($name)
            ->label($label)
            ->options($options)
            ->descriptions($descriptions)
            ->columns(['default' => 2, 'sm' => 3])
            ->rules(['array', "max:{$limit}"])
            ->validationMessages(['max' => "কার্ডের এক পাশে সর্বোচ্চ {$limit}টা তথ্য রাখা যায়।"])
            ->live()
            ->afterStateUpdated(function (Get $get, Set $set, ?array $state) use ($orderName): void {
                $selected = $state ?? [];

                $order = collect($get($orderName) ?? [])
                    ->filter(fn (array $item): bool => in_array($item['field'] ?? null, $selected, true));

                foreach (array_diff($selected, $order->pluck('field')->all()) as $newlySelected) {
                    $order->put((string) Str::uuid(), ['field' => $newlySelected]);
                }

                $set($orderName, $order->all());
            });
    }

    /**
     * The selected fields of one side as a drag-to-reorder list — the card
     * prints them top to bottom in this order.
     *
     * @param  array<string, string>  $options
     */
    protected function cardFieldsOrderRepeater(string $name, string $label, array $options): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->helperText('টেনে বা তীর চিহ্নে চেপে উপরে-নিচে সরান — কার্ডে এই সিরিয়ালেই ছাপা হবে।')
            ->schema([
                Select::make('field')
                    ->hiddenLabel()
                    ->options($options)
                    ->selectablePlaceholder(false)
                    ->disabled()
                    ->dehydrated(),
            ])
            ->addable(false)
            ->deletable(false)
            ->reorderableWithButtons()
            ->hidden(fn (?array $state): bool => blank($state));
    }

    /**
     * @param  array<int, BackedEnum>  $fields
     * @return array<int, array{field: string}>
     */
    protected function orderItems(array $fields): array
    {
        return array_map(fn (BackedEnum $field): array => ['field' => (string) $field->value], $fields);
    }

    /**
     * The ticked fields arranged as the order list has them. A ticked field
     * the order list somehow lacks is appended rather than silently dropped.
     *
     * @param  array<int, string>  $selected
     * @param  array<int|string, array{field?: string}>  $order
     * @return array<int, string>
     */
    protected function orderedSelection(array $selected, array $order): array
    {
        $arranged = array_intersect(array_column($order, 'field'), $selected);

        return array_values(array_unique([...$arranged, ...$selected]));
    }
}
