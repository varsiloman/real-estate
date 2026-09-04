<?php

namespace App\Enums;

enum OfferStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
}
