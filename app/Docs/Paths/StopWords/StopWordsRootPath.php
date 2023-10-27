<?php

namespace App\Docs\Paths\StopWords;

use App\Docs\Operations\StopWords\IndexStopWordOperation;
use App\Docs\Operations\StopWords\UpdateStopWordOperation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\PathItem;

class StopWordsRootPath extends PathItem
{
    /**
     * @return static
     *
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->route('/stop-words')
            ->operations(
                IndexStopWordOperation::create(),
                UpdateStopWordOperation::create()
            );
    }
}
