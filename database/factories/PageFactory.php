<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Collection;
use App\Models\File;
use App\Models\Page;
use Faker\Generator as Faker;
use Illuminate\Support\Str;

$factory->define(Page::class, function (Faker $faker) {
    return [
        'title' => $faker->sentence(),
        'content' => [
            'introduction' => [
                'copy' => [
                    $this->faker->realText(),
                ],
            ],
        ],
        'enabled' => Page::ENABLED,
        'page_type' => Page::PAGE_TYPE_INFORMATION,
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

$factory->state(Page::class, 'landingPage', [
    'page_type' => Page::PAGE_TYPE_LANDING,
    'content' => [
        'introduction' => [
            'copy' => [
                $this->faker->realText(),
            ],
        ],
        'about' => [
            'copy' => [
                $this->faker->realText(),
                $this->faker->realText(),
            ],
        ],
        'info-pages' => [
            'title' => $this->faker->sentence(),
            'copy' => [
                $this->faker->realText(),
            ],
        ],
        'collections' => [
            'title' => $this->faker->sentence(),
            'copy' => [
                $this->faker->realText(),
            ],
        ],
    ],
]);

$factory->afterCreatingState(Page::class, 'withParent', function (Page $page, Faker $faker) {
    factory(Page::class)->create()->appendNode($page);
});

$factory->afterCreatingState(Page::class, 'withChildren', function (Page $page, Faker $faker) {
    factory(Page::class, 3)->create()->each(function (Page $child) use ($page) {
        $page->appendNode($child);
    });
});

$factory->afterCreatingState(Page::class, 'withCollections', function (Page $page, Faker $faker) {
    $page->collections()->attach(factory(Collection::class, 3)->create()->pluck('id')->all());
    $page->save();
});
