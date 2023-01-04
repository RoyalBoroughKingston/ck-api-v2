<?php

namespace App\Docs\Operations\Pages;

use App\Docs\Schemas\Page\PageSchema;
use App\Docs\Schemas\Page\UpdatePageSchema;
use App\Docs\Schemas\ResourceSchema;
use App\Docs\Tags\PagesTag;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Operation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\RequestBody;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Response;

class UpdatePageOperation extends Operation
{
    /**
     * @param  string|null  $objectId
     * @return static
     *
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->action(static::ACTION_PUT)
            ->tags(PagesTag::create())
            ->summary('Update an information page')
            ->description('**Permission:** `Global Admin`')
            ->requestBody(
                RequestBody::create()
                    ->required()
                    ->content(
                        MediaType::json()->schema(
                            UpdatePageSchema::create()
                        )
                    )
            )
            ->responses(
                Response::created()->content(
                    MediaType::json()->schema(
                        ResourceSchema::create(null, PageSchema::create())
                    )
                )
            );
    }
}
