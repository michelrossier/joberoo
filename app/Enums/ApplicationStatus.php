<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case New = 'new';
    case Reviewed = 'reviewed';
    case Interview = 'interview';
    case Accepted = 'accepted';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Neu',
            self::Reviewed => 'In Bearbeitung',
            self::Interview => 'Interview',
            self::Accepted => 'Angenommen',
            self::Dismissed => 'Abgelehnt',
        };
    }
}
