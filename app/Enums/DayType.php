<?php

namespace App\Enums;

enum DayType: string
{
    case FullDay = 'full_day';
    case FirstHalf = 'first_half';
    case SecondHalf = 'second_half';

    public function label(): string
    {
        return match ($this) {
            self::FullDay => 'Full Day',
            self::FirstHalf => 'First Half',
            self::SecondHalf => 'Second Half',
        };
    }

    public function factor(): float
    {
        return $this === self::FullDay ? 1.0 : 0.5;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
