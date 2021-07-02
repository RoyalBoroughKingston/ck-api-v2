<?php

use App\Models\Service;
use App\Models\SocialMedia;
use App\Models\Taxonomy;
use Faker\Generator as Faker;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

$factory->define(Service::class, function (Faker $faker) {
    $name = $faker->unique()->company;

    return [
        'organisation_id' => function () {
            return factory(\App\Models\Organisation::class)->create()->id;
        },
        'slug' => Str::slug($name) . '-' . mt_rand(1, 1000),
        'name' => $name,
        'type' => Service::TYPE_SERVICE,
        'status' => Service::STATUS_ACTIVE,
        'intro' => $faker->sentence,
        'description' => $faker->sentence,
        'is_free' => true,
        'url' => $faker->url,
        'contact_name' => $faker->name,
        'contact_phone' => random_uk_phone(),
        'contact_email' => $faker->safeEmail,
        'show_referral_disclaimer' => false,
        'referral_method' => Service::REFERRAL_METHOD_NONE,
        'last_modified_at' => Date::now(),
    ];
});

$factory->afterCreatingState(Service::class, 'withOfferings', function (Service $service, Faker $faker) {
    $service->offerings()->create([
        'offering' => 'Weekly club',
        'order' => 1,
    ]);
});

$factory->afterCreatingState(Service::class, 'withUsefulInfo', function (Service $service, Faker $faker) {
    $service->usefulInfos()->create([
        'title' => 'Did You Know?',
        'description' => 'This is a test description',
        'order' => 1,
    ]);
});

$factory->afterCreatingState(Service::class, 'withSocialMedia', function (Service $service, Faker $faker) {
    $service->socialMedias()->create([
        'type' => SocialMedia::TYPE_INSTAGRAM,
        'url' => 'https://www.instagram.com/ayupdigital/',
    ]);
});

$factory->afterCreatingState(Service::class, 'withCustomEligibilities', function (Service $service, Faker $faker) {
    $service->eligibility_age_group_custom = 'custom age group';
    $service->eligibility_disability_custom = 'custom disability';
    $service->eligibility_gender_custom = 'custom gender';
    $service->eligibility_income_custom = 'custom income';
    $service->eligibility_language_custom = 'custom language';
    $service->eligibility_ethnicity_custom = 'custom ethnicity';
    $service->eligibility_housing_custom = 'custom housing';
    $service->eligibility_other_custom = 'custom other';
    $service->save();
});

$factory->afterCreatingState(Service::class, 'withEligibilityTaxonomies', function (Service $service) {
    // Loop through each top level child of service eligibility taxonomy
    Taxonomy::serviceEligibility()->children->each((function ($topLevelChild) use ($service) {
        // And for each top level child, attach one of its children to the service
        $service->serviceEligibilities()->create([
            'taxonomy_id' => $topLevelChild->children->first()->id,
        ]);
    }));
});

$factory->afterCreatingState(Service::class, 'withCategoryTaxonomies', function (Service $service) {
    $service->syncTaxonomyRelationships(collect([factory(Taxonomy::class)->create()]));
});
