<?php

use App\Models\Collection;
use App\Models\File;
use Faker\Generator as Faker;
use Illuminate\Support\Str;

$factory->define(Collection::class, function (Faker $faker) {
    $imageId = factory(File::class)->states('image-svg')->create()->id;
    $name = $faker->sentence(2);

    return [
        'type' => Collection::TYPE_CATEGORY,
        'slug' => Str::slug($name).'-'.mt_rand(1, 1000),
        'name' => $name,
        'meta' => [
            'intro' => $faker->sentence,
            'sideboxes' => [],
            'image_file_id' => $imageId,
        ],
        'order' => Collection::categories()->count() + 1,
        'enabled' => true,
    ];
});

$factory->state(Collection::class, 'typePersona', function (Faker $faker) {
    return [
        'type' => Collection::TYPE_PERSONA,
        'meta' => [
            'intro' => $faker->sentence,
            'subtitle' => $faker->sentence,
            'sideboxes' => [],
        ],
        'order' => Collection::personas()->count() + 1,
    ];
});

$factory->state(Collection::class, 'typeOrganisationEvent', function (Faker $faker) {
    return [
        'type' => Collection::TYPE_ORGANISATION_EVENT,
        'order' => Collection::organisationEvents()->count() + 1,
    ];
});
