<?php
namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class greetingWish extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'Wishing';
    }
    

}