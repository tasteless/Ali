<?php

namespace Hujing\Ali\Facades;

use Illuminate\Support\Facades\Facade;

class Ali extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'ali';
    }
}
