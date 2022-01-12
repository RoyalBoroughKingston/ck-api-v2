<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\File;
use App\Models\Page;
use Faker\Generator as Faker;
use Illuminate\Support\Str;

$factory->define(Page::class, function (Faker $faker) {
    return [
        'title' => $faker->sentence(),
        'content' => $faker->realText(),
        'enabled' => Page::ENABLED,
    ];
});

$factory->state(Page::class, 'withImage', [
    'image_file_id' => function () {
        return factory(File::class)->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);
    },
]);

$factory->state(Page::class, 'disabled', [
    'enabled' => Page::DISABLED,
]);

$factory->afterCreatingState(Page::class, 'withParent', function (Page $page, Faker $faker) {
    factory(Page::class)->create()->appendNode($page);
});

$factory->afterCreatingState(Page::class, 'withChildren', function (Page $page, Faker $faker) {
    factory(Page::class, 3)->create()->each(function (Page $child) use ($page) {
        $page->appendNode($child);
    });
});
