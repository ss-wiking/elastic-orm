<?php

namespace SsWiking\ElasticOrm\Facades;

use Illuminate\Support\Facades\Facade;

class ElasticOrm extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return 'elastic-orm';
    }
}