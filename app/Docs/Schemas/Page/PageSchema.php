<?php

namespace App\Docs\Schemas\Page;

use App\Docs\Schemas\Collection\Category\CollectionCategorySchema;
use App\Docs\Schemas\Collection\Persona\CollectionPersonaSchema;
use App\Docs\Schemas\File\FileSchema;
use App\Models\Page;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class PageSchema extends Schema
{
    /**
     * @param string|null $objectId
     * @return static
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->type(static::TYPE_OBJECT)
            ->properties(
                Schema::string('id')
                    ->format(Schema::TYPE_OBJECT),
                Schema::string('title'),
                Schema::string('content'),
                Schema::integer('order'),
                Schema::boolean('enabled'),
                Schema::string('page_type')
                    ->enum(
                        Page::PAGE_TYPE_INFORMATION,
                        Page::PAGE_TYPE_LANDING
                    ),
                FileSchema::create('image'),
                PageListItemSchema::create('parent'),
                Schema::array('children')
                    ->items(PageListItemSchema::create()),
                Schema::array('collection_categories')
                    ->items(CollectionCategorySchema::create()),
                Schema::array('collection_persona')
                    ->items(CollectionPersonaSchema::create()),
                Schema::string('created_at')
                    ->format(Schema::FORMAT_DATE_TIME),
                Schema::string('updated_at')
                    ->format(Schema::FORMAT_DATE_TIME)
                    ->nullable()
            );
    }
}
