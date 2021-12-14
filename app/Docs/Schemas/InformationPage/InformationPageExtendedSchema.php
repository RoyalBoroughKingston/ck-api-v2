<?php

namespace App\Docs\Schemas\InformationPage;

use App\Docs\Schemas\File\FileSchema;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class InformationPageExtendedSchema extends Schema
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
                FileSchema::create('image'),
                InformationPageSchema::create('parent'),
                Schema::array('children')
                    ->items(InformationPageSchema::create()),
                Schema::string('created_at')
                    ->format(Schema::FORMAT_DATE_TIME)
                    ->nullable(),
                Schema::string('updated_at')
                    ->format(Schema::FORMAT_DATE_TIME)
                    ->nullable()
            );
    }
}
