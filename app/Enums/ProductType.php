<?php

namespace App\Enums;

enum ProductType: string
{
    case Course   = 'course';
    case Ebook    = 'ebook';
    case Donation = 'donation';
    case Service  = 'service';
}
