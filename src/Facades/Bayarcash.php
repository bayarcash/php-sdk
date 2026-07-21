<?php

namespace Bayarcash\Facades;

use Bayarcash\Bayarcash as BayarcashSdk;
use Illuminate\Support\Facades\Facade;

class Bayarcash extends Facade
{
    public static function getFacadeAccessor()
    {
        return BayarcashSdk::class;
    }
}
