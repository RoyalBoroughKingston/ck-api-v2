<?php

namespace App\Docs\Paths\InformationPages;

use App\Docs\Operations\InformationPages\IndexInformationPageOperation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\PathItem;

class InformationPagesIndexPath extends PathItem
{
    /**
     * @param string|null $objectId
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     * @return static
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->route('/information-pages/index')
            ->operations(
                IndexInformationPageOperation::create()
                    ->action(IndexInformationPageOperation::ACTION_POST)
                    ->description(
                        <<<'EOT'
This is an alias of `GET /information-pages` which allows all the query string parameters to be passed
as part of the request body.
EOT
                    )
            );
    }
}
