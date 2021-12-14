<?php

namespace App\Docs\Schemas\InformationPage;

use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class UpdateInformationPageSchema extends Schema
{
    /**
     * @param string|null $objectId
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     * @return static
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->type(static::TYPE_OBJECT)
            ->required(
                'title',
                'content',
                'parent_id',
            )
            ->properties(
                Schema::string('title'),
                Schema::string('content'),
                Schema::string('parent_id'),
                Schema::string('image_file_id'),
                Schema::integer('order'),
                Schema::boolean('enabled')
            );
    }
}
