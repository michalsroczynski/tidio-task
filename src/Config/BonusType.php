<?php

declare(strict_types=1);

namespace App\Config;

enum BonusType: string
{
    case Fixed = 'FIXED';
    case Percentage = 'PERCENTAGE';
}
