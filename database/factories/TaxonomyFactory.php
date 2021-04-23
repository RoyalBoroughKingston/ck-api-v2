<?php

use App\Models\Taxonomy;
use Faker\Generator as Faker;

$factory->define(Taxonomy::class, function (Faker $faker) {
    $name = $faker->unique()->words(3, true);

    return [
        'name' => $name,
        'parent_id' => Taxonomy::category()->id,
        'order' => 0,
        'depth' => 1,
    ];
});
