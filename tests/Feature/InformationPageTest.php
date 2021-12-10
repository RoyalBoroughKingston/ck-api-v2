<?php

namespace Tests\Feature;

use App\Events\EndpointHit;
use App\Models\Audit;
use App\Models\File;
use App\Models\InformationPage;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class InformationPageTest extends TestCase
{

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create('en_GB');
    }

    /**
     * @test
     */
    public function listEnabledInformationPagesAsGuest200()
    {
        $informationPage = factory(InformationPage::class)->states('withParent', 'withChildren')->create();

        $response = $this->json('GET', '/core/v1/information-pages/index');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'title',
                    'content',
                    'order',
                    'enabled',
                    'image',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function auditCreatedOnList()
    {
        $this->fakeEvents();

        $this->json('GET', '/core/v1/information-pages/index');

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return ($event->getAction() === Audit::ACTION_READ);
        });
    }

    /**
     * @test
     */
    public function listMixedStateInformationPagesAsGuest200()
    {
        $informationPage = factory(InformationPage::class)
            ->states('withImage', 'withParent', 'disabled')
            ->create();

        $response = $this->json('GET', '/core/v1/information-pages/index');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $informationPage->parent->id,
        ]);
        $response->assertJsonMissing([
            'id' => $informationPage->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStateInformationPagesAsAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $informationPage = factory(InformationPage::class)
            ->states('withImage', 'withParent', 'disabled')
            ->create();

        $response = $this->json('GET', '/core/v1/information-pages/index');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $informationPage->parent->id,
        ]);
        $response->assertJsonFragment([
            'id' => $informationPage->id,
        ]);
    }

    /**
     * @test
     */
    public function getEnabledInformationPageAsGuest200()
    {
        $informationPage = factory(InformationPage::class)->states('withImage', 'withParent', 'withChildren')->create();

        $response = $this->json('GET', '/core/v1/information-pages/' . $informationPage->id);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'image' => [
                'id',
                'mime_type',
                'created_at',
                'updated_at',
            ],
            'parent' => [
                'id',
                'title',
                'image',
                'content',
                'order',
                'enabled',
                'created_at',
                'updated_at',
            ],
            'children' => [
                '*' => [
                    'id',
                    'title',
                    'image',
                    'content',
                    'order',
                    'enabled',
                    'created_at',
                    'updated_at',
                ],
            ],
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @test
     */
    public function getDisabledInformationPageAsGuest403()
    {
        $informationPage = factory(InformationPage::class)
            ->states('withImage', 'withParent', 'withChildren', 'disabled')
            ->create();

        $response = $this->json('GET', '/core/v1/information-pages/' . $informationPage->id);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function getDisabledInformationPageAsAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $informationPage = factory(InformationPage::class)
            ->states('withImage', 'withParent', 'withChildren', 'disabled')
            ->create();

        $response = $this->json('GET', '/core/v1/information-pages/' . $informationPage->id);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'image' => [
                'id',
                'mime_type',
                'created_at',
                'updated_at',
            ],
            'parent' => [
                'id',
                'title',
                'image',
                'content',
                'order',
                'enabled',
                'created_at',
                'updated_at',
            ],
            'children' => [
                '*' => [
                    'id',
                    'title',
                    'image',
                    'content',
                    'order',
                    'enabled',
                    'created_at',
                    'updated_at',
                ],
            ],
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @test
     */
    public function getEnabledInformationPageImageAsGuest200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(InformationPage::class)->create();

        $image = Storage::disk('local')->get('/test-data/image.png');

        $image = factory(File::class)->states('pending-assignment')->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);

        $imageResponse = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/png',
            'file' => 'data:image/png;base64,' . base64_encode($image),
        ]);

        $imageId = $this->getResponseContent($imageResponse, 'data.id');

        $informationPage = factory(InformationPage::class)->create([
            'image_file_id' => $imageId,
        ]);

        $response = $this->json('GET', '/core/v1/information-pages/' . $informationPage->id . '/image.png');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals($image, $response->content());
    }

    /**
     * @test
     */
    public function createInformationPageAsGuest403()
    {
        $parentPage = factory(InformationPage::class)->create();

        $data = [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(),
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/information-pages', $data);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function createInformationPageAsAdmin201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(InformationPage::class)->create();

        $data = [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(),
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/information-pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'image',
            'parent',
            'children',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @test
     */
    public function createInformationPageAsAdminWithInvalidData422()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $this->json('POST', '/core/v1/information-pages', [
            'title' => $this->faker->sentence(),
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('POST', '/core/v1/information-pages', [
            'content' => $this->faker->realText(),
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('POST', '/core/v1/information-pages', [
            'title' => '',
            'content' => '',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('POST', '/core/v1/information-pages', [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(),
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('POST', '/core/v1/information-pages', [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(),
            'parent_id' => $this->faker->uuid(),
        ])->assertStatus(Response::HTTP_NOT_FOUND);

        $parentPage = factory(InformationPage::class)->states('withChildren')->create();

        $this->json('POST', '/core/v1/information-pages', [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(),
            'parent_id' => $parentPage->id,
            'order' => $parentPage->children->count() + 1,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('POST', '/core/v1/information-pages', [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(),
            'parent_id' => $parentPage->id,
            'order' => -1,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $image = factory(File::class)->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);

        $this->json('POST', '/core/v1/information-pages', [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(),
            'parent_id' => $parentPage->id,
            'order' => 1,
            'image_file_id' => $image->id,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function createInformationPageAsChild201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(InformationPage::class)->create();

        $data = [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(),
            'parent_id' => $parentPage->id,
        ];
        $response = $this->json('POST', '/core/v1/information-pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'image',
            'parent' => [
                'id',
                'title',
                'image',
                'content',
                'order',
                'enabled',
                'created_at',
                'updated_at',
            ],
            'children',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @test
     */
    public function createInformationPageAfterSibling201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(InformationPage::class)->states('withChildren')->create();

        $childPage = $parentPage->children()->defaultOrder()->offset(1)->limit(1)->first();

        $data = [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(),
            'parent_id' => $parentPage->id,
            'order' => 1,
        ];
        $response = $this->json('POST', '/core/v1/information-pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $page = InformationPage::find($response->json('data.id'));

        $this->assertEquals($childPage->id, $page->getNextSibling()->id);
    }

    /**
     * @test
     */
    public function createInformationPageAsFirstChild201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(InformationPage::class)->states('withChildren')->create();

        $childPage = $parentPage->children()->defaultOrder()->offset(0)->limit(1)->first();

        $data = [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(),
            'parent_id' => $parentPage->id,
            'order' => 0,
        ];
        $response = $this->json('POST', '/core/v1/information-pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $page = InformationPage::find($response->json('data.id'));

        $this->assertEquals($childPage->id, $page->getNextSibling()->id);
        $this->assertEquals(null, $page->getPrevSibling());
    }

    /**
     * @test
     */
    public function createInformationPageWithImagePNG201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(InformationPage::class)->create();

        $image = factory(File::class)->states('pending-assignment')->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);

        $image->uploadBase64EncodedFile(
            'data:image/png;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.png'))
        );

        $data = [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(),
            'image_file_id' => $image->id,
            'parent_id' => $parentPage->id,
        ];
        $response = $this->json('POST', '/core/v1/information-pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'image' => [
                'id',
                'mime_type',
                'created_at',
                'updated_at',
            ],
            'parent',
            'children',
            'created_at',
            'updated_at',
        ]);

        $response->assertJsonFragment([
            'id' => $image->id,
        ]);
    }

    /**
     * @test
     */
    public function createInformationPageWithImageJPG201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(InformationPage::class)->create();

        $image = factory(File::class)->states('pending-assignment')->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);

        $image->uploadBase64EncodedFile(
            'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.jpg'))
        );

        $data = [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(),
            'image_file_id' => $image->id,
            'parent_id' => $parentPage->id,
        ];
        $response = $this->json('POST', '/core/v1/information-pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'image' => [
                'id',
                'mime_type',
                'created_at',
                'updated_at',
            ],
            'parent',
            'children',
            'created_at',
            'updated_at',
        ]);

        $response->assertJsonFragment([
            'id' => $image->id,
        ]);
    }

    /**
     * @test
     */
    public function updateInformationPageAsGuest403()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @test
     */
    public function updateInformationPageAsAdminWithInvalidData422()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @test
     */
    public function updateInformationPageAsAdmin200()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @test
     */
    public function updateInformationPageStatus200()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @test
     */
    public function updateInformationPageAddImage200()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @test
     */
    public function updateInformationPageRemoveImage200()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @test
     */
    public function updateInformationPageChangeImage200()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @test
     */
    public function updateInformationPageChangeParent200()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @test
     */
    public function updateInformationPageChangeOrder200()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @test
     */
    public function deleteInformationPageAsGuest403()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @test
     */
    public function deleteInformationPageAsAdmin204()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @test
     */
    public function deleteInformationPageWithChildren422()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
}
