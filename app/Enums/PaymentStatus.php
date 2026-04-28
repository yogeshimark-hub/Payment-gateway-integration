<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending         = 'pending';
    case Succeeded       = 'succeeded';
    case Failed          = 'failed';
    case RequiresAction  = 'requires_action';
    case Cancelled       = 'cancelled';
}
