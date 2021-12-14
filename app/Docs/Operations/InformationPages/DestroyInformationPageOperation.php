<?php

namespace App\Docs\Operations\InformationPages;

use App\Docs\Responses\ResourceDeletedResponse;
use App\Docs\Tags\InformationPagesTag;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Operation;

class DestroyInformationPageOperation extends Operation
{
    /**
     * @param string|null $objectId
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     * @return static
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->action(static::ACTION_DELETE)
            ->tags(InformationPagesTag::create())
            ->summary('Delete a specific information page')
            ->description('**Permission:** `Global Admin`')
            ->responses(
                ResourceDeletedResponse::create(null, 'information page')
            );
    }
}
