<?php

namespace App\Docs\Operations\InformationPages;

use App\Docs\Schemas\InformationPage\InformationPageExtendedSchema;
use App\Docs\Schemas\ResourceSchema;
use App\Docs\Tags\InformationPagesTag;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Operation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Response;

class ShowInformationPageOperation extends Operation
{
    /**
     * @param string|null $objectId
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     * @return static
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->action(static::ACTION_GET)
            ->tags(InformationPagesTag::create())
            ->summary('Get a specific information page')
            ->description('**Permission:** `Open`')
            ->noSecurity()
            ->responses(
                Response::ok()->content(
                    MediaType::json()->schema(
                        ResourceSchema::create(null, InformationPageExtendedSchema::create())
                    )
                )
            );
    }
}
