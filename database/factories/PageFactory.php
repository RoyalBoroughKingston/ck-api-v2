<?php



namespace Database\Factories;

use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Collection;
use App\Models\File;
use App\Models\Page;
use Illuminate\Support\Str;

class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $title = $this->faker->unique()->words(3, true);

    return [
        'title' => $title,
        'slug' => Str::slug($title),
        'content' => [
            'introduction' => [
                'content' => [
                    [
                        'type' => 'copy',
                        'value' => $this->faker->realText(),
                    ],
                ],
            ],
        ],
        'enabled' => Page::ENABLED,
        'page_type' => Page::PAGE_TYPE_INFORMATION,
    ];
    }

    public function withImage()
    {
        return $this->state(function () {
            ['image_file_id' => function () {
    return File::factory()->create(['filename' => Str::random() . '.png', 'mime_type' => 'image/png']);
}]
        });
    }

    public function disabled()
    {
        return $this->state(['enabled' => Page::DISABLED]);
    }

    public function landingPage()
    {
        return $this->state(['page_type' => Page::PAGE_TYPE_LANDING, 'content' => ['introduction' => ['content' => [['type' => 'copy', 'value' => $this->faker->realText()], ['type' => 'cta', 'title' => $this->faker->sentence, 'description' => $this->faker->realText(), 'url' => $this->faker->url(), 'buttonText' => $this->faker->words(3, true)]]], 'about' => ['content' => [['type' => 'copy', 'value' => $this->faker->realText()], ['type' => 'cta', 'title' => $this->faker->sentence, 'description' => $this->faker->realText(), 'url' => $this->faker->url(), 'buttonText' => $this->faker->words(3, true)], ['type' => 'copy', 'value' => $this->faker->realText()]]], 'info-pages' => ['title' => $this->faker->sentence(), 'content' => [['type' => 'copy', 'value' => $this->faker->realText()]]], 'collections' => ['title' => $this->faker->sentence(), 'content' => [['type' => 'copy', 'value' => $this->faker->realText()]]]]]);
    }

    public function withCollections()
    {
        return $this->afterCreating(function (Page $page, Faker $faker) {
    $page->collections()->attach(Collection::factory()->count(3)->create()->pluck('id')->all());
    $page->save();
})->state([]);
    }

    public function withChildren()
    {
        return $this->afterCreating(function (Page $page, Faker $faker) {
    Page::factory()->count(3)->create()->each(function (Page $child) use ($page) {
        $page->appendNode($child);
    });
})->state([]);
    }

    public function withParent()
    {
        return $this->afterCreating(function (Page $page, Faker $faker) {
    Page::factory()->create()->appendNode($page);
})->state([]);
    }
}
