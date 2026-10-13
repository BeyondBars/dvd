<?php

namespace App\Support\Enums;

enum EmailTemplateType: string
{
    case Transactional = 'transactional';
    case Marketing = 'marketing';

    public function title(): string
    {
        return match ($this) {
            self::Transactional => 'Transactional',
            self::Marketing => 'Marketing',
        };
    }
}
