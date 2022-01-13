<?php

namespace Tests\Feature;

use App\Events\EndpointHit;
use App\Models\Audit;
use App\Models\File;
use App\Models\Page;
use App\Models\User;
use Faker\Factory as Faker;
use function GuzzleHttp\json_encode;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PagesTest extends TestCase
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
    public function listEnabledPagesAsGuest200()
    {
        factory(Page::class)->states('withParent', 'withChildren')->create();

        $response = $this->json('GET', '/core/v1/pages/index');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'title',
                    'content',
                    'order',
                    'enabled',
                    'page_type',
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

        $this->json('GET', '/core/v1/pages/index');

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return ($event->getAction() === Audit::ACTION_READ);
        });
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsGuest200()
    {
        $page = factory(Page::class)
            ->states('withImage', 'withParent', 'disabled')
            ->create();

        $landingPage = factory(Page::class)
            ->states('withImage', 'landingPage')
            ->create();

        $response = $this->json('GET', '/core/v1/pages/index');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $page->parent->id,
        ]);
        $response->assertJsonFragment([
            'id' => $landingPage->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $page = factory(Page::class)
            ->states('withImage', 'withParent', 'disabled')
            ->create();

        $landingPage = factory(Page::class)
            ->states('withImage', 'landingPage')
            ->create();

        $response = $this->json('GET', '/core/v1/pages/index');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment([
            'id' => $page->parent->id,
        ]);
        $response->assertJsonFragment([
            'id' => $landingPage->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsGuestFilterByID200()
    {
        $pages = factory(Page::class, 5)->create();
        $disabledPage = factory(Page::class)->states('disabled')->create();

        $ids = [
            $pages->get(0)->id,
            $pages->get(2)->id,
            $pages->get(4)->id,
            $disabledPage->id,
        ];

        $response = $this->json('GET', '/core/v1/pages/index?filter[id]=' . implode(',', $ids));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment([
            'id' => $pages->get(0)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $pages->get(2)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $pages->get(4)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $pages->get(1)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $pages->get(3)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $disabledPage->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsGuestFilterByParentID200()
    {
        $page1 = factory(Page::class)->states('withChildren')->create();
        $page2 = factory(Page::class)->states('withChildren')->create();

        $response = $this->json('GET', '/core/v1/pages/index?filter[parent_id]=' . $page1->id);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment([
            'id' => $page1->children->get(0)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page1->children->get(1)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page1->children->get(2)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page2->children->get(0)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page2->children->get(1)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page2->children->get(2)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page2->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsGuestFilterByTitle200()
    {
        $page1 = factory(Page::class)->create(['title' => 'Page One']);
        $page2 = factory(Page::class)->create(['title' => 'Second Page']);
        $page3 = factory(Page::class)->create(['title' => 'Third']);
        $page4 = factory(Page::class)->create(['title' => 'Page the Fourth']);
        $page5 = factory(Page::class)->create(['title' => 'Final']);
        $landingPage = factory(Page::class)
            ->states('landingPage')
            ->create(['title' => 'Landing Page']);

        $response = $this->json('GET', '/core/v1/pages/index?filter[title]=page');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(4, 'data');
        $response->assertJsonFragment([
            'id' => $page1->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page2->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page4->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page3->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page5->id,
        ]);
        $response->assertJsonFragment([
            'id' => $landingPage->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsGuestFilterByLandingPage200()
    {
        $page1 = factory(Page::class)->states('landingPage')->create();
        $page2 = factory(Page::class)->states('disabled')->create();
        $page3 = factory(Page::class)->create();
        $page4 = factory(Page::class)->states('landingPage', 'disabled')->create();

        $response = $this->json('GET', '/core/v1/pages/index?filter[page_type]=landing');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $page1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page2->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page3->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page4->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsGuestFilterByInformationPage200()
    {
        $page1 = factory(Page::class)->states('landingPage')->create();
        $page2 = factory(Page::class)->states('disabled')->create();
        $page3 = factory(Page::class)->create();
        $page4 = factory(Page::class)->states('landingPage', 'disabled')->create();

        $response = $this->json('GET', '/core/v1/pages/index?filter[page_type]=information');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $page3->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page2->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page4->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsAdminFilterByLandingPage200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $page1 = factory(Page::class)->states('landingPage')->create();
        $page2 = factory(Page::class)->states('disabled')->create();
        $page3 = factory(Page::class)->create();
        $page4 = factory(Page::class)->states('landingPage', 'disabled')->create();

        $response = $this->json('GET', '/core/v1/pages/index?filter[page_type]=landing');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $page1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page2->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page3->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page4->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsAdminFilterByInformationPage200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $page1 = factory(Page::class)->states('landingPage')->create();
        $page2 = factory(Page::class)->states('disabled')->create();
        $page3 = factory(Page::class)->create();
        $page4 = factory(Page::class)->states('landingPage', 'disabled')->create();

        $response = $this->json('GET', '/core/v1/pages/index?filter[page_type]=information');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $page2->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page3->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page4->id,
        ]);
    }

    /**
     * @test
     */
    public function getEnabledPageAsGuest200()
    {
        $page = factory(Page::class)->states('withImage', 'withParent', 'withChildren')->create();

        $response = $this->json('GET', '/core/v1/pages/' . $page->id);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'page_type',
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
                'page_type',
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
                    'page_type',
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

        $page = factory(Page::class)->create();

        $this->json('GET', '/core/v1/pages/' . $page->id);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return ($event->getAction() === Audit::ACTION_READ);
        });
    }

    /**
     * @test
     */
    public function getDisabledPageAsGuest403()
    {
        $page = factory(Page::class)
            ->states('withImage', 'withParent', 'withChildren', 'disabled')
            ->create();

        $response = $this->json('GET', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function getDisabledPageAsAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $page = factory(Page::class)
            ->states('withImage', 'withParent', 'withChildren', 'disabled')
            ->create();

        $response = $this->json('GET', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'page_type',
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
                'page_type',
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
                    'page_type',
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
    public function getEnabledPageImagePNGAsGuest200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(Page::class)->create();

        $image = factory(File::class)->states('pending-assignment')->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);

        $base64Image = 'data:image/png;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.png'));

        $image->uploadBase64EncodedFile($base64Image);

        $page = factory(Page::class)->create([
            'image_file_id' => $image->id,
        ]);

        $response = $this->json('GET', '/core/v1/pages/' . $page->id . '/image.png');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals($base64Image, 'data:image/png;base64,' . base64_encode($response->content()));
    }

    /**
     * @test
     */
    public function getEnabledPageImageJPGAsGuest200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(Page::class)->create();

        $image = factory(File::class)->states('pending-assignment')->create([
            'filename' => Str::random() . '.jpg',
            'mime_type' => 'image/jepg',
        ]);

        $base64Image = 'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.jpg'));

        $image->uploadBase64EncodedFile($base64Image);

        $page = factory(Page::class)->create([
            'image_file_id' => $image->id,
        ]);

        $response = $this->json('GET', '/core/v1/pages/' . $page->id . '/image.jpg');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals($base64Image, 'data:image/jpeg;base64,' . base64_encode($response->content()));
    }

    /**
     * @test
     */
    public function getEnabledPageImageSVGAsGuest200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(Page::class)->create();

        $image = factory(File::class)->states('pending-assignment')->create([
            'filename' => Str::random() . '.svg',
            'mime_type' => 'image/svg+xml',
        ]);

        $base64Image = 'data:image/svg+xml;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.svg'));

        $image->uploadBase64EncodedFile($base64Image);

        $page = factory(Page::class)->create([
            'image_file_id' => $image->id,
        ]);

        $response = $this->json('GET', '/core/v1/pages/' . $page->id . '/image.svg');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals($base64Image, 'data:image/svg+xml;base64,' . base64_encode($response->content()));
    }

    /**
     * @test
     */
    public function createPageAsGuest403()
    {
        $parentPage = factory(Page::class)->create();

        $data = [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function createPageAsAdmin201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(Page::class)->create();

        $data = [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'page_type',
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

        $parentPage = factory(Page::class)->create();

        $data = [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return ($event->getAction() === Audit::ACTION_CREATE);
        });
    }

    /**
     * @test
     */
    public function createPageAsAdminWithInvalidData422()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('POST', '/core/v1/pages', [
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('POST', '/core/v1/pages', [
            'title' => '',
            'content' => '',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(),
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'parent_id' => 1,
        ])->assertStatus(Response::HTTP_NOT_FOUND);

        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'parent_id' => $this->faker->uuid(),
        ])->assertStatus(Response::HTTP_NOT_FOUND);

        $parentPage = factory(Page::class)->states('withChildren')->create();

        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'parent_id' => $parentPage->id,
            'order' => $parentPage->children->count() + 1,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'parent_id' => $parentPage->id,
            'order' => -1,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'parent_id' => $parentPage->id,
            'page_type' => 'landing',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        /**
         * Assigned Images not allowed
         */
        $image = factory(File::class)->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);

        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'parent_id' => $parentPage->id,
            'order' => 1,
            'image_file_id' => $image->id,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function createPageAsChild201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(Page::class)->create();

        $data = [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'parent_id' => $parentPage->id,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'page_type',
            'image',
            'parent' => [
                'id',
                'title',
                'image',
                'content',
                'order',
                'enabled',
                'page_type',
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
    public function createPageAsRoot201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $data = [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'parent_id' => null,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'page_type',
            'image',
            'parent',
            'children',
            'created_at',
            'updated_at',
        ]);

        $response->assertJsonFragment([
            'parent' => null,
        ]);

        $response->assertJsonFragment([
            'page_type' => 'information',
        ]);

        $rootPage = Page::find($response->json('data.id'));

        $this->assertTrue($rootPage->isRoot());
    }

    /**
     * @test
     */
    public function createPageAsLandingPage201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $data = [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'page_type' => 'landing',
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'page_type',
            'image',
            'parent',
            'children',
            'created_at',
            'updated_at',
        ]);

        $response->assertJsonFragment([
            'parent' => null,
        ]);

        $response->assertJsonFragment([
            'page_type' => 'landing',
        ]);

        $rootPage = Page::find($response->json('data.id'));

        $this->assertTrue($rootPage->isRoot());
    }

    /**
     * @test
     */
    public function createPageAfterSibling201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(Page::class)->states('withChildren')->create();

        $childPage = $parentPage->children()->defaultOrder()->offset(1)->limit(1)->first();

        $data = [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'parent_id' => $parentPage->id,
            'order' => 1,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $page = Page::find($response->json('data.id'));

        $this->assertEquals($childPage->id, $page->getNextSibling()->id);

        $response->assertJsonFragment([
            'order' => 1,
        ]);
    }

    /**
     * @test
     */
    public function createPageAsFirstChild201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(Page::class)->states('withChildren')->create();

        $childPage = $parentPage->children()->defaultOrder()->offset(0)->limit(1)->first();

        $data = [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'parent_id' => $parentPage->id,
            'order' => 0,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $page = Page::find($response->json('data.id'));

        $this->assertEquals($childPage->id, $page->getNextSibling()->id);
        $this->assertEquals(null, $page->getPrevSibling());

        $response->assertJsonFragment([
            'order' => 0,
        ]);
    }

    /**
     * @test
     */
    public function createPageWithImagePNG201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(Page::class)->create();

        $image = factory(File::class)->states('pending-assignment')->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);

        $image->uploadBase64EncodedFile(
            'data:image/png;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.png'))
        );

        $data = [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'image_file_id' => $image->id,
            'parent_id' => $parentPage->id,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'page_type',
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
    public function createPageWithImageJPG201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(Page::class)->create();

        $image = factory(File::class)->states('pending-assignment')->create([
            'filename' => Str::random() . '.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $image->uploadBase64EncodedFile(
            'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.jpg'))
        );

        $data = [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'image_file_id' => $image->id,
            'parent_id' => $parentPage->id,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'page_type',
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
    public function createPageWithImageSVG201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(Page::class)->create();

        $image = factory(File::class)->states('pending-assignment')->create([
            'filename' => Str::random() . '.svg',
            'mime_type' => 'image/svg+xml',
        ]);

        $image->uploadBase64EncodedFile(
            'data:image/svg+xml;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.svg'))
        );

        $data = [
            'title' => $this->faker->sentence(),
            'content' => json_encode(['introduction' => ['copy' => $this->faker->realText()]]),
            'image_file_id' => $image->id,
            'parent_id' => $parentPage->id,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'page_type',
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
    public function updatePageAsGuest403()
    {
        $page = factory(Page::class)
            ->states('withImage', 'withParent', 'withChildren', 'disabled')
            ->create();

        $data = [
            'title' => 'New Title',
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function updatePageAsAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $page = factory(Page::class)
            ->states('withImage', 'withParent', 'withChildren', 'disabled')
            ->create();

        $data = [
            'title' => 'New Title',
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'page_type',
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

        $page = factory(Page::class)->create();

        $data = [
            'title' => 'New Title',
        ];

        $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return ($event->getAction() === Audit::ACTION_UPDATE);
        });
    }

    /**
     * @test
     */
    public function updatePageAsAdminWithInvalidData422()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $page = factory(Page::class)
            ->states('withImage', 'withParent', 'withChildren', 'disabled')
            ->create();

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'title' => '',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'content' => '',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'title' => '',
            'content' => '',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'content' => $this->faker->realText(),
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'parent_id' => $this->faker->uuid(),
        ])->assertStatus(Response::HTTP_NOT_FOUND);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'order' => $page->siblingsAndSelf()->count() + 1,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'order' => -1,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'page_type' => 'landing',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        /**
         * Assigned Images not allowed
         */
        $image = factory(File::class)->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'image_file_id' => $image->id,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function updatePageAddImage200()
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

        $page = factory(Page::class)
            ->states('withParent', 'withChildren', 'disabled')
            ->create();

        $data = [
            'image_file_id' => $image->id,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'page_type',
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
    public function updatePageRemoveImage200()
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

        $page = factory(Page::class)
            ->states('withParent', 'withChildren', 'disabled')
            ->create([
                'image_file_id' => $image->id,
            ]);

        $data = [
            'image_file_id' => null,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'page_type',
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
    public function updatePageChangeImage200()
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

        $page = factory(Page::class)
            ->states('withParent', 'withChildren', 'disabled')
            ->create([
                'image_file_id' => $imageJpg->id,
            ]);

        $data = [
            'image_file_id' => $imagePng->id,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'page_type',
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
    public function updatePageChangeParent200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage1 = factory(Page::class)->states('withChildren')->create();
        $parentPage2 = factory(Page::class)->states('withChildren')->create();

        $page = factory(Page::class)
            ->states('withChildren')
            ->create([
                'parent_uuid' => $parentPage1->id,
            ]);

        $data = [
            'parent_id' => $parentPage2->id,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

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
    public function updatePageChangePageType200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(Page::class)->states('withChildren')->create();

        $page = factory(Page::class)
            ->states('withChildren')
            ->create();

        $page->appendToNode($parentPage)->save();

        $data = [
            'page_type' => Page::PAGE_TYPE_LANDING,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $data = [
            'page_type' => Page::PAGE_TYPE_LANDING,
            'parent_id' => null,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonMissing([
            'id' => $parentPage->id,
        ]);

        $response->assertJsonFragment([
            'page_type' => Page::PAGE_TYPE_LANDING,
        ]);
    }

    /**
     * @test
     */
    public function updatePageChangeOrder200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(Page::class)->states('withChildren')->create();

        $children = $parentPage->children()->defaultOrder()->get();

        $data = [
            'order' => 2,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $children->get(1)->id, $data);

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

        $response = $this->json('PUT', '/core/v1/pages/' . $children->get(1)->id, $data);

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
    public function updatePageDisabledCascadestoChildPages200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $page = factory(Page::class)->states('withParent', 'withChildren')->create();

        $parent = $page->parent;

        $children = $page->children()->defaultOrder()->get();

        $data = [
            'enabled' => 0,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $parent->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertFalse($parent->fresh()->enabled);
        $this->assertFalse($page->fresh()->enabled);
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

        $response = $this->json('PUT', '/core/v1/pages/' . $parent->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertTrue($parent->fresh()->enabled);
        $this->assertFalse($page->fresh()->enabled);
        $this->assertFalse($children->get(0)->fresh()->enabled);

        $response->assertJsonFragment([
            'enabled' => true,
        ]);
    }

    /**
     * @test
     */
    public function deletePageAsGuest403()
    {
        $page = factory(Page::class)->create();

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function deletePageAsAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $page = factory(Page::class)->create();

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
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

        $page = factory(Page::class)->create();

        $this->json('DELETE', '/core/v1/pages/' . $page->id);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return ($event->getAction() === Audit::ACTION_DELETE);
        });
    }

    /**
     * @test
     */
    public function deletePageWithChildren422()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $page = factory(Page::class)->states('withParent', 'withChildren')->create();

        $parent = $page->parent;

        $children = $page->children()->defaultOrder()->get();

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
        $this->assertDatabaseHas('pages', ['id' => $children->get(0)->id]);
        $this->assertDatabaseHas('pages', ['id' => $children->get(1)->id]);
        $this->assertDatabaseHas('pages', ['id' => $children->get(2)->id]);
        $this->assertDatabaseHas('pages', ['id' => $parent->id]);
    }

    /**
     * @test
     */
    public function deleteLandingPageWithChildren422()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $page = factory(Page::class)->states('landingPage', 'withChildren')->create();

        $parent = $page->parent;

        $children = $page->children()->defaultOrder()->get();

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
        $this->assertDatabaseHas('pages', ['id' => $children->get(0)->id]);
        $this->assertDatabaseHas('pages', ['id' => $children->get(1)->id]);
        $this->assertDatabaseHas('pages', ['id' => $children->get(2)->id]);
    }

    /**
     * @test
     */
    public function deletePageWithImage200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $page = factory(Page::class)->states('withImage')->create();

        $imageId = $page->image_file_id;

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
        $this->assertDatabaseMissing('files', ['id' => $imageId]);
    }
}
