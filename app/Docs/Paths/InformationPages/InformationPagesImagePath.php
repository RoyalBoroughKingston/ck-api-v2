<?php

namespace App\Docs\Paths\InformationPages;

use App\Docs\Operations\InformationPages\ImageInformationPageOperation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter;
use GoldSpecDigital\ObjectOrientedOAS\Objects\PathItem;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class InformationPagesImagePath extends PathItem
{
    /**
     * @param string|null $objectId
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     * @return static
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->route('/information-pages/{InformationPage}/image.png')
            ->parameters(
                Parameter::path()
                    ->name('InformationPage')
                    ->description('The ID or slug of the InformationPage')
                    ->required()
                    ->schema(Schema::string())
            )
            ->operations(
                ImageInformationPageOperation::create()
            );
    }
}
