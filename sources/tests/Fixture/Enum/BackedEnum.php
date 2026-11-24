<?php

declare(strict_types=1);

namespace PommProject\Foundation\Tests\Fixture\Enum;

enum BackedEnum: string
{
    case A = 'a';
    case NUMERIC = '42.5';
}
