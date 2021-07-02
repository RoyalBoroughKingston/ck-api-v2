<?php

namespace App\Docs\Schemas\Taxonomy\ServiceEligibility;

use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class UpdateTaxonomyServiceEligibilitySchema extends Schema
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
            ->required('parent_id', 'name', 'order')
            ->properties(
                Schema::string('parent_id')
                    ->format(Schema::FORMAT_UUID)
                    ->nullable(),
                Schema::string('name'),
                Schema::integer('order')
            );
    }
}
