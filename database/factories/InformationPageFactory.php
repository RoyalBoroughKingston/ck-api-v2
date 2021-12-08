<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\File;
use App\Models\InformationPage;
use Faker\Generator as Faker;
use Illuminate\Support\Str;

$factory->define(InformationPage::class, function (Faker $faker) {
    return [
        'title' => $faker->sentence(),
        'content' => $faker->realText(),
        'order' => 0,
        'enabled' => InformationPage::ENABLED,
        'parent_id' => null,
    ];
});

$factory->state(InformationPage::class, 'withImage', [
    'image_file_id' => function () {
        return factory(File::class)->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);
    },
]);
