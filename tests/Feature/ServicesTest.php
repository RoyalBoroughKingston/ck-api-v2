<?php

namespace Tests\Feature;

use Carbon\Carbon;
use App\Models\Tag;
use Tests\TestCase;
use App\Models\File;
use App\Models\Role;
use App\Models\User;
use App\Models\Audit;
use App\Models\Service;
use App\Models\Taxonomy;
use App\Models\UserRole;
use App\Events\EndpointHit;
use App\Models\SocialMedia;
use Carbon\CarbonImmutable;
use Faker\Factory as Faker;
use Illuminate\Support\Arr;
use App\Models\Organisation;
use App\Models\UpdateRequest;
use Illuminate\Http\Response;
use Laravel\Passport\Passport;
use App\Models\ServiceLocation;
use App\Models\ServiceTaxonomy;
use Illuminate\Http\UploadedFile;
use App\Models\HolidayOpeningHour;
use App\Models\RegularOpeningHour;
use App\Models\ServiceRefreshToken;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Collection;

class ServicesTest extends TestCase
{
    /**
     * Create spreadsheets of services
     *
     * @return null
     **/
    public function createServiceSpreadsheets(\Illuminate\Support\Collection $services, array $serviceEligibilities = null)
    {
        $faker = Faker::create('en_GB');

        $headers = [
            'id',
            'organisation_id',
            'name',
            'type',
            'status',
            'intro',
            'description',
            'wait_time',
            'is_free',
            'fees_text',
            'fees_url',
            'testimonial',
            'video_embed',
            'url',
            'contact_name',
            'contact_phone',
            'contact_email',
            'referral_method',
            'referral_email',
            'referral_url',
            'ends_at',
            'show_referral_disclaimer',
            'referral_button_text',
            'eligibility_age_group_custom',
            'eligibility_disability_custom',
            'eligibility_employment_custom',
            'eligibility_gender_custom',
            'eligibility_housing_custom',
            'eligibility_income_custom',
            'eligibility_language_custom',
            'eligibility_ethnicity_custom',
            'eligibility_other_custom',
            'eligibility_taxonomies',
        ];

        $services = $services->map(function ($service) use ($serviceEligibilities) {
            $serviceAttributes = $service->getAttributes();
            $serviceAttributes['id'] = $service->id ?: uuid();

            if (is_array($serviceEligibilities) && !empty($serviceEligibilities[$serviceAttributes['id']])) {
                $serviceAttributes['eligibility_taxonomies'] = implode(',', $serviceEligibilities[$serviceAttributes['id']]);
            }

            return $serviceAttributes;
        });

        $spreadsheet = \Tests\Integration\SpreadsheetParserTest::createSpreadsheets($services->all(), $headers);
        \Tests\Integration\SpreadsheetParserTest::writeSpreadsheetsToDisk($spreadsheet, 'test.xlsx', 'test.xls');
    }

    /*
     * List all the services.
     */

    /**
     * @test
     */
    public function guest_can_list_them(): void
    {
        /** @var \App\Models\Service $service */
        $service = Service::factory()->withPngLogo()->create();
        $service->usefulInfos()->create([
            'title' => 'Did You Know?',
            'description' => 'This is a test description',
            'order' => 1,
        ]);
        $service->offerings()->create([
            'offering' => 'Weekly club',
            'order' => 1,
        ]);
        $service->socialMedias()->create([
            'type' => SocialMedia::TYPE_INSTAGRAM,
            'url' => 'https://www.instagram.com/ayupdigital/',
        ]);
        $service->serviceTaxonomies()->create([
            'taxonomy_id' => Taxonomy::category()->children()->first()->id,
        ]);

        $response = $this->json('GET', '/core/v1/services');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $service->id,
            'organisation_id' => $service->organisation_id,
            'has_logo' => $service->hasLogo(),
            'slug' => $service->slug,
            'name' => $service->name,
            'type' => $service->type,
            'status' => $service->status,
            'intro' => $service->intro,
            'description' => $service->description,
            'image' => [
                'id' => $service->logoFile->id,
                'mime_type' => $service->logoFile->mime_type,
                'alt_text' => $service->logoFile->altText,
                'url' => $service->logoFile->url(),
            ],
            'wait_time' => $service->wait_time,
            'is_free' => $service->is_free,
            'fees_text' => $service->fees_text,
            'fees_url' => $service->fees_url,
            'testimonial' => $service->testimonial,
            'video_embed' => $service->video_embed,
            'url' => $service->url,
            'contact_name' => $service->contact_name,
            'contact_phone' => $service->contact_phone,
            'contact_email' => $service->contact_email,
            'show_referral_disclaimer' => $service->show_referral_disclaimer,
            'referral_method' => $service->referral_method,
            'referral_button_text' => $service->referral_button_text,
            'referral_email' => $service->referral_email,
            'referral_url' => $service->referral_url,
            'cqc_location_id' => $service->cqc_location_id,
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did You Know?',
                    'description' => 'This is a test description',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [
                [
                    'id' => Taxonomy::category()->children()->first()->id,
                    'parent_id' => Taxonomy::category()->children()->first()->parent_id,
                    'slug' => Taxonomy::category()->children()->first()->slug,
                    'name' => Taxonomy::category()->children()->first()->name,
                    'created_at' => Taxonomy::category()->children()->first()->created_at->format(CarbonImmutable::ISO8601),
                    'updated_at' => Taxonomy::category()->children()->first()->updated_at->format(CarbonImmutable::ISO8601),
                ],
            ],
            'last_modified_at' => $service->last_modified_at->format(CarbonImmutable::ISO8601),
            'created_at' => $service->created_at->format(CarbonImmutable::ISO8601),
        ]);
    }

    /**
     * @test
     */
    public function guest_can_filter_by_organisation_id(): void
    {
        $anotherService = Service::factory()->create();
        $service = Service::factory()->create();
        $service->usefulInfos()->create([
            'title' => 'Did You Know?',
            'description' => 'This is a test description',
            'order' => 1,
        ]);
        $service->offerings()->create([
            'offering' => 'Weekly club',
            'order' => 1,
        ]);
        $service->socialMedias()->create([
            'type' => SocialMedia::TYPE_INSTAGRAM,
            'url' => 'https://www.instagram.com/ayupdigital/',
        ]);
        $service->serviceTaxonomies()->create([
            'taxonomy_id' => Taxonomy::category()->children()->first()->id,
        ]);

        $response = $this->json('GET', "/core/v1/services?filter[organisation_id]={$service->organisation_id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $service->id]);
        $response->assertJsonMissing(['id' => $anotherService->id]);
    }

    /**
     * @test
     */
    public function guest_can_filter_by_organisation_name(): void
    {
        $anotherService = Service::factory()->create([
            'organisation_id' => Organisation::factory()->create(['name' => 'Amazing Place']),
        ]);
        $service = Service::factory()->create([
            'organisation_id' => Organisation::factory()->create(['name' => 'Interesting House']),
        ]);
        $service->usefulInfos()->create([
            'title' => 'Did You Know?',
            'description' => 'This is a test description',
            'order' => 1,
        ]);
        $service->offerings()->create([
            'offering' => 'Weekly club',
            'order' => 1,
        ]);
        $service->socialMedias()->create([
            'type' => SocialMedia::TYPE_INSTAGRAM,
            'url' => 'https://www.instagram.com/ayupdigital/',
        ]);
        $service->serviceTaxonomies()->create([
            'taxonomy_id' => Taxonomy::category()->children()->first()->id,
        ]);

        $response = $this->json('GET', "/core/v1/services?filter[organisation_name]={$service->organisation->name}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $service->id]);
        $response->assertJsonMissing(['id' => $anotherService->id]);
    }

    /**
     * @test
     */
    public function guest_can_filter_by_tag(): void
    {
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();
        $tag3 = Tag::factory()->create();
        $service1 = Service::factory()->withUsefulInfo()->withOfferings()->withSocialMedia()->withCategoryTaxonomies()
            ->create();
        $service1->tags()->attach([
            $tag1->id,
            $tag2->id,
        ]);
        $service2 = Service::factory()->withUsefulInfo()->withOfferings()->withSocialMedia()->withCategoryTaxonomies()
            ->create();
        $service2->tags()->attach([
            $tag2->id,
            $tag3->id,
        ]);
        $service3 = Service::factory()->withUsefulInfo()->withOfferings()->withSocialMedia()->withCategoryTaxonomies()
            ->create();
        $service3->tags()->attach([
            $tag1->id,
            $tag3->id,
        ]);

        $response = $this->json('GET', "/core/v1/services?filter[tags.slug]={$tag1->slug}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $service1->id]);
        $response->assertJsonFragment(['id' => $service3->id]);
        $response->assertJsonMissing(['id' => $service2->id]);

        $response = $this->json('GET', "/core/v1/services?filter[tags.slug]={$tag1->slug},{$tag2->slug}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment(['id' => $service1->id]);
        $response->assertJsonFragment(['id' => $service2->id]);
        $response->assertJsonFragment(['id' => $service3->id]);
    }

    /**
     * @test
     */
    public function audit_created_when_listed(): void
    {
        $this->fakeEvents();

        $this->json('GET', '/core/v1/services');

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return $event->getAction() === Audit::ACTION_READ;
        });
    }

    /**
     * @test
     */
    public function guest_can_sort_by_service_name(): void
    {
        $serviceOne = Service::factory()->create(['name' => 'Service A']);
        $serviceTwo = Service::factory()->create(['name' => 'Service B']);

        $response = $this->json('GET', '/core/v1/services?sort=-name');
        $data = $this->getResponseContent($response);

        $this->assertEquals($serviceOne->id, $data['data'][1]['id']);
        $this->assertEquals($serviceTwo->id, $data['data'][0]['id']);
    }

    /**
     * @test
     */
    public function guest_can_sort_by_organisation_name(): void
    {
        $serviceOne = Service::factory()->create([
            'organisation_id' => Organisation::factory()
                ->create(['name' => 'Organisation A'])
                ->id,
        ]);
        $serviceTwo = Service::factory()->create([
            'organisation_id' => Organisation::factory()
                ->create(['name' => 'Organisation B'])
                ->id,
        ]);

        $response = $this->json('GET', '/core/v1/services?sort=-organisation_name');
        $data = $this->getResponseContent($response);

        $this->assertEquals($serviceOne->organisation_id, $data['data'][1]['organisation_id']);
        $this->assertEquals($serviceTwo->organisation_id, $data['data'][0]['organisation_id']);
    }

    /**
     * @test
     */
    public function guest_can_sort_by_last_modified_at(): void
    {
        $serviceOne = Service::factory()->create([
            'last_modified_at' => '2020-01-01 13:00:00',
        ]);
        $serviceTwo = Service::factory()->create([
            'last_modified_at' => '2020-01-01 20:00:00',
        ]);

        $response = $this->json('GET', '/core/v1/services?sort=-last_modified_at');
        $data = $this->getResponseContent($response);

        $this->assertEquals($serviceOne->organisation_id, $data['data'][1]['organisation_id']);
        $this->assertEquals($serviceTwo->organisation_id, $data['data'][0]['organisation_id']);
    }

    /**
     * @test
     */
    public function guest_can_sort_by_score(): void
    {
        $service1 = Service::factory()->create(['score' => 0]);
        $service2 = Service::factory()->create(['score' => 5]);
        $service3 = Service::factory()->create(['score' => 3]);
        $service4 = Service::factory()->create(['score' => 1]);
        $service5 = Service::factory()->create(['score' => 4]);
        $service6 = Service::factory()->create(['score' => 2]);

        $response = $this->json('GET', '/core/v1/services?sort=-score');
        $response->assertStatus(Response::HTTP_OK);
        $data = $this->getResponseContent($response);

        $this->assertEquals($service2->id, $data['data'][0]['id']);
        $this->assertEquals($service5->id, $data['data'][1]['id']);
        $this->assertEquals($service3->id, $data['data'][2]['id']);
        $this->assertEquals($service6->id, $data['data'][3]['id']);
        $this->assertEquals($service4->id, $data['data'][4]['id']);
        $this->assertEquals($service1->id, $data['data'][5]['id']);
    }

    /*
     * Create a service.
     */

    /**
     * @test
     */
    public function guest_cannot_create_one(): void
    {
        $response = $this->json('POST', '/core/v1/services');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_create_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/services');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_cannot_create_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/services');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_can_create_an_inactive_one(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [],
            'eligibility_types' => [
                'custom' => [],
                'taxonomies' => [],
            ],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);

        $superAdminUser = User::factory()->create()->makeSuperAdmin();

        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_SERVICE_ORG_ADMIN,
            'updateable_id' => null,
        ]);

        $data = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_SERVICE_ORG_ADMIN)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail()->data;

        $this->assertEquals($data, $payload);

        // Simulate frontend check by making call with UpdateRequest ID.
        $updateRequestId = json_decode($response->getContent())->id;

        Passport::actingAs($superAdminUser);

        $updateRequestCheckResponse = $this->get(
            route(
                'core.v1.update-requests.show',
                ['update_request' => $updateRequestId]
            )
        );

        $updateRequestCheckResponse->assertSuccessful();
        $updateRequestResponseData = json_decode($updateRequestCheckResponse->getContent(), true);

        $this->assertEquals($updateRequestResponseData['data'], $payload);
    }

    /**
     * @test
     */
    public function organisation_admin_creates_update_request_when_creating_one(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        //Given an organisation admin is logged in
        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => Carbon::now()->addMonths(6)->toDateString() . 'T00:00:00+0000',
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [],
            'eligibility_types' => [
                'custom' => [],
                'taxonomies' => [],
            ],
        ];

        //When they create a service
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);

        $responseData = json_decode($response->getContent());

        //Then an update request should be created for the new service
        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_SERVICE_ORG_ADMIN)
            ->where('updateable_id', null)
            ->firstOrFail();

        $this->assertEquals($updateRequest->data, $payload);

        // Simulate frontend check by making call with UpdateRequest ID.
        $updateRequestId = $responseData->id;

        Passport::actingAs(User::factory()->create()->makeSuperAdmin());

        $updateRequestCheckResponse = $this->get(
            route(
                'core.v1.update-requests.show',
                ['update_request' => $updateRequestId]
            )
        );

        $updateRequestCheckResponse->assertSuccessful();
        $updateRequestResponseData = json_decode($updateRequestCheckResponse->getContent(), true);

        $this->assertEquals($updateRequestResponseData['data'], $payload);
        //And the service should not yet be created
        $this->assertEmpty(Service::all());
    }

    /**
     * @test
     */
    public function global_admin_creates_update_request_when_creating_one(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        //Given an organisation admin is logged in
        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => Carbon::now()->addMonths(6)->toDateString() . 'T00:00:00+0000',
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [Taxonomy::category()->children()->firstOrFail()->id],
            'eligibility_types' => [
                'custom' => [],
                'taxonomies' => [],
            ],
        ];

        //When they create a service
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_SERVICE_GLOBAL_ADMIN,
            'updateable_id' => null,
        ]);

        $response->assertJsonFragment($payload);

        $responseData = json_decode($response->getContent());

        //Then an update request should be created for the new service
        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_SERVICE_GLOBAL_ADMIN)
            ->where('updateable_id', null)
            ->firstOrFail();

        $this->assertEquals($updateRequest->data, $payload);

        // Simulate frontend check by making call with UpdateRequest ID.
        $updateRequestId = $responseData->id;

        Passport::actingAs(User::factory()->create()->makeSuperAdmin());

        $updateRequestCheckResponse = $this->get(
            route(
                'core.v1.update-requests.show',
                ['update_request' => $updateRequestId]
            )
        );

        $updateRequestCheckResponse->assertSuccessful();
        $updateRequestResponseData = json_decode($updateRequestCheckResponse->getContent(), true);

        $this->assertEquals($updateRequestResponseData['data'], $payload);

        //And the service should not yet be created
        $this->assertEmpty(Service::all());
    }

    /**
     * @test
     */
    public function super_admin_does_not_create_update_request_when_creating_one(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        //Given that a global admin is logged in
        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => Carbon::now()->addMonths(6)->toDateString() . 'T00:00:00+0000',
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [Taxonomy::category()->children()->firstOrFail()->id],
        ];

        //When they create a service
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        $responseData = json_decode($response->getContent())->data;

        // The service is created
        $this->assertDatabaseHas((new Service())->getTable(), ['id' => $responseData->id]);

        // And no update request was created
        $this->assertEmpty(UpdateRequest::all());
    }

    /**
     * @test
     */
    public function organisation_admin_can_create_one_with_no_form_of_contact(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => null,
            'contact_name' => $this->faker->name(),
            'contact_phone' => null,
            'contact_email' => null,
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);
    }

    /**
     * @test
     */
    public function organisation_admin_is_made_a_service_admin_when_they_create_one(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => null,
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);

        $response->assertJsonFragment(['data' => $payload]);

        $updateRequest = UpdateRequest::find($response->json('id'));

        $this->assertEquals($updateRequest->data, $payload);

        $this->approveUpdateRequest($updateRequest->id);

        $service = Service::where('slug', 'test-service-1')->first();

        $this->assertTrue($user->isServiceAdmin($service));
    }

    /**
     * @test
     */
    public function organisation_admin_can_create_one_without_cqc_field_if_cqc_flag_is_false(): void
    {
        config(['flags.cqc_location' => false]);
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => null,
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);
    }

    /**
     * @test
     */
    public function organisation_admin_can_create_one_without_tags_field_if_tags_flag_is_false(): void
    {
        config(['flags.service_tags' => true]);
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => null,
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        config(['flags.service_tags' => false]);

        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);
    }

    /**
     * @test
     */
    public function organisation_admin_can_create_one_without_offerings_field_if_offerings_flag_is_false(): void
    {
        config(['flags.offerings' => true]);
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => null,
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        config(['flags.offerings' => false]);

        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_create_an_active_one(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function createServiceWithSlugClashAsOrganisationAdmin200()
    {
        $now = Date::now();
        Date::setTestNow($now);

        $organisation = Organisation::factory()->create();

        $user = User::factory()->create()->makeOrganisationAdmin($organisation);
        Passport::actingAs($user);

        $service1 = Service::factory()->create([
            'organisation_id' => $organisation->id,
            'slug' => 'test-slug',
        ]);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-slug',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'logo_file_id' => null,
            'social_medias' => [],
            'gallery_items' => [],
            'tags' => [],
            'category_taxonomies' => [],
        ];

        //When they create a service
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest1 = UpdateRequest::find($response->json('id'));

        $this->assertEquals('test-slug', $updateRequest1->data['slug']);

        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest2 = UpdateRequest::find($response->json('id'));

        $this->assertEquals('test-slug', $updateRequest2->data['slug']);

        $updateData = $this->approveUpdateRequest($updateRequest1->id);

        $this->assertDatabaseHas('services', [
            'id' => $updateData['updateable_id'],
            'slug' => 'test-slug-1',
        ]);

        $updateData = $this->approveUpdateRequest($updateRequest2->id);

        $this->assertDatabaseHas('services', [
            'id' => $updateData['updateable_id'],
            'slug' => 'test-slug-2',
        ]);
    }

    /**
     * @test
     */
    public function global_admin_cannot_create_with_non_numeric_phone(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => 'Tel 01234 567890',
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_create_one_with_tags(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();
        $tag3 = Tag::factory()->create();

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [
                [
                    'slug' => $tag1->slug,
                    'label' => $tag1->label,
                ],
                [
                    'slug' => $tag2->slug,
                    'label' => $tag2->label,
                ],
                [
                    'slug' => $tag3->slug,
                    'label' => $tag3->label,
                ],
            ],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function organisation_admin_can_create_one_with_gallery_items(): void
    {
        // Set the max number of gallery images to 2
        config(['local.max_gallery_images' => 2]);
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        // SVG
        $imageSvg = File::factory()->pendingAssignment()->imageSvg()->create();
        // PNG
        $imagePng = File::factory()->pendingAssignment()->imagePng()->create();
        // JPG
        $imageJpg = File::factory()->pendingAssignment()->imageJpg()->create();

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [
                [
                    'file_id' => $imageSvg->id,
                ],
                [
                    'file_id' => $imagePng->id,
                ],
                [
                    'file_id' => $imageJpg->id,
                ],
            ],
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Set the max number of gallery images to 5
        config(['local.max_gallery_images' => 5]);

        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment(['data' => $payload]);

        $updateRequest = UpdateRequest::find($response->json('id'));

        $this->assertEquals($updateRequest->data, $payload);

        $this->approveUpdateRequest($updateRequest->id);

        $response = $this->json('GET', '/core/v1/services/test-service');

        $response->assertJsonFragment([
            'file_id' => $imageSvg->id,
            'url' => url("core/v1/services/{$response->json('data.id')}/gallery-items/$imageSvg->id"),
            'mime_type' => $imageSvg->mime_type,
            'alt_text' => $imageSvg->meta['alt_text'],
        ]);
        $response->assertJsonFragment([
            'file_id' => $imagePng->id,
            'url' => url("core/v1/services/{$response->json('data.id')}/gallery-items/$imagePng->id"),
            'mime_type' => $imagePng->mime_type,
            'alt_text' => $imagePng->meta['alt_text'],
        ]);
        $response->assertJsonFragment([
            'file_id' => $imageJpg->id,
            'url' => url("core/v1/services/{$response->json('data.id')}/gallery-items/$imageJpg->id"),
            'mime_type' => $imageJpg->mime_type,
            'alt_text' => $imageJpg->meta['alt_text'],
        ]);

        // Get the gallery images for the service
        $contentSvg = $this->get("/core/v1/services/test-service/gallery-items/$imageSvg->id")->content();

        $this->assertEquals(Storage::disk('local')->get('/test-data/image.svg'), $contentSvg);

        $contentPng = $this->get("/core/v1/services/test-service/gallery-items/$imagePng->id")->content();

        $this->assertEquals(Storage::disk('local')->get('/test-data/image.png'), $contentPng);

        $contentJpg = $this->get("/core/v1/services/test-service/gallery-items/$imageJpg->id")->content();

        $this->assertEquals(Storage::disk('local')->get('/test-data/image.jpg'), $contentJpg);
    }

    /**
     * @test
     */
    public function organisation_admin_can_create_one_with_elegibility_fields(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        $taxonomyIds = Taxonomy::serviceEligibility()
            ->children
            ->map(function ($taxonomy) {
                return $taxonomy
                    ->children()
                    ->inRandomOrder()
                    ->pluck('id')
                    ->first();
            })
            ->toArray();

        sort($taxonomyIds, SORT_STRING);

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [],
            'eligibility_types' => [
                'custom' => [
                    'age_group' => 'created with custom age group',
                    'disability' => 'created with custom disability',
                    'ethnicity' => 'created with custom ethnicity',
                    'gender' => 'created with custom gender',
                    'income' => 'created with custom income',
                    'language' => 'created with custom language',
                    'housing' => 'created with custom housing',
                    'other' => 'created with custom other',
                ],
                'taxonomies' => $taxonomyIds,
            ],
        ];

        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment(['data' => $payload]);

        $updateRequest = UpdateRequest::find($response->json('id'));

        $this->assertEquals($updateRequest->data, $payload);

        $this->approveUpdateRequest($updateRequest->id);

        $service = Service::where('slug', 'test-service')->first();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'eligibility_age_group_custom' => 'created with custom age group',
            'eligibility_disability_custom' => 'created with custom disability',
            'eligibility_ethnicity_custom' => 'created with custom ethnicity',
            'eligibility_gender_custom' => 'created with custom gender',
            'eligibility_income_custom' => 'created with custom income',
            'eligibility_language_custom' => 'created with custom language',
            'eligibility_housing_custom' => 'created with custom housing',
            'eligibility_other_custom' => 'created with custom other',
        ]);

        foreach ($taxonomyIds as $taxonomyId) {
            $this->assertDatabaseHas('service_eligibilities', [
                'service_id' => $service->id,
                'taxonomy_id' => $taxonomyId,
            ]);
        }
    }

    /**
     * @test
     */
    public function taxonomy_hierarchy_works_when_creating(): void
    {
        $taxonomy = Taxonomy::factory()->lgaStandards()->create();

        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => true,
            'referral_method' => Service::REFERRAL_METHOD_INTERNAL,
            'referral_button_text' => null,
            'referral_email' => $this->faker->safeEmail(),
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [$taxonomy->id],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $service = Service::findOrFail(json_decode($response->getContent(), true)['data']['id']);
        $this->assertDatabaseHas(table(ServiceTaxonomy::class), [
            'service_id' => $service->id,
            'taxonomy_id' => $taxonomy->id,
        ]);
        $this->assertDatabaseHas(table(ServiceTaxonomy::class), [
            'service_id' => $service->id,
            'taxonomy_id' => $taxonomy->parent_id,
        ]);
    }

    /**
     * @test
     */
    public function organisation_admin_for_another_organisation_cannot_create_one(): void
    {
        $anotherOrganisation = Organisation::factory()->create();
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $anotherOrganisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => true,
            'referral_method' => Service::REFERRAL_METHOD_INTERNAL,
            'referral_button_text' => null,
            'referral_email' => $this->faker->safeEmail(),
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [],
            'offerings' => [],
            'tags' => [],
            'social_medias' => [],
            'gallery_items' => [],
            'category_taxonomies' => [Taxonomy::category()->children()->firstOrFail()->id],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function audit_created_when_created(): void
    {
        $this->fakeEvents();

        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/services', [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => true,
            'referral_method' => Service::REFERRAL_METHOD_INTERNAL,
            'referral_button_text' => null,
            'referral_email' => $this->faker->safeEmail(),
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [Taxonomy::category()->children()->firstOrFail()->id],
        ]);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $response) {
            return ($event->getAction() === Audit::ACTION_CREATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $this->getResponseContent($response)['data']['id']);
        });
    }

    /**
     * @test
     */
    public function global_admin_can_create_an_active_one_with_taxonomies(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [Taxonomy::category()->children()->firstOrFail()->id],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment($payload);
    }

    /**
     * @test
     */
    public function super_admin_can_create_an_active_one_with_taxonomies(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [Taxonomy::category()->children()->firstOrFail()->id],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $responsePayload = $payload;
        $responsePayload['category_taxonomies'] = [
            [
                'id' => Taxonomy::category()->children()->firstOrFail()->id,
                'parent_id' => Taxonomy::category()->children()->firstOrFail()->parent_id,
                'slug' => Taxonomy::category()->children()->firstOrFail()->slug,
                'name' => Taxonomy::category()->children()->firstOrFail()->name,
                'created_at' => Taxonomy::category()->children()->firstOrFail()->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => Taxonomy::category()->children()->firstOrFail()->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ];
        $response->assertJsonFragment($responsePayload);
    }

    /**
     * @test
     */
    public function global_admin_can_create_one_accepting_referrals(): void
    {
        $organisation = Organisation::factory()->create();
        $taxonomy = Taxonomy::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => true,
            'referral_method' => Service::REFERRAL_METHOD_INTERNAL,
            'referral_button_text' => null,
            'referral_email' => $this->faker->safeEmail(),
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [$taxonomy->id],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment($payload);
    }

    /**
     * @test
     */
    public function global_admin_cannot_create_one_with_referral_disclaimer_showing(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => true,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function global_admin_can_create_one_with_tags(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        $taxonomy = Taxonomy::factory()->create();
        $tag1 = Tag::factory()->create();

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [
                [
                    'slug' => $tag1->slug,
                    'label' => $tag1->label,
                ],
            ],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [
                $taxonomy->id,
            ],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment([
            'slug' => $tag1->slug,
            'label' => $tag1->label,
        ]);
    }

    /**
     * @test
     */
    public function global_admin_can_create_tags_when_creating_one(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        $taxonomy = Taxonomy::factory()->create();

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [
                [
                    'slug' => 'tag-1',
                    'label' => 'Tag One',
                ],
            ],
            'social_medias' => [],
            'gallery_items' => [],
            'category_taxonomies' => [
                $taxonomy->id,
            ],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment([
            'slug' => 'tag-1',
            'label' => 'Tag One',
        ]);

        $updateRequestId = $response->json()['id'];

        Passport::actingAs(User::factory()->create()->makeSuperAdmin());

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequestId}/approve");

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('tags', [
            'slug' => 'tag-1',
            'label' => 'Tag One',
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_create_one_with_referral_disclaimer_showing(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $taxonomy = Taxonomy::factory()->create();

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => true,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [

                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital/',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [
                $taxonomy->id,
            ],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
    }

    /**
     * @test
     */
    public function slug_is_incremented_when_creating_one_with_duplicate_slug(): void
    {
        $organisation1 = Organisation::factory()->create();
        $organisation2 = Organisation::factory()->create();
        $organisation3 = Organisation::factory()->create();
        $organisation4 = Organisation::factory()->create();
        $taxonomy = Taxonomy::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        //Given that a super admin is logged in
        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation1->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [],
            'gallery_items' => [],
            'category_taxonomies' => [$taxonomy->id],
        ];

        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        // Slug is not incremented if no clashes exist
        $response->assertJsonFragment([
            'slug' => 'test-service',
        ]);

        $this->assertDatabaseHas((new Service())->getTable(), [
            'organisation_id' => $organisation1->id,
            'slug' => 'test-service',
        ]);

        $payload['organisation_id'] = $organisation2->id;

        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        // slug is incremented when clashes exist
        $response->assertJsonFragment([
            'slug' => 'test-service-1',
        ]);

        $this->assertDatabaseHas((new Service())->getTable(), [
            'organisation_id' => $organisation2->id,
            'slug' => 'test-service-1',
        ]);

        $payload['organisation_id'] = $organisation3->id;
        $payload['slug'] = 'test-service-1';

        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        // slug continues to increment as long as there are clashes
        $response->assertJsonFragment([
            'slug' => 'test-service-2',
        ]);

        $this->assertDatabaseHas((new Service())->getTable(), [
            'organisation_id' => $organisation3->id,
            'slug' => 'test-service-2',
        ]);

        $service = Service::factory()->create([
            'slug' => 'demo-service-1',
        ]);

        $payload['organisation_id'] = $organisation4->id;
        $payload['slug'] = 'demo-service';

        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        // slug is permissable if same slug with suffix exists
        $response->assertJsonFragment([
            'slug' => 'demo-service',
        ]);

        $this->assertDatabaseHas((new Service())->getTable(), [
            'organisation_id' => $organisation4->id,
            'slug' => 'demo-service',
        ]);
    }

    /**
     * @test
     */
    public function global_admin_is_added_as_service_admin_when_organisation_admin_creates_one(): void
    {
        $globalAdmin = User::factory()->create()->makeGlobalAdmin();
        $organisation = Organisation::factory()->create();
        $orgAdmin = User::factory()->create()->makeOrganisationAdmin($organisation);

        //Given an organisation admin is logged in
        Passport::actingAs($orgAdmin);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [],
            'gallery_items' => [],
            'category_taxonomies' => [],
        ];

        //When they create a service
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $responseData = json_decode($response->getContent());

        // Simulate frontend check by making call with UpdateRequest ID.
        $updateRequestId = $responseData->id;

        Passport::actingAs(User::factory()->create()->makeSuperAdmin());

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequestId}/approve");

        $response->assertStatus(Response::HTTP_OK);

        $service = Service::where('slug', 'test-service')->first();

        $this->assertDatabaseHas((new UserRole)->getTable(), [
            'user_id' => $globalAdmin->id,
            'role_id' => Role::serviceAdmin()->id,
            'service_id' => $service->id,
        ]);

        $this->assertDatabaseHas((new UserRole)->getTable(), [
            'user_id' => $globalAdmin->id,
            'role_id' => Role::serviceWorker()->id,
            'service_id' => $service->id,
        ]);

        $this->assertTrue($globalAdmin->isServiceWorker($service));
        $this->assertTrue($globalAdmin->isServiceAdmin($service));
        $this->assertTrue($globalAdmin->isOrganisationAdmin($service->organisation));
    }

    /**
     * @test
     */
    public function global_admin_is_added_as_service_admin_when_other_global_admin_creates_one(): void
    {
        $taxonomy = Taxonomy::factory()->create();
        $globalAdmin1 = User::factory()->create()->makeGlobalAdmin();
        $globalAdmin2 = User::factory()->create()->makeGlobalAdmin();
        $organisation = Organisation::factory()->create();

        //Given an global admin is logged in
        Passport::actingAs($globalAdmin1);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [],
            'gallery_items' => [],
            'category_taxonomies' => [
                $taxonomy->id,
            ],
        ];

        //When they create a service
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $responseData = $response->json();

        // Simulate frontend check by making call with UpdateRequest ID.
        $updateRequestId = $responseData['id'];

        Passport::actingAs(User::factory()->create()->makeSuperAdmin());

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequestId}/approve");

        $response->assertStatus(Response::HTTP_OK);

        $service = Service::where('slug', $payload['slug'])->firstOrFail();

        $this->assertDatabaseHas((new UserRole)->getTable(), [
            'user_id' => $globalAdmin1->id,
            'role_id' => Role::serviceAdmin()->id,
            'service_id' => $service->id,
        ]);

        $this->assertDatabaseHas((new UserRole)->getTable(), [
            'user_id' => $globalAdmin1->id,
            'role_id' => Role::serviceWorker()->id,
            'service_id' => $service->id,
        ]);

        $this->assertDatabaseHas((new UserRole)->getTable(), [
            'user_id' => $globalAdmin2->id,
            'role_id' => Role::serviceAdmin()->id,
            'service_id' => $service->id,
        ]);

        $this->assertDatabaseHas((new UserRole)->getTable(), [
            'user_id' => $globalAdmin2->id,
            'role_id' => Role::serviceWorker()->id,
            'service_id' => $service->id,
        ]);

        $this->assertTrue($globalAdmin1->isServiceWorker($service));
        $this->assertTrue($globalAdmin1->isServiceAdmin($service));
        $this->assertTrue($globalAdmin1->isOrganisationAdmin($service->organisation));

        $this->assertTrue($globalAdmin2->isServiceWorker($service));
        $this->assertTrue($globalAdmin2->isServiceAdmin($service));
        $this->assertTrue($globalAdmin2->isOrganisationAdmin($service->organisation));
    }

    /**
     * @test
     */
    public function create_service_with_eligibility_taxonomies_as_super_admin(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        $taxonomyIds = Taxonomy::serviceEligibility()
            ->children
            ->map(function ($taxonomy) {
                return $taxonomy
                    ->children()
                    ->inRandomOrder()
                    ->pluck('id')
                    ->first();
            })->toArray();

        sort($taxonomyIds, SORT_STRING);

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => null,
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [],
            'gallery_items' => [],
            'category_taxonomies' => [Taxonomy::category()->children()->firstOrFail()->id],
        ];

        $taxonomyPayload = [
            'eligibility_types' => [
                'custom' => [
                    'age_group' => null,
                    'disability' => null,
                    'ethnicity' => null,
                    'gender' => null,
                    'income' => null,
                    'language' => null,
                    'housing' => null,
                    'other' => null,
                ],
                'taxonomies' => $taxonomyIds,
            ],
        ];

        $payload = array_merge($taxonomyPayload, $payload);

        $response = $this->json('POST', '/core/v1/services', $payload);
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($taxonomyPayload);
    }

    /**
     * @test
     */
    public function create_service_with_custom_eligibility_fields_as_super_admin(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => null,
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [],
            'gallery_items' => [],
            'category_taxonomies' => [Taxonomy::category()->children()->firstOrFail()->id],
        ];

        $taxonomyPayload = [
            'eligibility_types' => [
                'custom' => [
                    'age_group' => 'custom age group',
                    'disability' => 'custom disability',
                    'ethnicity' => 'custom ethnicity',
                    'gender' => 'custom gender',
                    'income' => 'custom income',
                    'language' => 'custom language',
                    'housing' => 'custom housing',
                    'other' => 'custom other',
                ],
                'taxonomies' => [],
            ],
        ];

        $payload = array_merge($taxonomyPayload, $payload);

        $response = $this->json('POST', '/core/v1/services', $payload);
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($taxonomyPayload);
    }

    /**
     * @test
     */
    public function create_service_with_custom_fields_and_eligibility_taxonomy_ids_as_global_admin(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        $taxonomyIds = Taxonomy::serviceEligibility()
            ->children
            ->map(function ($taxonomy) {
                return $taxonomy
                    ->children()
                    ->inRandomOrder()
                    ->pluck('id')
                    ->first();
            })
            ->toArray();

        sort($taxonomyIds, SORT_STRING);

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => null,
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [],
            'gallery_items' => [],
            'category_taxonomies' => [Taxonomy::category()->children()->firstOrFail()->id],
        ];

        $taxonomyPayload = [
            'eligibility_types' => [
                'custom' => [
                    'age_group' => 'created with custom age group',
                    'disability' => 'created with custom disability',
                    'ethnicity' => 'created with custom ethnicity',
                    'gender' => 'created with custom gender',
                    'income' => 'created with custom income',
                    'language' => 'created with custom language',
                    'housing' => 'created with custom housing',
                    'other' => 'created with custom other',
                ],
                'taxonomies' => $taxonomyIds,
            ],
        ];

        $payload = array_merge($taxonomyPayload, $payload);

        $response = $this->json('POST', '/core/v1/services', $payload);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($taxonomyPayload);

        $responseData = json_decode($response->getContent());

        // Simulate frontend check by making call with UpdateRequest ID.
        $updateRequestId = $responseData->id;

        Passport::actingAs(User::factory()->create()->makeSuperAdmin());

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequestId}/approve");

        $response->assertStatus(Response::HTTP_OK);

        $service = Service::where('slug', 'test-service')->first();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'eligibility_age_group_custom' => 'created with custom age group',
            'eligibility_disability_custom' => 'created with custom disability',
            'eligibility_ethnicity_custom' => 'created with custom ethnicity',
            'eligibility_gender_custom' => 'created with custom gender',
            'eligibility_income_custom' => 'created with custom income',
            'eligibility_language_custom' => 'created with custom language',
            'eligibility_housing_custom' => 'created with custom housing',
            'eligibility_other_custom' => 'created with custom other',
        ]);

        foreach ($taxonomyIds as $taxonomyId) {
            $this->assertDatabaseHas('service_eligibilities', [
                'service_id' => $service->id,
                'taxonomy_id' => $taxonomyId,
            ]);
        }
    }

    /**
     * @test
     */
    public function create_service_with_custom_fields_and_eligibility_taxonomy_ids_as_super_admin(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        $taxonomyIds = Taxonomy::serviceEligibility()
            ->children
            ->map(function ($taxonomy) {
                return $taxonomy
                    ->children()
                    ->inRandomOrder()
                    ->pluck('id')
                    ->first();
            })
            ->toArray();

        sort($taxonomyIds, SORT_STRING);

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => null,
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [],
            'gallery_items' => [],
            'category_taxonomies' => [Taxonomy::category()->children()->firstOrFail()->id],
        ];

        $taxonomyPayload = [
            'eligibility_types' => [
                'custom' => [
                    'age_group' => 'created with custom age group',
                    'disability' => 'created with custom disability',
                    'ethnicity' => 'created with custom ethnicity',
                    'gender' => 'created with custom gender',
                    'income' => 'created with custom income',
                    'language' => 'created with custom language',
                    'housing' => 'created with custom housing',
                    'other' => 'created with custom other',
                ],
                'taxonomies' => $taxonomyIds,
            ],
        ];

        $payload = array_merge($taxonomyPayload, $payload);

        $response = $this->json('POST', '/core/v1/services', $payload);
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($taxonomyPayload);
    }

    /**
     * @test
     */
    public function CreateServiceWithSocialMediasAsGlobalAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => null,
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [Taxonomy::category()->children()->firstOrFail()->id],
        ];
        $response = $this->json('POST', '/core/v1/services', $payload);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['social_medias' => [
            [
                'type' => SocialMedia::TYPE_INSTAGRAM,
                'url' => 'https://www.instagram.com/ayupdigital',
            ],
        ],
        ]);
    }

    /*
     * Get a specific service.
     */

    /**
     * @test
     */
    public function guest_can_view_one(): void
    {
        $service = Service::factory()->create();
        $taxonomy = Taxonomy::factory()->create();
        $tag1 = Tag::factory()->create();

        $service->usefulInfos()->create([
            'title' => 'Did You Know?',
            'description' => 'This is a test description',
            'order' => 1,
        ]);
        $service->offerings()->create([
            'offering' => 'Weekly club',
            'order' => 1,
        ]);
        $service->socialMedias()->create([
            'type' => SocialMedia::TYPE_INSTAGRAM,
            'url' => 'https://www.instagram.com/ayupdigital',
        ]);
        $service->serviceTaxonomies()->create([
            'taxonomy_id' => $taxonomy->id,
        ]);

        $service->tags()->attach([
            $tag1->id,
        ]);

        $response = $this->json('GET', "/core/v1/services/{$service->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $service->id,
            'organisation_id' => $service->organisation_id,
            'has_logo' => $service->hasLogo(),
            'slug' => $service->slug,
            'name' => $service->name,
            'type' => $service->type,
            'status' => $service->status,
            'intro' => $service->intro,
            'description' => $service->description,
            'wait_time' => $service->wait_time,
            'is_free' => $service->is_free,
            'fees_text' => $service->fees_text,
            'fees_url' => $service->fees_url,
            'testimonial' => $service->testimonial,
            'video_embed' => $service->video_embed,
            'url' => $service->url,
            'contact_name' => $service->contact_name,
            'contact_phone' => $service->contact_phone,
            'contact_email' => $service->contact_email,
            'show_referral_disclaimer' => $service->show_referral_disclaimer,
            'referral_method' => $service->referral_method,
            'referral_button_text' => $service->referral_button_text,
            'referral_email' => $service->referral_email,
            'referral_url' => $service->referral_url,
            'cqc_location_id' => $service->cqc_location_id,
            'useful_infos' => [
                [
                    'title' => 'Did You Know?',
                    'description' => 'This is a test description',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],

            'category_taxonomies' => [
                [
                    'id' => $taxonomy->id,
                    'parent_id' => $taxonomy->parent_id,
                    'slug' => $taxonomy->slug,
                    'name' => $taxonomy->name,
                    'created_at' => $taxonomy->created_at->format(CarbonImmutable::ISO8601),
                    'updated_at' => $taxonomy->updated_at->format(CarbonImmutable::ISO8601),
                ],
            ],
            'tags' => [
                [
                    'id' => $tag1->id,
                    'slug' => $tag1->slug,
                    'label' => $tag1->label,
                    'created_at' => $tag1->created_at->format(CarbonImmutable::ISO8601),
                    'updated_at' => $tag1->updated_at->format(CarbonImmutable::ISO8601),
                ],
            ],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'gallery_items' => [],
            'last_modified_at' => $service->last_modified_at->format(CarbonImmutable::ISO8601),
            'created_at' => $service->created_at->format(CarbonImmutable::ISO8601),
        ]);
    }

    /**
     * @test
     */
    public function guest_can_view_one_by_slug(): void
    {
        $service = Service::factory()->create();
        $taxonomy = Taxonomy::factory()->create();
        $service->usefulInfos()->create([
            'title' => 'Did You Know?',
            'description' => 'This is a test description',
            'order' => 1,
        ]);
        $service->offerings()->create([
            'offering' => 'Weekly club',
            'order' => 1,
        ]);
        $service->socialMedias()->create([
            'type' => SocialMedia::TYPE_INSTAGRAM,
            'url' => 'https://www.instagram.com/ayupdigital',
        ]);
        $service->serviceTaxonomies()->create([
            'taxonomy_id' => $taxonomy->id,
        ]);

        $response = $this->json('GET', "/core/v1/services/{$service->slug}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $service->id,
            'organisation_id' => $service->organisation_id,
            'has_logo' => $service->hasLogo(),
            'slug' => $service->slug,
            'name' => $service->name,
            'type' => $service->type,
            'status' => $service->status,
            'intro' => $service->intro,
            'description' => $service->description,
            'wait_time' => $service->wait_time,
            'is_free' => $service->is_free,
            'fees_text' => $service->fees_text,
            'fees_url' => $service->fees_url,
            'testimonial' => $service->testimonial,
            'video_embed' => $service->video_embed,
            'url' => $service->url,
            'contact_name' => $service->contact_name,
            'contact_phone' => $service->contact_phone,
            'contact_email' => $service->contact_email,
            'show_referral_disclaimer' => $service->show_referral_disclaimer,
            'referral_method' => $service->referral_method,
            'referral_button_text' => $service->referral_button_text,
            'referral_email' => $service->referral_email,
            'referral_url' => $service->referral_url,
            'cqc_location_id' => $service->cqc_location_id,
            'useful_infos' => [
                [
                    'title' => 'Did You Know?',
                    'description' => 'This is a test description',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'category_taxonomies' => [
                [
                    'id' => $taxonomy->id,
                    'parent_id' => $taxonomy->parent_id,
                    'slug' => $taxonomy->slug,
                    'name' => $taxonomy->name,
                    'created_at' => $taxonomy->created_at->format(CarbonImmutable::ISO8601),
                    'updated_at' => $taxonomy->updated_at->format(CarbonImmutable::ISO8601),
                ],
            ],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'gallery_items' => [],
            'last_modified_at' => $service->last_modified_at->format(CarbonImmutable::ISO8601),
            'created_at' => $service->created_at->format(CarbonImmutable::ISO8601),
        ]);
    }

    /**
     * @test
     */
    public function offerings_are_returned_in_order(): void
    {
        $service = Service::factory()->create();
        $taxonomy = Taxonomy::factory()->create();
        $service->usefulInfos()->create([
            'title' => 'Did You Know?',
            'description' => 'This is a test description',
            'order' => 1,
        ]);
        $service->offerings()->createMany([
            [
                'offering' => 'First club',
                'order' => 1,
            ],
            [
                'offering' => 'Second club',
                'order' => 2,
            ],
            [
                'offering' => 'Third club',
                'order' => 3,
            ],
        ]);
        $service->socialMedias()->create([
            'type' => SocialMedia::TYPE_INSTAGRAM,
            'url' => 'https://www.instagram.com/ayupdigital/',
        ]);
        $service->serviceTaxonomies()->create([
            'taxonomy_id' => $taxonomy->id,
        ]);

        $response = $this->json('GET', "/core/v1/services/{$service->slug}");

        $response->assertStatus(Response::HTTP_OK);
        $offerings = $response->json('data.offerings');
        $this->assertEquals(1, $offerings[0]['order']);
        $this->assertEquals(2, $offerings[1]['order']);
        $this->assertEquals(3, $offerings[2]['order']);
    }

    /**
     * @test
     */
    public function service_eligiblity_custom_fields_schema_on_index(): void
    {
        $service = Service::factory()->withOfferings()->withUsefulInfo()->withSocialMedia()->withCustomEligibilities()->withCategoryTaxonomies()
            ->create();

        $service->save();

        $response = $this->get(route('core.v1.services.index'));

        $response->assertJsonFragment([
            'eligibility_types' => [
                'custom' => [
                    'age_group' => $service->eligibility_age_group_custom,
                    'disability' => $service->eligibility_disability_custom,
                    'ethnicity' => $service->eligibility_ethnicity_custom,
                    'gender' => $service->eligibility_gender_custom,
                    'income' => $service->eligibility_income_custom,
                    'language' => $service->eligibility_language_custom,
                    'housing' => $service->eligibility_housing_custom,
                    'other' => $service->eligibility_other_custom,
                ],
                'taxonomies' => [],
            ],
        ]);
    }

    /**
     * @test
     */
    public function service_eligiblity_taxonomy_id_schema_on_index(): void
    {
        $service = Service::factory()->withOfferings()->withUsefulInfo()->withSocialMedia()->withEligibilityTaxonomies()->withCategoryTaxonomies()
            ->create();

        $taxonomyIds = $service->serviceEligibilities()->pluck('taxonomy_id')->all();

        $response = $this->get(route('core.v1.services.index'));

        $response->assertJsonFragment([
            'eligibility_types' => [
                'custom' => [
                    'age_group' => null,
                    'disability' => null,
                    'ethnicity' => null,
                    'gender' => null,
                    'income' => null,
                    'language' => null,
                    'housing' => null,
                    'other' => null,
                ],
                'taxonomies' => $taxonomyIds,
            ],
        ]);
    }

    /**
     * @test
     */
    public function service_eligibility_taxonomy_and_custom_fields_schema_on_index(): void
    {
        $service = Service::factory()->withOfferings()->withUsefulInfo()->withSocialMedia()->withCustomEligibilities()->withCategoryTaxonomies()->withEligibilityTaxonomies()
            ->create();

        $taxonomyIds = $service->serviceEligibilities()->pluck('taxonomy_id')->all();
        sort($taxonomyIds, SORT_STRING);

        $response = $this->get(route('core.v1.services.index'));

        $response->assertJsonFragment([
            'eligibility_types' => [
                'custom' => [
                    'age_group' => $service->eligibility_age_group_custom,
                    'disability' => $service->eligibility_disability_custom,
                    'ethnicity' => $service->eligibility_ethnicity_custom,
                    'gender' => $service->eligibility_gender_custom,
                    'income' => $service->eligibility_income_custom,
                    'language' => $service->eligibility_language_custom,
                    'housing' => $service->eligibility_housing_custom,
                    'other' => $service->eligibility_other_custom,
                ],
                'taxonomies' => $taxonomyIds,
            ],
        ]);
    }

    /**
     * @test
     */
    public function service_eligiblity_custom_fields_schema_on_show(): void
    {
        $service = Service::factory()->withOfferings()->withUsefulInfo()->withSocialMedia()->withCustomEligibilities()->withCategoryTaxonomies()
            ->create();

        $response = $this->get(route('core.v1.services.show', $service->id));

        $response->assertJsonFragment([
            'eligibility_types' => [
                'custom' => [
                    'age_group' => $service->eligibility_age_group_custom,
                    'disability' => $service->eligibility_disability_custom,
                    'ethnicity' => $service->eligibility_ethnicity_custom,
                    'gender' => $service->eligibility_gender_custom,
                    'income' => $service->eligibility_income_custom,
                    'language' => $service->eligibility_language_custom,
                    'housing' => $service->eligibility_housing_custom,
                    'other' => $service->eligibility_other_custom,
                ],
                'taxonomies' => [],
            ],
        ]);
    }

    /**
     * @test
     */
    public function service_eligiblity_taxonomy_id_schema_on_show(): void
    {
        $service = Service::factory()->withOfferings()->withUsefulInfo()->withSocialMedia()->withCategoryTaxonomies()->withEligibilityTaxonomies()
            ->create();

        $taxonomyIds = $service->serviceEligibilities()->pluck('taxonomy_id')->all();

        $response = $this->get(route('core.v1.services.show', $service->id));

        $response->assertJsonFragment([
            'eligibility_types' => [
                'custom' => [
                    'age_group' => null,
                    'disability' => null,
                    'ethnicity' => null,
                    'gender' => null,
                    'income' => null,
                    'language' => null,
                    'housing' => null,
                    'other' => null,
                ],
                'taxonomies' => $taxonomyIds,
            ],
        ]);
    }

    /**
     * @test
     */
    public function service_eligibility_taxonomy_and_custom_fields_schema_on_show(): void
    {
        $service = Service::factory()->withOfferings()->withUsefulInfo()->withSocialMedia()->withCustomEligibilities()->withCategoryTaxonomies()->withEligibilityTaxonomies()
            ->create();

        $taxonomyIds = $service->serviceEligibilities()->pluck('taxonomy_id')->all();

        $response = $this->get(route('core.v1.services.show', $service->id));

        $response->assertJsonFragment([
            'eligibility_types' => [
                'custom' => [
                    'age_group' => $service->eligibility_age_group_custom,
                    'disability' => $service->eligibility_disability_custom,
                    'ethnicity' => $service->eligibility_ethnicity_custom,
                    'gender' => $service->eligibility_gender_custom,
                    'income' => $service->eligibility_income_custom,
                    'language' => $service->eligibility_language_custom,
                    'housing' => $service->eligibility_housing_custom,
                    'other' => $service->eligibility_other_custom,
                ],
                'taxonomies' => $taxonomyIds,
            ],
        ]);
    }

    /**
     * @test
     */
    public function audit_created_when_viewed(): void
    {
        $this->fakeEvents();

        $service = Service::factory()->create();
        $service->usefulInfos()->create([
            'title' => 'Did You Know?',
            'description' => 'This is a test description',
            'order' => 1,
        ]);
        $service->offerings()->create([
            'offering' => 'Weekly club',
            'order' => 1,
        ]);
        $service->socialMedias()->create([
            'type' => SocialMedia::TYPE_INSTAGRAM,
            'url' => 'https://www.instagram.com/ayupdigital/',
        ]);
        $service->serviceTaxonomies()->create([
            'taxonomy_id' => Taxonomy::factory()->create()->id,
        ]);

        $this->json('GET', "/core/v1/services/{$service->id}");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($service) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getModel()->id === $service->id);
        });
    }

    /*
     * Update a specific service.
     */

    /**
     * @test
     */
    public function guest_cannot_update_one(): void
    {
        $service = Service::factory()->create();

        $response = $this->json('PUT', "/core/v1/services/{$service->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_update_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);

        Passport::actingAs($user);

        $response = $this->json('PUT', "/core/v1/services/{$service->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_can_update_one(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $payload = [
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'category_taxonomies' => [
                $taxonomy->id,
                $taxonomy->parent_id,
            ],
            'eligibility_types' => [
                'custom' => [],
                'taxonomies' => [],
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['data' => $payload]);
    }

    /**
     * @test
     */
    public function service_admin_can_update_one_with_no_form_of_contact(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $payload = [
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => null,
            'contact_name' => $this->faker->name(),
            'contact_phone' => null,
            'contact_email' => null,
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'category_taxonomies' => [
                $taxonomy->id,
                $taxonomy->parent_id,
            ],
            'eligibility_types' => [
                'custom' => [],
                'taxonomies' => [],
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['data' => $payload]);
    }

    /**
     * @test
     */
    public function service_admin_cannot_update_one_with_tags(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $payload = [
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => null,
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [
                [
                    'slug' => 'tag-1',
                    'label' => 'Tag One',
                ],
            ],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'category_taxonomies' => [
                $taxonomy->id,
                $taxonomy->parent_id,
            ],
            'eligibility_types' => [
                'custom' => [],
                'taxonomies' => [],
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function global_admin_can_update_most_fields_for_one(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $tag1 = Tag::factory()->create();

        Passport::actingAs($user);

        $payload = [
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => $service->referral_method,
            'referral_button_text' => $service->referral_button_text,
            'referral_email' => $service->referral_email,
            'referral_url' => $service->referral_url,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [
                [
                    'slug' => $tag1->slug,
                    'label' => $tag1->label,
                ],
            ],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [
                $taxonomy->id,
            ],
            'eligibility_types' => [
                'custom' => [],
                'taxonomies' => [],
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['data' => $payload]);

        $this->assertDatabaseMissing('services', [
            'id' => $service->id,
            'intro' => 'This is a test intro',
        ]);
    }

    /**
     * @test
     */
    public function global_admin_cannot_update_with_non_numeric_phone(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));

        Passport::actingAs($user);

        $payload = [
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => 'Tel 01234 567890',
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => $service->referral_method,
            'referral_button_text' => $service->referral_button_text,
            'referral_email' => $service->referral_email,
            'referral_url' => $service->referral_url,
            'cqc_location_id' => $service->cqc_location_id,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'gallery_items' => [],
            'category_taxonomies' => [
                $taxonomy->id,
            ],
            'eligibility_types' => [
                'custom' => [],
                'taxonomies' => [],
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function global_admin_cannot_update_show_referral_disclaimer_for_one(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));

        Passport::actingAs($user);

        $payload = [
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => true,
            'referral_method' => $service->referral_method,
            'referral_button_text' => $service->referral_button_text,
            'referral_email' => $service->referral_email,
            'referral_url' => $service->referral_url,
            'cqc_location_id' => $service->cqc_location_id,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'category_taxonomies' => [
                $taxonomy->id,
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function audit_created_when_updated(): void
    {
        $this->fakeEvents();

        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $this->json('PUT', "/core/v1/services/{$service->id}", [
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => $service->show_referral_disclaimer,
            'referral_method' => $service->referral_method,
            'referral_button_text' => $service->referral_button_text,
            'referral_email' => $service->referral_email,
            'referral_url' => $service->referral_url,
            'cqc_location_id' => $service->cqc_location_id,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'category_taxonomies' => [
                $taxonomy->id,
                $taxonomy->parent_id,
            ],
        ]);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $service) {
            return ($event->getAction() === Audit::ACTION_UPDATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $service->id);
        });
    }

    /**
     * @test
     */
    public function service_admin_cannot_update_taxonomies(): void
    {
        $service = Service::factory()->create();
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $newTaxonomy = Taxonomy::category()
            ->children()
            ->where('id', '!=', $taxonomy->id)
            ->firstOrFail();
        $payload = [
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => true,
            'referral_method' => Service::REFERRAL_METHOD_INTERNAL,
            'referral_button_text' => null,
            'referral_email' => $this->faker->safeEmail(),
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'category_taxonomies' => [
                $taxonomy->id,
                $newTaxonomy->id,
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function global_admin_can_update_taxonomies(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $service = Service::factory()->create();
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));

        Passport::actingAs($user);

        $newTaxonomy = Taxonomy::category()
            ->children()
            ->where('id', '!=', $taxonomy->id)
            ->firstOrFail();
        $payload = [
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => true,
            'referral_method' => Service::REFERRAL_METHOD_INTERNAL,
            'referral_button_text' => null,
            'referral_email' => $this->faker->safeEmail(),
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'category_taxonomies' => [
                $taxonomy->id,
                $newTaxonomy->id,
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     */
    public function service_admin_can_update_cqc_location_id(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
            'cqc_location_id' => null,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $cqcLocationId = $this->faker->numerify('#-#########');

        $payload = [
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $cqcLocationId,
            'useful_infos' => [],
            'offerings' => [],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'category_taxonomies' => [
                $taxonomy->id,
                $taxonomy->parent_id,
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment(['data' => $payload]);

        $this->assertDatabaseHas(table(UpdateRequest::class), ['updateable_id' => $service->id]);
        $updateRequest = UpdateRequest::where('updateable_id', $service->id)->firstOrFail();
        $this->assertEquals($cqcLocationId, $updateRequest->data['cqc_location_id']);
    }

    /**
     * @test
     */
    public function service_admin_cannot_update_status(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $payload = [
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => true,
            'referral_method' => Service::REFERRAL_METHOD_INTERNAL,
            'referral_button_text' => null,
            'referral_email' => $this->faker->safeEmail(),
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [],
            'offerings' => [],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'category_taxonomies' => [
                $taxonomy->id,
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function service_admin_cannot_update_slug(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $payload = [
            'slug' => 'new-slug',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => true,
            'referral_method' => Service::REFERRAL_METHOD_INTERNAL,
            'referral_button_text' => null,
            'referral_email' => $this->faker->safeEmail(),
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [],
            'offerings' => [],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'category_taxonomies' => [
                $taxonomy->id,
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function service_admin_can_update_logo(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $service = Service::factory()->create([
            'slug' => 'test-service',
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));

        $image = File::factory()->pendingAssignment()->imagePng()->create();

        Passport::actingAs($user);

        $payload = [
            'logo_file_id' => $image->id,
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $this->approveUpdateRequest($response->json()['id']);

        $response = $this->json('GET', "/core/v1/services/{$service->id}");

        $response->assertJsonFragment([
            'image' => [
                'id' => $image->id,
                'mime_type' => $image->mime_type,
                'alt_text' => $image->meta['alt_text'],
                'url' => $image->url(),
            ],
        ]);
    }

    /**
     * @test
     */
    public function global_admin_can_update_status(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));

        Passport::actingAs($user);

        $payload = [
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => true,
            'referral_method' => Service::REFERRAL_METHOD_INTERNAL,
            'referral_button_text' => null,
            'referral_email' => $this->faker->safeEmail(),
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [],
            'offerings' => [],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'category_taxonomies' => [
                $taxonomy->id,
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     */
    public function global_admin_can_update_slug(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));

        Passport::actingAs($user);

        $payload = [
            'slug' => 'new-slug',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => true,
            'referral_method' => Service::REFERRAL_METHOD_INTERNAL,
            'referral_button_text' => null,
            'referral_email' => $this->faker->safeEmail(),
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [],
            'offerings' => [],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'category_taxonomies' => [
                $taxonomy->id,
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     */
    public function updateServiceWithSlugClashAsGlobalAdmin200()
    {
        $now = Date::now();
        Date::setTestNow($now);

        $organisation = Organisation::factory()->create();

        $user = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($user);

        $service1 = Service::factory()->create([
            'organisation_id' => $organisation->id,
            'slug' => 'test-slug',
        ]);
        $service1->serviceTaxonomies()->create([
            'taxonomy_id' => Taxonomy::category()->children()->firstOrFail()->id,
        ]);

        $service2 = Service::factory()->create([
            'organisation_id' => $organisation->id,
            'slug' => 'other-slug',
        ]);
        $service2->serviceTaxonomies()->create([
            'taxonomy_id' => Taxonomy::category()->children()->firstOrFail()->id,
        ]);

        $payload = [
            'slug' => 'test-slug',
        ];

        $response = $this->json('PUT', "/core/v1/services/{$service2->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::find($response->json('id'));

        $this->assertEquals('test-slug', $updateRequest->data['slug']);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas('services', [
            'id' => $service2->id,
            'slug' => 'test-slug-1',
        ]);
    }

    /**
     * @test
     */
    public function global_admin_can_create_tags_when_updating(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $payload = [
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => $service->referral_method,
            'referral_button_text' => $service->referral_button_text,
            'referral_email' => $service->referral_email,
            'referral_url' => $service->referral_url,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [
                [
                    'slug' => 'tag-1',
                    'label' => 'Tag One',
                ],
            ],
            'social_medias' => [],
            'gallery_items' => [],
            'category_taxonomies' => [
                $taxonomy->id,
            ],
            'eligibility_types' => [
                'custom' => [],
                'taxonomies' => [],
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'slug' => 'tag-1',
            'label' => 'Tag One',
        ]);

        $this->assertDatabaseHas(table(UpdateRequest::class), ['updateable_id' => $service->id]);
        $updateRequest = UpdateRequest::where('updateable_id', $service->id)->firstOrFail();
        $this->assertEquals([[
            'slug' => 'tag-1',
            'label' => 'Tag One',
        ]], $updateRequest->data['tags']);
    }

    /**
     * @test
     */
    public function referral_email_must_be_provided_when_referral_type_is_internal(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $payload = [
            'slug' => 'new-slug',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => true,
            'referral_method' => Service::REFERRAL_METHOD_INTERNAL,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [],
            'offerings' => [],
            'social_medias' => [],
            'category_taxonomies' => [
                $taxonomy->id,
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertArrayHasKey('referral_email', $this->getResponseContent($response)['errors']);
    }

    /**
     * @test
     */
    public function service_admin_cannot_update_referral_details(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
            'referral_method' => Service::REFERRAL_METHOD_INTERNAL,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $payload = [
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [],
            'offerings' => [],
            'social_medias' => [],
            'category_taxonomies' => [
                $taxonomy->id,
                $taxonomy->parent_id,
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertCount(1, $this->getResponseContent($response)['errors']);
        $this->assertArrayHasKey('referral_method', $this->getResponseContent($response)['errors']);
    }

    /**
     * @test
     */
    public function global_admin_can_update_referral_details(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
            'referral_method' => Service::REFERRAL_METHOD_INTERNAL,
            'referral_email' => $this->faker->safeEmail(),
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $payload = [
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => $service->show_referral_disclaimer,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'ends_at' => null,
            'useful_infos' => [],
            'offerings' => [],
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_INSTAGRAM,
                    'url' => 'https://www.instagram.com/ayupdigital',
                ],
            ],
            'category_taxonomies' => [
                $taxonomy->id,
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     */
    public function update_service_with_eligibility_taxonomies(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $service = Service::factory()->withOfferings()->withUsefulInfo()->withSocialMedia()->withCustomEligibilities()->withEligibilityTaxonomies()->withCategoryTaxonomies()
            ->create();

        $taxonomyIds = Taxonomy::serviceEligibility()->children->map(function ($taxonomy) {
            // ensure we assign a different taxonomy ID
            return $taxonomy->children()
                ->inRandomOrder()
                ->first()
                ->id;
        })
            ->toArray();

        sort($taxonomyIds, SORT_STRING);

        Passport::actingAs($user);

        $payload = [
            'eligibility_types' => [
                'taxonomies' => $taxonomyIds,
            ],
        ];

        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);

        $service->load('serviceEligibilities');

        foreach ($service->serviceEligibilities['taxonomies'] as $taxonomyId) {
            $this->assertTrue(in_array($taxonomyId, $taxonomyIds));
        }
    }

    /**
     * @test
     */
    public function update_service_with_custom_eligibility_fields(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        $service = Service::factory()->withOfferings()->withUsefulInfo()->withSocialMedia()->withCustomEligibilities()->withEligibilityTaxonomies()->withCategoryTaxonomies()
            ->create();

        Passport::actingAs($user);

        $payload = [
            'eligibility_types' => [
                'custom' => [
                    'age_group' => 'I am updating custom age_group',
                    'disability' => 'I am updating custom disability',
                    'ethnicity' => 'I am updating custom ethnicity',
                    'gender' => 'I am updating custom gender',
                    'income' => 'I am updating custom income',
                    'language' => 'I am updating custom language',
                    'housing' => 'I am updating custom housing',
                    'other' => 'I am updating custom other',
                ],
            ],
        ];

        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);

        // request the service to check update request was auto-applied
        $service = $service->fresh()->load('serviceEligibilities');

        foreach ($payload['eligibility_types']['custom'] as $customFieldName => $customFieldValue) {
            $this->assertEquals($customFieldValue, $service->{'eligibility_' . $customFieldName . '_custom'});
        }
    }

    /**
     * @test
     */
    public function update_service_with_custom_fields_and_eligibility_taxonomies(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        $service = Service::factory()->withOfferings()->withUsefulInfo()->withSocialMedia()->withCustomEligibilities()->withEligibilityTaxonomies()->withCategoryTaxonomies()
            ->create();

        $taxonomyIds = Taxonomy::serviceEligibility()->children->map(function ($taxonomy) {
            // ensure we assign a different taxonomy ID
            return $taxonomy->children()
                ->inRandomOrder()
                ->first()
                ->id;
        })
            ->toArray();

        sort($taxonomyIds, SORT_STRING);

        Passport::actingAs($user);

        $payload = [
            'eligibility_types' => [
                'custom' => [
                    'age_group' => 'I am updating custom age_group',
                    'disability' => 'I am updating custom disability',
                    'ethnicity' => 'I am updating custom ethnicity',
                    'gender' => 'I am updating custom gender',
                    'income' => 'I am updating custom income',
                    'language' => 'I am updating custom language',
                    'housing' => 'I am updating custom housing',
                    'other' => 'I am updating custom other',
                ],
                'taxonomies' => $taxonomyIds,
            ],
        ];

        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);

        $service = $service->fresh();
        $service->load('serviceEligibilities');

        foreach ($service->serviceEligibilities['taxonomies'] as $taxonomyId) {
            $this->assertTrue(in_array($taxonomyId, $taxonomyIds));
        }

        foreach ($payload['eligibility_types']['custom'] as $customFieldName => $customFieldValue) {
            $this->assertEquals($customFieldValue, $service->{'eligibility_' . $customFieldName . '_custom'});
        }
    }

    /**
     * @test
     */
    public function delete_custom_eligibility_fields_from_service(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $service = Service::factory()->withOfferings()->withUsefulInfo()->withSocialMedia()->withCustomEligibilities()->withEligibilityTaxonomies()->withCategoryTaxonomies()
            ->create();

        Passport::actingAs($user);

        $payload = [
            'eligibility_types' => [
                'custom' => [
                    'age_group' => null,
                    'disability' => null,
                    'ethnicity' => null,
                    'gender' => null,
                    'income' => null,
                    'language' => null,
                    'housing' => null,
                    'other' => null,
                ],
            ],
        ];

        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);
    }

    /**
     * @test
     */
    public function delete_eligibility_taxonomy_ids_from_service(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $service = Service::factory()->withOfferings()->withUsefulInfo()->withSocialMedia()->withCustomEligibilities()->withEligibilityTaxonomies()->withCategoryTaxonomies()
            ->create();

        Passport::actingAs($user);

        $payload = [
            'eligibility_types' => [
                'taxonomies' => [],
            ],
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);
    }

    /**
     * @test
     */
    public function delete_eligibility_taxonomy_ids_and_custom_fields_from_service(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $service = Service::factory()->withOfferings()->withUsefulInfo()->withSocialMedia()->withCustomEligibilities()->withEligibilityTaxonomies()->withCategoryTaxonomies()
            ->create();

        Passport::actingAs($user);

        $payload = [
            'eligibility_types' => [
                'taxonomies' => [],
                'custom' => [
                    'age_group' => null,
                    'disability' => null,
                    'employment' => null,
                    'ethnicity' => null,
                    'gender' => null,
                    'housing' => null,
                    'income' => null,
                    'language' => null,
                    'other' => null,
                ],
            ],
        ];

        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);
    }

    /**
     * @test
     */
    public function eligibility_taxonomy_can_not_be_added_if_top_level_child_of_incorrect_parent_taxonomy(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $service = Service::factory()->withOfferings()->withUsefulInfo()->withSocialMedia()->withCustomEligibilities()->withEligibilityTaxonomies()->withCategoryTaxonomies()
            ->create();

        // When I try to associate a taxonomy that is NOT a child of Service Eligibility
        $incorrectTaxonomyId = Taxonomy::category()->children->random()->id;

        $payload = [
            'eligibility_types' => [
                'taxonomies' => [$incorrectTaxonomyId],
            ],
        ];

        Passport::actingAs($user);

        $response = $this->json('PUT', route('core.v1.services.update', $service->id), $payload);

        // A validation error is thrown
        $response->assertStatus(422);
    }

    /**
     * @test
     */
    public function UpdateSocialMediasAsServiceAdmin200(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);

        $taxonomy = Taxonomy::category()->children()->firstOrFail();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $serviceAdmin = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($serviceAdmin);
        $payload = [
            'social_medias' => [
                [
                    'type' => SocialMedia::TYPE_FACEBOOK,
                    'url' => 'https://www.facebook.com/randomPerson',
                ],
            ],
        ];

        // Create the update request as service admin
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['social_medias' => [
            [
                'type' => SocialMedia::TYPE_FACEBOOK,
                'url' => 'https://www.facebook.com/randomPerson',
            ],
        ],
        ]);
    }

    /*
     * Delete a specific service's logo.
     */

    /**
     * @test
     */
    public function service_admin_can_delete_logo(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $service = Service::factory()->withPngLogo()->create();
        $user->makeServiceAdmin($service);
        $payload = [
            'slug' => $service->slug,
            'name' => $service->name,
            'status' => $service->status,
            'intro' => $service->intro,
            'description' => $service->description,
            'wait_time' => $service->wait_time,
            'is_free' => $service->is_free,
            'fees_text' => $service->fees_text,
            'fees_url' => $service->fees_url,
            'testimonial' => $service->testimonial,
            'video_embed' => $service->video_embed,
            'url' => $service->url,
            'contact_name' => $service->contact_name,
            'contact_phone' => $service->contact_phone,
            'contact_email' => $service->contact_email,
            'show_referral_disclaimer' => $service->show_referral_disclaimer,
            'referral_method' => $service->referral_method,
            'referral_button_text' => $service->referral_button_text,
            'referral_email' => $service->referral_email,
            'referral_url' => $service->referral_url,
            'cqc_location_id' => $service->cqc_location_id,
            'useful_infos' => [],

            'category_taxonomies' => [],
            'logo_file_id' => null,
        ];

        Passport::actingAs($user);

        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas(table(UpdateRequest::class), ['updateable_id' => $service->id]);
        $updateRequest = UpdateRequest::where('updateable_id', $service->id)->firstOrFail();
        $this->assertEquals(null, $updateRequest->data['logo_file_id']);
    }

    /**
     * @test
     */
    public function service_admin_can_update_gallery_items(): void
    {
        // Set the max number of gallery images to 2
        config(['local.max_gallery_images' => 2]);
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
            'referral_method' => Service::REFERRAL_METHOD_INTERNAL,
            'referral_email' => $this->faker->safeEmail(),
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));

        $user = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($user);

        // SVG
        $imageSvg = File::factory()->pendingAssignment()->imageSvg()->create();
        // PNG
        $imagePng = File::factory()->pendingAssignment()->imagePng()->create();
        // JPG
        $imageJpg = File::factory()->pendingAssignment()->imageJpg()->create();

        $payload = [
            'gallery_items' => [
                [
                    'file_id' => $imageSvg->id,
                ],
                [
                    'file_id' => $imagePng->id,
                ],
                [
                    'file_id' => $imageJpg->id,
                ],
            ],
        ];

        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Set the max number of gallery images to 5
        config(['local.max_gallery_images' => 5]);

        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment(['data' => $payload]);

        $updateRequest = UpdateRequest::find($response->json('id'));

        $this->assertEquals($updateRequest->data, $payload);

        $this->approveUpdateRequest($updateRequest->id);

        $response = $this->json('GET', '/core/v1/services/test-service');

        $response->assertJsonFragment([
            'file_id' => $imageSvg->id,
            'url' => url("core/v1/services/$service->id/gallery-items/$imageSvg->id"),
            'mime_type' => $imageSvg->mime_type,
            'alt_text' => $imageSvg->meta['alt_text'],
        ]);

        // Get the gallery item image
        $content = $this->get("/core/v1/services/$service->slug/gallery-items/$imageSvg->id")->content();

        $this->assertEquals(Storage::disk('local')->get('/test-data/image.svg'), $content);

        $response->assertJsonFragment([
            'file_id' => $imagePng->id,
            'url' => url("core/v1/services/$service->id/gallery-items/$imagePng->id"),
            'mime_type' => $imagePng->mime_type,
            'alt_text' => $imagePng->meta['alt_text'],
        ]);

        // Get the gallery item image
        $content = $this->get("/core/v1/services/$service->slug/gallery-items/$imagePng->id")->content();

        $this->assertEquals(Storage::disk('local')->get('/test-data/image.png'), $content);

        $response->assertJsonFragment([
            'file_id' => $imageJpg->id,
            'url' => url("core/v1/services/$service->id/gallery-items/$imageJpg->id"),
            'mime_type' => $imageJpg->mime_type,
            'alt_text' => $imageJpg->meta['alt_text'],
        ]);

        // Get the gallery item image
        $content = $this->get("/core/v1/services/$service->slug/gallery-items/$imageJpg->id")->content();

        $this->assertEquals(Storage::disk('local')->get('/test-data/image.jpg'), $content);
    }

    /**
     * @test
     */
    public function only_partial_fields_can_be_updated(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $payload = [
            'slug' => 'random-slug',
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['data' => $payload]);
    }

    /**
     * @test
     */
    public function fields_removed_for_existing_update_requests(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $responseOne = $this->json('PUT', "/core/v1/services/{$service->id}", [
            'useful_infos' => [
                [
                    'title' => 'Title 1',
                    'description' => 'Description 1',
                    'order' => 1,
                ],
            ],
        ]);
        $responseOne->assertStatus(Response::HTTP_OK);

        $responseTwo = $this->json('PUT', "/core/v1/services/{$service->id}", [
            'useful_infos' => [
                [
                    'title' => 'Title 1',
                    'description' => 'Description 1',
                    'order' => 1,
                ],
                [
                    'title' => 'Title 2',
                    'description' => 'Description 2',
                    'order' => 2,
                ],
            ],
        ]);
        $responseTwo->assertStatus(Response::HTTP_OK);

        $updateRequestOne = UpdateRequest::withTrashed()->findOrFail($this->getResponseContent($responseOne)['id']);
        $updateRequestTwo = UpdateRequest::findOrFail($this->getResponseContent($responseTwo)['id']);

        $this->assertArrayNotHasKey('useful_infos', $updateRequestOne->data);
        $this->assertArrayHasKey('useful_infos', $updateRequestTwo->data);
        $this->assertArrayHasKey('useful_infos.0.title', Arr::dot($updateRequestTwo->data));
        $this->assertArrayHasKey('useful_infos.0.description', Arr::dot($updateRequestTwo->data));
        $this->assertArrayHasKey('useful_infos.0.order', Arr::dot($updateRequestTwo->data));
        $this->assertArrayHasKey('useful_infos.1.title', Arr::dot($updateRequestTwo->data));
        $this->assertArrayHasKey('useful_infos.1.description', Arr::dot($updateRequestTwo->data));
        $this->assertArrayHasKey('useful_infos.1.order', Arr::dot($updateRequestTwo->data));
        $this->assertSoftDeleted($updateRequestOne->getTable(), ['id' => $updateRequestOne->id]);
    }

    /**
     * @test
     */
    public function referral_url_required_when_referral_method_not_updated_with_it(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
            'referral_method' => Service::REFERRAL_METHOD_EXTERNAL,
            'referral_url' => $this->faker->url(),
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $response = $this->json('PUT', "/core/v1/services/{$service->id}", [
            'referral_url' => null,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_update_organisation_id(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);

        Passport::actingAs($user);

        $response = $this->json('PUT', "/core/v1/services/{$service->id}", [
            'organisation_id' => Organisation::factory()->create()->id,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function global_admin_can_update_organisation_id(): void
    {
        $originalOrganisation = Organisation::factory()->create([
            'name' => 'Original Organisation',
        ]);
        $service = Service::factory()->create([
            'organisation_id' => $originalOrganisation->id,
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $newOrganisation = Organisation::factory()->create([
            'name' => 'New Organisation',
        ]);
        $globalAdmin = User::factory()->create()->makeGlobalAdmin();
        $originalOrganisationAdmin = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        $newOrganisationAdmin = User::factory()->create()->makeOrganisationAdmin($newOrganisation);

        $this->assertFalse($newOrganisationAdmin->isServiceAdmin($service));
        $this->assertTrue($originalOrganisationAdmin->isServiceAdmin($service));

        Passport::actingAs($globalAdmin);

        $payload = [
            'organisation_id' => $newOrganisation->id,
        ];

        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);

        $updateRequestId = $response->json()['id'];

        Passport::actingAs(User::factory()->create()->makeSuperAdmin());

        $updateRequestCheckResponse = $this->get(
            route(
                'core.v1.update-requests.show',
                ['update_request' => $updateRequestId]
            )
        );

        $updateRequestCheckResponse->assertSuccessful();
        $updateRequestResponseData = json_decode($updateRequestCheckResponse->getContent());

        // Update request should not have been approved.
        $this->assertNull($updateRequestResponseData->approved_at);

        // Approve the update request
        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequestId}/approve");

        $response->assertStatus(Response::HTTP_OK);

        $service->refresh();

        $this->assertTrue($newOrganisationAdmin->isServiceAdmin($service));
        $this->assertFalse($originalOrganisationAdmin->isServiceAdmin($service));
    }

    /**
     * @test
     */
    public function global_admin_can_update_organisation_id_with_preview_only(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => Organisation::factory()->create()->id,
        ];

        $response = $this->json('PUT', "/core/v1/services/{$service->id}", array_merge(
            $payload,
            ['preview' => true]
        ));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => null]);
        $response->assertJsonFragment($payload);
    }

    /**
     * @test
     */
    public function global_admin_cannot_update_one_with_auto_approval(): void
    {
        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => Organisation::factory()->create()->id,
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);

        $data = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_SERVICE)
            ->where('updateable_id', $service->id)
            ->firstOrFail()->data;
        $this->assertEquals($data, $payload);

        // Simulate frontend check by making call with UpdateRequest ID.
        $updateRequestId = json_decode($response->getContent())->id;
        Passport::actingAs(User::factory()->create()->makeSuperAdmin());

        $updateRequestCheckResponse = $this->get(
            route(
                'core.v1.update-requests.show',
                ['update_request' => $updateRequestId]
            )
        );

        $updateRequestCheckResponse->assertSuccessful();
        $updateRequestResponseData = json_decode($updateRequestCheckResponse->getContent());

        // Update request should already have been approved.
        $this->assertNull($updateRequestResponseData->approved_at);
    }

    /**
     * @test
     */
    public function UpdateSocialMediasFieldAsSuperAdmin200(): void
    {
        $now = Date::now();
        Date::setTestNow($now);

        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $service = Service::factory()->create();
        $service->serviceTaxonomies()->create([
            'taxonomy_id' => Taxonomy::category()->children()->firstOrFail()->id,
        ]);
        $updateRequest = $service->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => [
                'slug' => $service->slug,
                'name' => 'Test Name',
                'type' => $service->type,
                'status' => $service->status,
                'intro' => $service->intro,
                'description' => $service->description,
                'wait_time' => $service->wait_time,
                'is_free' => $service->is_free,
                'fees_text' => $service->fees_text,
                'fees_url' => $service->fees_url,
                'testimonial' => $service->testimonial,
                'video_embed' => $service->video_embed,
                'url' => $service->url,
                'contact_name' => $service->contact_name,
                'contact_phone' => $service->contact_phone,
                'contact_email' => $service->contact_email,
                'show_referral_disclaimer' => $service->show_referral_disclaimer,
                'referral_method' => $service->referral_method,
                'referral_button_text' => $service->referral_button_text,
                'referral_email' => $service->referral_email,
                'referral_url' => $service->referral_url,
                'cqc_location_id' => $service->cqc_location_id,
                'ends_at' => null,
                'useful_infos' => [],
                'social_medias' => [
                    [
                        'type' => SocialMedia::TYPE_INSTAGRAM,
                        'url' => 'https://www.instagram.com/ayupdigital/',
                    ],
                ],
                'category_taxonomies' => $service->taxonomies()->pluck('taxonomies.id')->toArray(),
            ],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['social_medias' => [
            [
                'type' => SocialMedia::TYPE_INSTAGRAM,
                'url' => 'https://www.instagram.com/ayupdigital/',
            ],
        ]]);
    }

    /*
     * Delete a specific service.
     */

    /**
     * @test
     */
    public function guest_cannot_delete_one(): void
    {
        $service = Service::factory()->create();

        $response = $this->json('DELETE', "/core/v1/services/{$service->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_delete_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/core/v1/services/{$service->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_cannot_delete_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/core/v1/services/{$service->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_delete_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/core/v1/services/{$service->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_delete_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/core/v1/services/{$service->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function audit_created_when_deleted(): void
    {
        $this->fakeEvents();

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $this->json('DELETE', "/core/v1/services/{$service->id}");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $service) {
            return ($event->getAction() === Audit::ACTION_DELETE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $service->id);
        });
    }

    /**
     * @test
     */
    public function service_can_be_deleted_when_service_location_has_opening_hours(): void
    {
        $service = Service::factory()->create();
        $serviceLocation = ServiceLocation::factory()->create([
            'service_id' => $service->id,
        ]);
        RegularOpeningHour::factory()->create([
            'service_location_id' => $serviceLocation->id,
        ]);
        HolidayOpeningHour::factory()->create([
            'service_location_id' => $serviceLocation->id,
        ]);
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/core/v1/services/{$service->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing((new Service())->getTable(), ['id' => $service->id]);
    }

    /**
     * @test
     */
    public function service_can_be_deleted_when_disabled(): void
    {
        $service = Service::factory()->create([
            'status' => Service::STATUS_INACTIVE,
        ]);

        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/core/v1/services/{$service->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing((new Service())->getTable(), ['id' => $service->id]);
    }

    /*
     * Refresh service.
     */

    /**
     * @test
     */
    public function guest_without_token_cannot_refresh(): void
    {
        $service = Service::factory()->create();

        $response = $this->putJson("/core/v1/services/{$service->id}/refresh");

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function guest_with_invalid_token_cannot_refresh(): void
    {
        $service = Service::factory()->create();

        $response = $this->putJson("/core/v1/services/{$service->id}/refresh", [
            'token' => 'invalid-token',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function guest_with_valid_token_can_refresh(): void
    {
        $now = Date::now();
        Date::setTestNow($now);

        $service = Service::factory()->create([
            'last_modified_at' => Date::now()->subMonths(6),
        ]);

        $response = $this->putJson("/core/v1/services/{$service->id}/refresh", [
            'token' => ServiceRefreshToken::factory()->create([
                'service_id' => $service->id,
            ])->id,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'last_modified_at' => $now->format(CarbonImmutable::ISO8601),
        ]);
    }

    /**
     * @test
     */
    public function service_worker_without_token_cannot_refresh(): void
    {
        $service = Service::factory()->create();

        $user = User::factory()->create()->makeServiceWorker($service);

        Passport::actingAs($user);

        $response = $this->putJson("/core/v1/services/{$service->id}/refresh");

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function service_admin_without_token_can_refresh(): void
    {
        $now = Date::now();
        Date::setTestNow($now);

        $service = Service::factory()->create([
            'last_modified_at' => Date::now()->subMonths(6),
        ]);

        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $response = $this->putJson("/core/v1/services/{$service->id}/refresh");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'last_modified_at' => $now->format(CarbonImmutable::ISO8601),
        ]);
    }

    /*
     * List all the related services.
     */

    /**
     * @test
     */
    public function guest_can_list_related(): void
    {
        $taxonomyOne = Taxonomy::factory()->create();
        $taxonomyTwo = Taxonomy::factory()->create();
        $taxonomyThree = Taxonomy::factory()->create();

        $service = Service::factory()->create();
        $service->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyOne->id]);
        $service->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyTwo->id]);
        $service->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyThree->id]);

        $relatedService = Service::factory()->create();
        $relatedService->usefulInfos()->create([
            'title' => 'Did You Know?',
            'description' => 'This is a test description',
            'order' => 1,
        ]);
        $relatedService->socialMedias()->create([
            'type' => SocialMedia::TYPE_INSTAGRAM,
            'url' => 'https://www.instagram.com/ayupdigital/',
        ]);
        $relatedService->serviceGalleryItems()->create([
            'file_id' => File::factory()->create()->id,
        ]);
        $relatedService->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyOne->id]);
        $relatedService->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyTwo->id]);
        $relatedService->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyThree->id]);

        $unrelatedService = Service::factory()->create();
        $unrelatedService->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyOne->id]);
        $unrelatedService->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyTwo->id]);

        $inactiveService = Service::factory()->create([
            'status' => Service::STATUS_INACTIVE,
        ]);
        $inactiveService->usefulInfos()->create([
            'title' => 'Did You Know?',
            'description' => 'This is a test description',
            'order' => 1,
        ]);
        $inactiveService->socialMedias()->create([
            'type' => SocialMedia::TYPE_INSTAGRAM,
            'url' => 'https://www.instagram.com/ayupdigital/',
        ]);
        $inactiveService->serviceGalleryItems()->create([
            'file_id' => File::factory()->create()->id,
        ]);
        $inactiveService->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyOne->id]);
        $inactiveService->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyTwo->id]);
        $inactiveService->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyThree->id]);

        $response = $this->json('GET', "/core/v1/services/{$service->id}/related");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'organisation_id',
                    'has_logo',
                    'name',
                    'slug',
                    'type',
                    'status',
                    'intro',
                    'description',
                    'wait_time',
                    'is_free',
                    'fees_text',
                    'fees_url',
                    'testimonial',
                    'video_embed',
                    'url',
                    'contact_name',
                    'contact_phone',
                    'contact_email',
                    'show_referral_disclaimer',
                    'referral_method',
                    'referral_button_text',
                    'referral_email',
                    'referral_url',
                    'ends_at',
                    'useful_infos' => [
                        [
                            'title',
                            'description',
                            'order',
                        ],
                    ],
                    'gallery_items' => [
                        [
                            'file_id',
                            'url',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'category_taxonomies' => [
                        [
                            'id',
                            'parent_id',
                            'name',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'last_modified_at',
                    'created_at',
                    'updated_at',
                ],
            ],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'path',
                'per_page',
                'to',
                'total',
            ],
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
        ]);

        $response->assertJsonFragment(['id' => $relatedService->id]);
        $response->assertJsonMissing(['id' => $unrelatedService->id]);
        $response->assertJsonMissing(['id' => $inactiveService->id]);
    }

    /**
     * @test
     */
    public function related_services_order_by_taxonomy_depth(): void
    {
        // Create taxonomies.
        $taxonomy = Taxonomy::factory()->create();
        $taxonomyOneDepthOne = $taxonomy->children()->create([
            'slug' => 'taxonomy-one',
            'name' => 'Taxonomy One',
            'order' => 1,
            'depth' => 1,
        ]);
        $taxonomyTwoDepthOne = $taxonomy->children()->create([
            'slug' => 'taxonomy-two',
            'name' => 'Taxonomy Two',
            'order' => 1,
            'depth' => 1,
        ]);
        $taxonomyThreeDepthOne = $taxonomy->children()->create([
            'slug' => 'taxonomy-three',
            'name' => 'Taxonomy Three',
            'order' => 1,
            'depth' => 1,
        ]);
        $taxonomyFourDepthTwo = $taxonomyOneDepthOne->children()->create([
            'slug' => 'taxonomy-four',
            'name' => 'Taxonomy Four',
            'order' => 1,
            'depth' => 2,
        ]);

        // Create service.
        $service = Service::factory()->create();
        $service->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyOneDepthOne->id]);
        $service->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyTwoDepthOne->id]);
        $service->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyThreeDepthOne->id]);
        $service->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyFourDepthTwo->id]);

        // Create closely related service.
        $closelyRelatedService = Service::factory()->create([
            'name' => 'Beta',
        ]);
        $closelyRelatedService->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyOneDepthOne->id]);
        $closelyRelatedService->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyTwoDepthOne->id]);
        $closelyRelatedService->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyFourDepthTwo->id]);

        // Create distantly related service.
        $distantlyRelatedService = Service::factory()->create([
            'name' => 'Alpha',
        ]);
        $distantlyRelatedService->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyOneDepthOne->id]);
        $distantlyRelatedService->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyTwoDepthOne->id]);
        $distantlyRelatedService->serviceTaxonomies()->create(['taxonomy_id' => $taxonomyThreeDepthOne->id]);

        $response = $this->json('GET', "/core/v1/services/{$service->id}/related");
        $response->assertStatus(Response::HTTP_OK);

        $services = $this->getResponseContent($response, 'data');

        $this->assertCount(2, $services);
        $this->assertSame($closelyRelatedService->id, $services[0]['id']);
        $this->assertSame($distantlyRelatedService->id, $services[1]['id']);
    }

    /*
     * Disable stale.
     */

    /**
     * @test
     */
    public function guest_cannot_disable_stale(): void
    {
        $response = $this->putJson('/core/v1/services/disable-stale', [
            'last_modified_at' => Date::today()->toDateString(),
        ]);

        $response->assertUnauthorized();
    }

    /**
     * @test
     */
    public function super_admin_can_disable_stale(): void
    {
        $staleService = Service::factory()->create([
            'last_modified_at' => '2020-02-01',
        ]);
        $currentService = Service::factory()->create([
            'last_modified_at' => '2020-05-01',
        ]);

        Passport::actingAs(User::factory()->create()->makeSuperAdmin());

        $response = $this->putJson('/core/v1/services/disable-stale', [
            'last_modified_at' => '2020-03-01',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas($staleService->getTable(), [
            'id' => $staleService->id,
            'status' => Service::STATUS_INACTIVE,
        ]);
        $this->assertDatabaseHas($currentService->getTable(), [
            'id' => $currentService->id,
            'status' => Service::STATUS_ACTIVE,
        ]);
    }

    /*
     * Get a specific service's logo.
     */

    /**
     * @test
     */
    public function guest_can_view_logo(): void
    {
        $service = Service::factory()->create();

        $response = $this->get("/core/v1/services/{$service->id}/logo.png");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertHeader('Content-Type', 'image/png');
    }

    /**
     * @test
     */
    public function audit_created_when_logo_viewed(): void
    {
        $this->fakeEvents();

        $service = Service::factory()->create();

        $this->get("/core/v1/services/{$service->id}/logo.png");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($service) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getModel()->id === $service->id);
        });
    }

    /*
     * Upload a specific service's logo.
     */

    /**
     * @test
     */
    public function organisation_admin_can_upload_logo(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create();
        $user->makeOrganisationAdmin($organisation);
        $image = File::factory()->pendingAssignment()->imagePng()->create();

        Passport::actingAs($user);

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
            'intro' => 'This is a test intro',
            'description' => 'Lorem ipsum',
            'wait_time' => null,
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'testimonial' => null,
            'video_embed' => null,
            'url' => $this->faker->url(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => random_uk_phone(),
            'contact_email' => $this->faker->safeEmail(),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'cqc_location_id' => null,
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'tags' => [],
            'social_medias' => [],
            'gallery_items' => [],
            'category_taxonomies' => [],
            'logo_file_id' => $image->id,
        ];

        $response = $this->json('POST', '/core/v1/services', $payload);

        $updateRequestId = $this->getResponseContent($response, 'id');

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas(table(UpdateRequest::class), [
            'id' => $updateRequestId,
            'updateable_id' => null,
        ]);
        $updateRequest = UpdateRequest::where('id', $updateRequestId)->firstOrFail();
        $this->assertEquals($image->id, $updateRequest->data['logo_file_id']);

        Passport::actingAs(User::factory()->create()->makeSuperAdmin());

        //And the organisation event should not yet be created
        $this->assertEmpty(Service::all());

        // Get the event image for the update request
        $response = $this->get("/core/v1/services/new/logo.png?update_request_id=$updateRequestId");

        $this->assertEquals($image->getContent(), $response->content());

        $serviceApproveResponse = $this->put(
            route(
                'core.v1.update-requests.approve',
                ['update_request' => $updateRequestId]
            )
        );

        $serviceApproveResponse->assertSuccessful();

        unset($payload['useful_infos']);
        unset($payload['offerings']);
        unset($payload['social_medias']);
        unset($payload['gallery_items']);
        unset($payload['category_taxonomies']);
        unset($payload['tags']);

        $this->assertDatabaseHas('services', $payload);

        $response = $this->json('GET', "/core/v1/services/test-service");

        $response->assertJsonFragment([
            'image' => [
                'id' => $image->id,
                'mime_type' => $image->mime_type,
                'alt_text' => $image->meta['alt_text'],
                'url' => $image->url(),
            ],
        ]);
    }

    /*
     * Get a specific service's gallery item.
     */

    /**
     * @test
     */
    public function guest_can_view_gallery_item(): void
    {
        /** @var \App\Models\File $file */
        $file = File::factory()->pendingAssignment()->imagePng()->create();

        /** @var \App\Models\Service $service */
        $service = Service::factory()->create();

        /** @var \App\Models\ServiceGalleryItem $serviceGalleryItem */
        $serviceGalleryItem = $service->serviceGalleryItems()->create([
            'file_id' => $file->id,
        ]);

        $response = $this->get($serviceGalleryItem->url());

        $response->assertStatus(Response::HTTP_OK);
        $response->assertHeader('Content-Type', 'image/png');
    }

    /**
     * Bulk import services
     */
    /**
     * @test
     */
    public function guest_cannot_bulk_import(): void
    {
        Storage::fake('local');

        $services = Service::factory()->count(2)->make([
            'status' => Service::STATUS_INACTIVE,
        ]);

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_bulk_import(): void
    {
        Storage::fake('local');

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);

        Passport::actingAs($user);

        $services = Service::factory()->count(2)->make();

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_cannot_bulk_import(): void
    {
        Storage::fake('local');

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $services = Service::factory()->count(2)->make([
            'status' => Service::STATUS_INACTIVE,
        ]);

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_from_other_organisation_cannot_bulk_import(): void
    {
        Storage::fake('local');

        $organisation1 = Organisation::factory()->create();
        $organisation2 = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation1);

        Passport::actingAs($user);

        $services = Service::factory()->count(2)->make([
            'status' => Service::STATUS_INACTIVE,
            'organisation_id' => $organisation2->id,
        ]);

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_bulk_import(): void
    {
        Storage::fake('local');

        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $services = Service::factory()->count(2)->make([
            'status' => Service::STATUS_INACTIVE,
            'organisation_id' => $organisation->id,
        ]);

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_bulk_import(): void
    {
        Storage::fake('local');

        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $services = Service::factory()->count(2)->make();

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function super_admin_can_bulk_import(): void
    {
        Storage::fake('local');

        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $services = Service::factory()->count(2)->make();

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_CREATED);
    }

    /**
     * @test
     */
    public function global_admin_can_view_bulk_imported_services(): void
    {
        Storage::fake('local');

        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs(User::factory()->create()->makeSuperAdmin());

        $services = Service::factory()->count(2)->make();

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        Passport::actingAs($user);

        $response = $this->json('GET', '/core/v1/services?include=organisation&filter=[has_permission]=true&page=1&sort=name');

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment([
            'name' => $services->get(0)->name,
        ]);

        $response->assertJsonFragment([
            'name' => $services->get(1)->name,
        ]);

        $service1 = Service::where('name', $services->get(0)->name)->first();
        $service2 = Service::where('name', $services->get(1)->name)->first();

        $this->assertDatabaseHas((new UserRole())->getTable(), [
            'user_id' => $user->id,
            'role_id' => Role::serviceWorker()->id,
            'service_id' => $service1->id,
            'organisation_id' => $service1->organisation_id,
        ]);

        $this->assertDatabaseHas((new UserRole())->getTable(), [
            'user_id' => $user->id,
            'role_id' => Role::serviceWorker()->id,
            'service_id' => $service2->id,
            'organisation_id' => $service2->organisation_id,
        ]);

        $this->assertDatabaseHas((new UserRole())->getTable(), [
            'user_id' => $user->id,
            'role_id' => Role::serviceAdmin()->id,
            'service_id' => $service1->id,
            'organisation_id' => $service1->organisation_id,
        ]);

        $this->assertDatabaseHas((new UserRole())->getTable(), [
            'user_id' => $user->id,
            'role_id' => Role::serviceAdmin()->id,
            'service_id' => $service2->id,
            'organisation_id' => $service2->organisation_id,
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_view_bulk_imported_services(): void
    {
        Storage::fake('local');

        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $services = Service::factory()->count(2)->make();

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $response = $this->json('GET', '/core/v1/services?include=organisation&filter=[has_permission]=true&page=1&sort=name');

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment([
            'name' => $services->get(0)->name,
        ]);

        $response->assertJsonFragment([
            'name' => $services->get(1)->name,
        ]);

        $service1 = Service::where('name', $services->get(0)->name)->first();
        $service2 = Service::where('name', $services->get(1)->name)->first();

        $this->assertDatabaseHas((new UserRole())->getTable(), [
            'user_id' => $user->id,
            'role_id' => Role::serviceWorker()->id,
            'service_id' => $service1->id,
            'organisation_id' => $service1->organisation_id,
        ]);

        $this->assertDatabaseHas((new UserRole())->getTable(), [
            'user_id' => $user->id,
            'role_id' => Role::serviceWorker()->id,
            'service_id' => $service2->id,
            'organisation_id' => $service2->organisation_id,
        ]);

        $this->assertDatabaseHas((new UserRole())->getTable(), [
            'user_id' => $user->id,
            'role_id' => Role::serviceAdmin()->id,
            'service_id' => $service1->id,
            'organisation_id' => $service1->organisation_id,
        ]);

        $this->assertDatabaseHas((new UserRole())->getTable(), [
            'user_id' => $user->id,
            'role_id' => Role::serviceAdmin()->id,
            'service_id' => $service2->id,
            'organisation_id' => $service2->organisation_id,
        ]);
    }

    /**
     * @test
     */
    public function validate_file_import_type(): void
    {
        Storage::fake('local');

        $organisation = Organisation::factory()->create();

        $invalidFieldTypes = [
            ['spreadsheet' => 'This is a string'],
            ['spreadsheet' => 1],
            ['spreadsheet' => ['foo' => 'bar']],
            ['spreadsheet' => UploadedFile::fake()->create('dummy.doc', 3000)],
            ['spreadsheet' => UploadedFile::fake()->create('dummy.txt', 3000)],
            ['spreadsheet' => UploadedFile::fake()->create('dummy.csv', 3000)],
        ];

        $user = User::factory()->create()->makeSuperAdmin();
        $organisation = Organisation::factory()->create();

        Passport::actingAs($user);

        foreach ($invalidFieldTypes as $data) {
            $data['organisation_id'] = $organisation->id;
            $response = $this->json('POST', '/core/v1/services/import', $data);
            $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $services = Service::factory()->count(2)->make([
            'organisation_id' => $organisation->id,
        ]);

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson([
            'data' => [
                'imported_row_count' => 2,
            ],
        ]);

        $services = Service::factory()->count(2)->make([
            'organisation_id' => $organisation->id,
        ]);

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/octet-stream;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson([
            'data' => [
                'imported_row_count' => 2,
            ],
        ]);

        $services = Service::factory()->count(2)->make([
            'organisation_id' => $organisation->id,
        ]);

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xlsx'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson([
            'data' => [
                'imported_row_count' => 2,
            ],
        ]);
    }

    /**
     * @test
     */
    public function validate_file_import_service_fields(): void
    {
        Storage::fake('local');
        $faker = Faker::create('en_GB');

        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin($organisation);

        Passport::actingAs($user);

        // Empty fields
        $services = Service::factory()->count(2)->make([
            'organisation_id' => uuid(),
            'name' => '',
            'type' => '',
            'status' => '',
            'intro' => '',
            'description' => '',
            'url' => '',
            'referral_method' => '',
        ]);

        foreach ($services as &$service) {
            $service->id = $faker->word();
        }

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson([
            'data' => [
                'errors' => [
                    'spreadsheet' => [
                        [
                            'row' => [],
                            'errors' => [
                                'id' => [],
                                'organisation_id' => [],
                                'name' => [],
                                'type' => [],
                                'status' => [],
                                'intro' => [],
                                'description' => [],
                                'url' => [],
                                'referral_method' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Invalid data
        $services = Service::factory()->count(2)->make([
            'organisation_id' => uuid(),
            'name' => '',
            'type' => '',
            'status' => 'foo',
            'intro' => '',
            'description' => '',
            'url' => 'not a url',
            'referral_method' => '',
            'referral_email' => $faker->word(),
            'referral_url' => $faker->word(),
        ]);

        foreach ($services as &$service) {
            $service->id = $faker->word();
        }

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xlsx'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson([
            'data' => [
                'errors' => [
                    'spreadsheet' => [
                        [
                            'row' => [],
                            'errors' => [
                                'id' => [],
                                'organisation_id' => [],
                                'name' => [],
                                'type' => [],
                                'status' => [],
                                'intro' => [],
                                'description' => [],
                                'url' => [],
                                'referral_method' => [],
                                'referral_email' => [],
                                'referral_url' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Existing ID
        $service = Service::factory()->make([
            'id' => Service::factory()->create()->id,
            'status' => Service::STATUS_INACTIVE,
            'organisation_id' => $organisation->id,
        ]);

        $this->createServiceSpreadsheets(collect([$service]));

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson([
            'data' => [
                'errors' => [
                    'spreadsheet' => [
                        [
                            'row' => [],
                            'errors' => [
                                'id' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $data = [
            'spreadsheet' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xlsx'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson([
            'data' => [
                'errors' => [
                    'spreadsheet' => [
                        [
                            'row' => [],
                            'errors' => [
                                'id' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function validate_file_import_duplicate_service_ids(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->makeSuperAdmin();
        Passport::actingAs($user);

        $service = Service::factory()->create();

        $uuid = uuid();

        $services = collect([
            $service,
            Service::factory()->make([
                'id' => $uuid,
            ]),
            Service::factory()->make([
                'id' => $uuid,
            ]),
        ]);

        $this->createServiceSpreadsheets($services);

        $response = $this->json('POST', '/core/v1/services/import', [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $headers = [
            'id',
            'name',
            'description',
            'url',
            'email',
            'phone',
        ];

        $response->assertJson([
            'data' => [
                'errors' => [
                    'spreadsheet' => [
                        [
                            'row' => [],
                            'errors' => [
                                'id' => [
                                    'The id has already been taken.',
                                ],
                            ],
                        ],
                        [
                            'row' => [],
                            'errors' => [
                                'id' => [
                                    'The ID is used elsewhere in the spreadsheet.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function validate_file_import_invalid_organisation_ids(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->makeSuperAdmin();
        Passport::actingAs($user);

        $uuid = uuid();

        $services = collect([
            Service::factory()->make([
                'organisation_id' => 'foo',
            ]),
            Service::factory()->make([
                'organisation_id' => $uuid,
            ]),
        ]);

        $this->createServiceSpreadsheets($services);

        $response = $this->json('POST', '/core/v1/services/import', [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response->assertJson([
            'data' => [
                'errors' => [
                    'spreadsheet' => [
                        [
                            'row' => [],
                            'errors' => [
                                'organisation_id' => [
                                    __('validation.uuid', ['attribute' => 'organisation id']),
                                    'The organisation id field must contain an ID for an organisation you are an organisation admin for.',
                                ],
                            ],
                        ],
                        [
                            'row' => [],
                            'errors' => [
                                'organisation_id' => [
                                    'The selected organisation id is invalid.',
                                    'The organisation id field must contain an ID for an organisation you are an organisation admin for.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function validate_file_import_service_field_super_admin_permissions(): void
    {
        Storage::fake('local');
        $faker = Faker::create('en_GB');

        $globalAdminUser = User::factory()->create()->makeGlobalAdmin();
        $superAdminUser = User::factory()->create()->makeSuperAdmin();

        $services = Service::factory()->count(2)->make([
            'status' => Service::STATUS_ACTIVE,
            'referral_method' => Service::REFERRAL_METHOD_EXTERNAL,
            'referral_button_text' => $faker->word(),
            'referral_email' => $faker->email(),
            'referral_url' => $faker->url(),
            'show_referral_disclaimer' => '0',
        ]);

        $this->createServiceSpreadsheets($services);

        Passport::actingAs($superAdminUser);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $services = Service::factory()->count(2)->make([
            'status' => Service::STATUS_ACTIVE,
            'referral_method' => Service::REFERRAL_METHOD_EXTERNAL,
            'referral_button_text' => $faker->word(),
            'referral_email' => $faker->email(),
            'referral_url' => $faker->url(),
            'show_referral_disclaimer' => '0',
        ]);

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xlsx'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_CREATED);
    }

    /**
     * @test
     */
    public function service_file_import_creates_service_eligibility_relations(): void
    {
        Storage::fake('local');

        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();
        $serviceId = uuid();

        Passport::actingAs($user);

        $service = Service::factory()->withCustomEligibilities()->make([
            'id' => $serviceId,
            'status' => Service::STATUS_INACTIVE,
            'organisation_id' => $organisation->id,
        ]);

        $serviceEligibilityTaxonomyIds = Taxonomy::serviceEligibility()->children->map(function ($serviceEligibility) {
            return $serviceEligibility->children->first()->id;
        });

        $this->createServiceSpreadsheets(collect([$service]), [$serviceId => $serviceEligibilityTaxonomyIds->all()]);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJson([
            'data' => [
                'imported_row_count' => 1,
            ],
        ]);

        foreach ($serviceEligibilityTaxonomyIds as $serviceEligibilityId) {
            $this->assertDatabaseHas('service_eligibilities', [
                'service_id' => $serviceId,
                'taxonomy_id' => $serviceEligibilityId,
            ]);
        }
    }

    /**
     * @test
     */
    public function service_file_import_rejects_invalid_service_eligibility_relations(): void
    {
        Storage::fake('local');

        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();
        $serviceId = uuid();

        Passport::actingAs($user);

        $service = Service::factory()->withCustomEligibilities()->make([
            'id' => $serviceId,
            'status' => Service::STATUS_INACTIVE,
            'organisation_id' => $organisation->id,
        ]);

        $invalidServiceEligibilityTaxonomyIds = [uuid(), uuid(), uuid()];

        $this->createServiceSpreadsheets(collect([$service]), [$serviceId => $invalidServiceEligibilityTaxonomyIds]);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson([
            'data' => [
                'errors' => [
                    'spreadsheet' => [
                        [
                            'row' => [],
                            'errors' => [
                                'eligibility_taxonomies' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        foreach ($invalidServiceEligibilityTaxonomyIds as $invalidServiceEligibilityId) {
            $this->assertDatabaseMissing('service_eligibilities', [
                'service_id' => $serviceId,
                'taxonomy_id' => $invalidServiceEligibilityId,
            ]);
        }

        $categoryTaxonomyIds = [];
        foreach (Taxonomy::category()->children as $taxonomyCategory) {
            if (!$taxonomyCategory->children->isEmpty()) {
                $categoryTaxonomyIds[] = $taxonomyCategory->children->first()->id;
            }
        }

        $this->createServiceSpreadsheets(collect([$service]), [$serviceId => $categoryTaxonomyIds]);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson([
            'data' => [
                'errors' => [
                    'spreadsheet' => [
                        [
                            'row' => [],
                            'errors' => [
                                'eligibility_taxonomies' => [
                                    trans_choice(
                                        'validation.custom.service_eligibilities.not_found',
                                        count($categoryTaxonomyIds),
                                        ['ids' => implode(', ', $categoryTaxonomyIds)]
                                    ),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        foreach ($categoryTaxonomyIds as $categoryTaxonomyId) {
            $this->assertDatabaseMissing('service_eligibilities', [
                'service_id' => $serviceId,
                'taxonomy_id' => $categoryTaxonomyId,
            ]);
        }
    }

    /**
     * @test
     */
    public function services_file_import_100rows(): void
    {
        Storage::fake('local');

        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $services = Service::factory()->count(100)->make([
            'status' => Service::STATUS_INACTIVE,
            'organisation_id' => $organisation->id,
        ]);

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson([
            'data' => [
                'imported_row_count' => 100,
            ],
        ]);

        $services = Service::factory()->count(100)->make([
            'status' => Service::STATUS_INACTIVE,
            'organisation_id' => $organisation->id,
        ]);

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xlsx'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson([
            'data' => [
                'imported_row_count' => 100,
            ],
        ]);
    }

    /**
     * @group slow
     */
    /**
     * @test
     */
    public function services_file_import_5krows(): void
    {
        Storage::fake('local');

        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $services = Service::factory()->count(5000)->make([
            'status' => Service::STATUS_INACTIVE,
            'organisation_id' => $organisation->id,
        ]);

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.ms-excel;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xls'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson([
            'data' => [
                'imported_row_count' => 5000,
            ],
        ]);

        $services = Service::factory()->count(5000)->make([
            'status' => Service::STATUS_INACTIVE,
            'organisation_id' => $organisation->id,
        ]);

        $this->createServiceSpreadsheets($services);

        $data = [
            'spreadsheet' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' . base64_encode(file_get_contents(Storage::disk('local')->path('test.xlsx'))),
        ];

        $response = $this->json('POST', '/core/v1/services/import', $data);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson([
            'data' => [
                'imported_row_count' => 5000,
            ],
        ]);
    }

    /**
     * Service Eligibilities
     */
}
