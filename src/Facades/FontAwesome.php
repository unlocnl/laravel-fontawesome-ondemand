<?php

namespace Unloc\FontAwesome\Facades;

use Illuminate\Support\Facades\Facade;

class FontAwesome extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Unloc\FontAwesome\FontAwesome::class;
    }
}
