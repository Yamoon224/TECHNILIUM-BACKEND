<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

enum ConditionState: string
{
    case Neuf = 'neuf';
    case Reconditionne = 'reconditionne';
    case Occasion = 'occasion';
}
