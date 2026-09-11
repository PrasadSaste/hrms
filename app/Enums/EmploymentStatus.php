<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Probation = 'probation';
    case Permanent = 'permanent';
    case NoticePeriod = 'notice_period';
    case Resigned = 'resigned';
    case Terminated = 'terminated';
    case Retired = 'retired';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public function color(): string
    {
        return match ($this) {
            self::Probation => 'amber',
            self::Permanent => 'emerald',
            self::NoticePeriod => 'orange',
            self::Resigned, self::Terminated, self::Retired => 'rose',
        };
    }

    public function isOnRoll(): bool
    {
        return in_array($this, [self::Probation, self::Permanent, self::NoticePeriod], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
