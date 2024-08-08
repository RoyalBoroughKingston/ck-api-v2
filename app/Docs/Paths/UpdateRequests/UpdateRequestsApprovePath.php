<?php

namespace App\Docs\Paths\UpdateRequests;

use App\Docs\Operations\UpdateRequests\ApproveUpdateRequestOperation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter;
use GoldSpecDigital\ObjectOrientedOAS\Objects\PathItem;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class UpdateRequestsApprovePath extends PathItem
{
    /**
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->route('/update-requests/{update_request}/approve?action={action}')
            ->parameters(
                Parameter::path()
                    ->name('update_request')
                    ->description('The ID of the update request')
                    ->required()
                    ->schema(Schema::string()->format(Schema::FORMAT_UUID)),
                Parameter::query()
                    ->name('action')
                    ->description('The follow on action after approving')
                    ->schema(Schema::string()->enum('show', 'edit'))
            )
            ->operations(
                ApproveUpdateRequestOperation::create()
            );
    }
}
