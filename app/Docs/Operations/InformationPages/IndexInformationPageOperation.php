<?php

namespace App\Docs\Operations\InformationPages;

use App\Docs\Parameters\FilterParameter;
use App\Docs\Parameters\SortParameter;
use App\Docs\Schemas\AllSchema;
use App\Docs\Schemas\InformationPage\InformationPageSchema;
use App\Docs\Tags\InformationPagesTag;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Operation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Response;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class IndexInformationPageOperation extends Operation
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
            ->summary('List all the information pages')
            ->description('**Permission:** `Open`')
            ->noSecurity()
            ->parameters(
                FilterParameter::create(null, 'parent_id')
                    ->description('Filter by Parent ID to return descendant nodes')
                    ->schema(Schema::string()),
                FilterParameter::create(null, 'title')
                    ->description('Title to filter by')
                    ->schema(Schema::string()),
                SortParameter::create(null, ['title'], 'title')
            )
            ->responses(
                Response::ok()->content(
                    MediaType::json()->schema(
                        AllSchema::create(null, InformationPageSchema::create())
                    )
                )
            );
    }
}
