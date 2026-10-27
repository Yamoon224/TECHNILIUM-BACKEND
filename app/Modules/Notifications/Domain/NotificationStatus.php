<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain;

enum NotificationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
