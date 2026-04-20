<?php

declare(strict_types=1);

namespace App\Enums;

enum PipelineStage: string
{
    case Receive = 'receive';
    case Visual = 'visual';
    case SerialAssign = 'serial_assign';
    case Tech = 'tech';
    case Qa = 'qa';
    case Shelf = 'shelf';

    public function label(): string
    {
        return match ($this) {
            self::Receive => 'Receive',
            self::Visual => 'Visual Inspection',
            self::SerialAssign => 'Serial Assignment',
            self::Tech => 'Tech Inspection',
            self::Qa => 'QA',
            self::Shelf => 'Shelf',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Receive => self::Visual,
            self::Visual => self::SerialAssign,
            self::SerialAssign => self::Tech,
            self::Tech => self::Qa,
            self::Qa => self::Shelf,
            self::Shelf => null,
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Shelf;
    }
}
