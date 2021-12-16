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
    public function listMixedStateInformationPagesAsGuestFilterByID200()
    {
        $informationPages = factory(InformationPage::class, 5)->create();
        $disabledInformationPage = factory(InformationPage::class)->states('disabled')->create();

        $ids = [
            $informationPages->get(0)->id,
            $informationPages->get(2)->id,
            $informationPages->get(4)->id,
            $disabledInformationPage->id,
        ];

        $response = $this->json('GET', '/core/v1/information-pages/index?filter[id]=' . implode(',', $ids));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment([
            'id' => $informationPages->get(0)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $informationPages->get(2)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $informationPages->get(4)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $informationPages->get(1)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $informationPages->get(3)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $disabledInformationPage->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStateInformationPagesAsGuestFilterByParentID200()
    {
        $informationPage1 = factory(InformationPage::class)->states('withChildren')->create();
        $informationPage2 = factory(InformationPage::class)->states('withChildren')->create();

        $response = $this->json('GET', '/core/v1/information-pages/index?filter[parent_id]=' . $informationPage1->id);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment([
            'id' => $informationPage1->children->get(0)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $informationPage1->children->get(1)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $informationPage1->children->get(2)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $informationPage1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $informationPage2->children->get(0)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $informationPage2->children->get(1)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $informationPage2->children->get(2)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $informationPage2->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStateInformationPagesAsGuestFilterByTitle200()
    {
        $informationPage1 = factory(InformationPage::class)->create(['title' => 'Page One']);
        $informationPage2 = factory(InformationPage::class)->create(['title' => 'Second Page']);
        $informationPage3 = factory(InformationPage::class)->create(['title' => 'Third']);
        $informationPage4 = factory(InformationPage::class)->create(['title' => 'Page the Fourth']);
        $informationPage5 = factory(InformationPage::class)->create(['title' => 'Final']);

        $response = $this->json('GET', '/core/v1/information-pages/index?filter[title]=page');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment([
            'id' => $informationPage1->id,
        ]);
        $response->assertJsonFragment([
            'id' => $informationPage2->id,
        ]);
        $response->assertJsonFragment([
            'id' => $informationPage4->id,
        ]);
        $response->assertJsonMissing([
            'id' => $informationPage3->id,
        ]);
        $response->assertJsonMissing([
            'id' => $informationPage5->id,
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
    public function auditCreatedOnShow()
    {
        $this->fakeEvents();

        $informationPage = factory(InformationPage::class)->create();

        $this->json('GET', '/core/v1/information-pages/' . $informationPage->id);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return ($event->getAction() === Audit::ACTION_READ);
        });
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
    public function getEnabledInformationPageImagePNGAsGuest200()
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

        $base64Image = 'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.png'));

        $image->uploadBase64EncodedFile($base64Image);

        $informationPage = factory(InformationPage::class)->create([
            'image_file_id' => $image->id,
        ]);

        $response = $this->json('GET', '/core/v1/information-pages/' . $informationPage->id . '/image.png');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals($base64Image, 'data:image/jpeg;base64,' . base64_encode($response->content()));
    }

    /**
     * @test
     */
    public function getEnabledInformationPageImageJPGAsGuest200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(InformationPage::class)->create();

        $image = factory(File::class)->states('pending-assignment')->create([
            'filename' => Str::random() . '.jpg',
            'mime_type' => 'image/jepg',
        ]);

        $base64Image = 'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.jpg'));

        $image->uploadBase64EncodedFile($base64Image);

        $informationPage = factory(InformationPage::class)->create([
            'image_file_id' => $image->id,
        ]);

        $response = $this->json('GET', '/core/v1/information-pages/' . $informationPage->id . '/image.jpg');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals($base64Image, 'data:image/jpeg;base64,' . base64_encode($response->content()));
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
    public function auditCreatedOnCreate()
    {
        $this->fakeEvents();

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

        $this->json('POST', '/core/v1/information-pages', $data);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return ($event->getAction() === Audit::ACTION_CREATE);
        });
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
            'parent_id' => 1,
        ])->assertStatus(Response::HTTP_NOT_FOUND);

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

        /**
         * Assigned Images not allowed
         */
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
    public function createInformationPageAsRoot201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $data = [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(),
            'parent_id' => null,
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

        $response->assertJsonFragment([
            'parent' => null,
        ]);

        $rootPage = InformationPage::find($response->json('data.id'));

        $this->assertTrue($rootPage->isRoot());
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

        $response->assertJsonFragment([
            'order' => 1,
        ]);
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

        $response->assertJsonFragment([
            'order' => 0,
        ]);
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
            'filename' => Str::random() . '.jpg',
            'mime_type' => 'image/jpeg',
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
        $informationPage = factory(InformationPage::class)
            ->states('withImage', 'withParent', 'withChildren', 'disabled')
            ->create();

        $data = [
            'title' => 'New Title',
        ];

        $response = $this->json('PUT', '/core/v1/information-pages/' . $informationPage->id, $data);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function updateInformationPageAsAdmin200()
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

        $data = [
            'title' => 'New Title',
        ];

        $response = $this->json('PUT', '/core/v1/information-pages/' . $informationPage->id, $data);

        $response->assertStatus(Response::HTTP_OK);

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

        $response->assertJsonFragment([
            'title' => 'New Title',
        ]);
    }

    /**
     * @test
     */
    public function auditCreatedOnUpdate()
    {
        $this->fakeEvents();

        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $informationPage = factory(InformationPage::class)->create();

        $data = [
            'title' => 'New Title',
        ];

        $this->json('PUT', '/core/v1/information-pages/' . $informationPage->id, $data);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return ($event->getAction() === Audit::ACTION_UPDATE);
        });
    }

    /**
     * @test
     */
    public function updateInformationPageAsAdminWithInvalidData422()
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

        $this->json('PUT', '/core/v1/information-pages/' . $informationPage->id, [
            'title' => '',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/information-pages/' . $informationPage->id, [
            'content' => '',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/information-pages/' . $informationPage->id, [
            'title' => '',
            'content' => '',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/information-pages/' . $informationPage->id, [
            'parent_id' => $this->faker->uuid(),
        ])->assertStatus(Response::HTTP_NOT_FOUND);

        $this->json('PUT', '/core/v1/information-pages/' . $informationPage->id, [
            'order' => $informationPage->siblingsAndSelf()->count() + 1,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/information-pages/' . $informationPage->id, [
            'order' => -1,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        /**
         * Assigned Images not allowed
         */
        $image = factory(File::class)->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);

        $this->json('PUT', '/core/v1/information-pages/' . $informationPage->id, [
            'image_file_id' => $image->id,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function updateInformationPageAddImage200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $image = factory(File::class)->states('pending-assignment')->create([
            'filename' => Str::random() . '.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $image->uploadBase64EncodedFile(
            'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.jpg'))
        );

        $informationPage = factory(InformationPage::class)
            ->states('withParent', 'withChildren', 'disabled')
            ->create();

        $data = [
            'image_file_id' => $image->id,
        ];

        $response = $this->json('PUT', '/core/v1/information-pages/' . $informationPage->id, $data);

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
    public function updateInformationPageRemoveImage200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $image = factory(File::class)->create([
            'filename' => Str::random() . '.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $image->uploadBase64EncodedFile(
            'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.jpg'))
        );

        $informationPage = factory(InformationPage::class)
            ->states('withParent', 'withChildren', 'disabled')
            ->create([
                'image_file_id' => $image->id,
            ]);

        $data = [
            'image_file_id' => null,
        ];

        $response = $this->json('PUT', '/core/v1/information-pages/' . $informationPage->id, $data);

        $response->assertStatus(Response::HTTP_OK);

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

        $response->assertJsonMissing([
            'id' => $image->id,
        ]);
    }

    /**
     * @test
     */
    public function updateInformationPageChangeImage200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $imageJpg = factory(File::class)->create([
            'filename' => Str::random() . '.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $imageJpg->uploadBase64EncodedFile(
            'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.jpg'))
        );

        $imagePng = factory(File::class)->states('pending-assignment')->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);

        $imagePng->uploadBase64EncodedFile(
            'data:image/png;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.png'))
        );

        $informationPage = factory(InformationPage::class)
            ->states('withParent', 'withChildren', 'disabled')
            ->create([
                'image_file_id' => $imageJpg->id,
            ]);

        $data = [
            'image_file_id' => $imagePng->id,
        ];

        $response = $this->json('PUT', '/core/v1/information-pages/' . $informationPage->id, $data);

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
            'parent',
            'children',
            'created_at',
            'updated_at',
        ]);

        $response->assertJsonMissing([
            'id' => $imageJpg->id,
        ]);

        $response->assertJsonFragment([
            'id' => $imagePng->id,
        ]);
    }

    /**
     * @test
     */
    public function updateInformationPageChangeParent200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage1 = factory(InformationPage::class)->states('withChildren')->create();
        $parentPage2 = factory(InformationPage::class)->states('withChildren')->create();

        $informationPage = factory(InformationPage::class)
            ->states('withChildren')
            ->create([
                'parent_uuid' => $parentPage1->id,
            ]);

        $data = [
            'parent_id' => $parentPage2->id,
        ];

        $response = $this->json('PUT', '/core/v1/information-pages/' . $informationPage->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonMissing([
            'id' => $parentPage1->id,
        ]);

        $response->assertJsonFragment([
            'id' => $parentPage2->id,
        ]);
    }

    /**
     * @test
     */
    public function updateInformationPageChangeOrder200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(InformationPage::class)->states('withChildren')->create();

        $children = $parentPage->children()->defaultOrder()->get();

        $data = [
            'order' => 2,
        ];

        $response = $this->json('PUT', '/core/v1/information-pages/' . $children->get(1)->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $children->get(2)->refreshNode();

        $this->assertEquals($children->get(1)->id, $children->get(2)->getNextSibling()->id);

        $response->assertJsonMissing([
            'order' => 1,
        ]);

        $response->assertJsonFragment([
            'order' => 2,
        ]);

        $data = [
            'order' => 0,
        ];

        $response = $this->json('PUT', '/core/v1/information-pages/' . $children->get(1)->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $children->get(1)->refreshNode();

        $this->assertEquals($children->get(0)->id, $children->get(1)->getNextSibling()->id);

        $response->assertJsonMissing([
            'order' => 2,
        ]);

        $response->assertJsonFragment([
            'order' => 0,
        ]);
    }

    /**
     * @test
     */
    public function updateInformationPageDisabledCascadestoChildPages200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $informationPage = factory(InformationPage::class)->states('withParent', 'withChildren')->create();

        $parent = $informationPage->parent;

        $children = $informationPage->children()->defaultOrder()->get();

        $data = [
            'enabled' => 0,
        ];

        $response = $this->json('PUT', '/core/v1/information-pages/' . $parent->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertFalse($parent->fresh()->enabled);
        $this->assertFalse($informationPage->fresh()->enabled);
        $this->assertFalse($children->get(0)->fresh()->enabled);

        $response->assertJsonMissing([
            'enabled' => true,
        ]);

        $response->assertJsonFragment([
            'enabled' => false,
        ]);

        $data = [
            'enabled' => 1,
        ];

        $response = $this->json('PUT', '/core/v1/information-pages/' . $parent->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertTrue($parent->fresh()->enabled);
        $this->assertFalse($informationPage->fresh()->enabled);
        $this->assertFalse($children->get(0)->fresh()->enabled);

        $response->assertJsonFragment([
            'enabled' => true,
        ]);
    }

    /**
     * @test
     */
    public function deleteInformationPageAsGuest403()
    {
        $informationPage = factory(InformationPage::class)->create();

        $response = $this->json('DELETE', '/core/v1/information-pages/' . $informationPage->id);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function deleteInformationPageAsAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $informationPage = factory(InformationPage::class)->create();

        $response = $this->json('DELETE', '/core/v1/information-pages/' . $informationPage->id);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing('information_pages', ['id' => $informationPage->id]);
    }

    /**
     * @test
     */
    public function auditCreatedOnDelete()
    {
        $this->fakeEvents();

        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $informationPage = factory(InformationPage::class)->create();

        $this->json('DELETE', '/core/v1/information-pages/' . $informationPage->id);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return ($event->getAction() === Audit::ACTION_DELETE);
        });
    }

    /**
     * @test
     */
    public function deleteInformationPageWithChildren422()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $informationPage = factory(InformationPage::class)->states('withParent', 'withChildren')->create();

        $parent = $informationPage->parent;

        $children = $informationPage->children()->defaultOrder()->get();

        $response = $this->json('DELETE', '/core/v1/information-pages/' . $informationPage->id);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertDatabaseHas('information_pages', ['id' => $informationPage->id]);
        $this->assertDatabaseHas('information_pages', ['id' => $children->get(0)->id]);
        $this->assertDatabaseHas('information_pages', ['id' => $children->get(1)->id]);
        $this->assertDatabaseHas('information_pages', ['id' => $children->get(2)->id]);
        $this->assertDatabaseHas('information_pages', ['id' => $parent->id]);
    }

    /**
     * @test
     */
    public function deleteInformationPageWithImage200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $informationPage = factory(InformationPage::class)->states('withImage')->create();

        $imageId = $informationPage->image_file_id;

        $response = $this->json('DELETE', '/core/v1/information-pages/' . $informationPage->id);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing('information_pages', ['id' => $informationPage->id]);
        $this->assertDatabaseMissing('files', ['id' => $imageId]);
    }
}
