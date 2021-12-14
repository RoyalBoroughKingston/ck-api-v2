<?php

namespace App\Docs\Paths\InformationPages;

use App\Docs\Operations\InformationPages\IndexInformationPageOperation;
use App\Docs\Operations\InformationPages\StoreInformationPageOperation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\PathItem;

class InformationPagesRootPath extends PathItem
{
    /**
     * @param string|null $objectId
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     * @return static
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->route('/information-pages')
            ->operations(
                IndexInformationPageOperation::create(),
                StoreInformationPageOperation::create()
            );
    }
}
