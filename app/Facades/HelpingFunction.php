<?php

namespace App\Facades;
use Illuminate\Support\Facades\Facade;
use App\Contracts\HelpingInterface;

class HelpingFunction extends Facade
{
    protected static function getFacadeAccessor()
    {
        // return 'helping-facade';
        return HelpingInterface::class;
    }
}
