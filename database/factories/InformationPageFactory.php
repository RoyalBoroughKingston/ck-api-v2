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
        'enabled' => InformationPage::ENABLED,
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

$factory->state(InformationPage::class, 'disabled', [
    'enabled' => InformationPage::DISABLED,
]);

$factory->afterCreatingState(InformationPage::class, 'withParent', function (InformationPage $page, Faker $faker) {
    factory(InformationPage::class)->create()->appendNode($page);
});

$factory->afterCreatingState(InformationPage::class, 'withChildren', function (InformationPage $page, Faker $faker) {
    factory(InformationPage::class, 3)->create()->each(function (InformationPage $child) use ($page) {
        $page->appendNode($child);
    });
});
