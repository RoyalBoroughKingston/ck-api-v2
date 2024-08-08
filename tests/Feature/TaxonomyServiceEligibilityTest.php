<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Audit;
use App\Models\Service;
use App\Models\Taxonomy;
use App\Events\EndpointHit;
use Carbon\CarbonImmutable;
use App\Models\Organisation;
use Illuminate\Http\Response;
use Laravel\Passport\Passport;
use App\Models\ServiceEligibility;
use Illuminate\Support\Facades\Event;

class TaxonomyServiceEligibilityTest extends TestCase
{
    /**
     * Root service eligibilty type
     *
     * @var \App\Models\Taxonomy
     **/
    protected $testserviceEligibilityType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testserviceEligibilityType = Taxonomy::factory()->create([
            'name' => 'Test Service Eligibility Type',
            'parent_id' => function () {
                return Taxonomy::serviceEligibility()->id;
            },
        ]);
    }

    /*
     * List all the service eligibility taxonomies.
     */

    /**
     * @test
     */
    public function guest_can_list_them(): void
    {
        $response = $this->json('GET', '/core/v1/taxonomies/service-eligibilities');

        $taxonomyCount = Taxonomy::serviceEligibility()->children()->count();

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount($taxonomyCount, 'data');
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'parent_id',
                    'slug',
                    'name',
                    'order',
                    'children' => [],
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function audit_created_when_listed(): void
    {
        $this->fakeEvents();

        $this->json('GET', '/core/v1/taxonomies/service-eligibilities');

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return $event->getAction() === Audit::ACTION_READ;
        });
    }

    /*
     * Create a service eligibility taxonomy.
     */

    /**
     * @test
     */
    public function guest_cannot_create_one(): void
    {
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibilities');

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

        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibilities');

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

        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibilities');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_create_one(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibilities');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function gloal_admin_cannot_create_one(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibilities');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function super_admin_can_create_one(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        $siblingCount = Taxonomy::serviceEligibility()->children()->count();
        $payload = [
            'parent_id' => null,
            'name' => 'PHPUnit Service Eligibility Test',
            'order' => $siblingCount + 1,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibilities', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment([
            'parent_id' => Taxonomy::serviceEligibility()->id,
            'name' => 'PHPUnit Service Eligibility Test',
            'order' => $siblingCount + 1,
        ]);
    }

    /**
     * @test
     */
    public function order_is_updated_when_created_at_beginning(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();

        $testEligibilityTypeTaxonomies = [];
        for ($i = 1; $i < 6; $i++) {
            $testEligibilityTypeTaxonomies[] = Taxonomy::factory()->create([
                'parent_id' => $this->testserviceEligibilityType->id,
                'order' => $i,
            ]);
        }

        $payload = [
            'parent_id' => $this->testserviceEligibilityType->id,
            'name' => 'PHPUnit Service Eligibility Test',
            'order' => 1,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibilities', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($payload);
        foreach ($testEligibilityTypeTaxonomies as $eligibility) {
            $this->assertDatabaseHas(
                (new Taxonomy())->getTable(),
                ['id' => $eligibility->id, 'order' => $eligibility->order + 1]
            );
        }
    }

    /**
     * @test
     */
    public function order_is_updated_when_created_at_middle(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();

        $testEligibilityTypeTaxonomies = [];
        for ($i = 1; $i < 6; $i++) {
            $testEligibilityTypeTaxonomies[] = Taxonomy::factory()->create([
                'parent_id' => $this->testserviceEligibilityType->id,
                'order' => $i,
            ]);
        }

        $payload = [
            'parent_id' => $this->testserviceEligibilityType->id,
            'name' => 'PHPUnit Service Eligibility Test',
            'order' => 2,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibilities', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($payload);
        foreach ($testEligibilityTypeTaxonomies as $eligibility) {
            if ($eligibility->order < 2) {
                $this->assertDatabaseHas(
                    (new Taxonomy())->getTable(),
                    ['id' => $eligibility->id, 'order' => $eligibility->order]
                );
            } else {
                $this->assertDatabaseHas(
                    (new Taxonomy())->getTable(),
                    ['id' => $eligibility->id, 'order' => $eligibility->order + 1]
                );
            }
        }
    }

    /**
     * @test
     */
    public function order_is_updated_when_created_at_end(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();

        $testEligibilityTypeTaxonomies = [];
        for ($i = 1; $i < 6; $i++) {
            $testEligibilityTypeTaxonomies[] = Taxonomy::factory()->create([
                'parent_id' => $this->testserviceEligibilityType->id,
                'order' => $i,
            ]);
        }

        $payload = [
            'parent_id' => $this->testserviceEligibilityType->id,
            'name' => 'PHPUnit Service Eligibility Test',
            'order' => count($testEligibilityTypeTaxonomies) + 1,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibilities', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment($payload);
        foreach ($testEligibilityTypeTaxonomies as $eligibility) {
            $this->assertDatabaseHas(
                (new Taxonomy())->getTable(),
                ['id' => $eligibility->id, 'order' => $eligibility->order]
            );
        }
    }

    /**
     * @test
     */
    public function order_cannot_be_less_than_1_when_created(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        $payload = [
            'parent_id' => null,
            'name' => 'PHPUnit Service Eligibility Test',
            'order' => 0,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibilities', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function order_cannot_be_greater_than_count_plus_1_when_created(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        $siblingCount = Taxonomy::serviceEligibility()->children()->count();
        $payload = [
            'parent_id' => null,
            'name' => 'PHPUnit Service Eligibility Test',
            'order' => $siblingCount + 2,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibilities', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function createTaxonomyServiceEligibilityWithUniqueSlugAsSuperAdmin201(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        $siblingCount = Taxonomy::serviceEligibility()->children()->count();
        $payload = [
            'parent_id' => null,
            'name' => 'Taxonomy Slug Test',
            'order' => $siblingCount + 1,
        ];

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibilities', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('taxonomies', [
            'id' => $response->json('data.id'),
            'name' => 'Taxonomy Slug Test',
            'slug' => 'taxonomy-slug-test',
        ]);

        $payload['order']++;

        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibilities', $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('taxonomies', [
            'id' => $response->json('data.id'),
            'name' => 'Taxonomy Slug Test',
            'slug' => 'taxonomy-slug-test-1',
        ]);
    }

    /**
     * @test
     */
    public function audit_created_when_created(): void
    {
        $this->fakeEvents();

        $user = User::factory()->create()->makeSuperAdmin();
        $siblingCount = Taxonomy::serviceEligibility()->children()->count();

        Passport::actingAs($user);
        $response = $this->json('POST', '/core/v1/taxonomies/service-eligibilities', [
            'parent_id' => null,
            'name' => 'PHPUnit Service Eligibility Test',
            'order' => $siblingCount + 1,
        ]);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $response) {
            return ($event->getAction() === Audit::ACTION_CREATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $this->getResponseContent($response)['data']['id']);
        });
    }

    /*
     * Get a service eligibility taxonomy
     */

    /**
     * @test
     */
    public function guest_can_view_one(): void
    {
        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        $response = $this->json('GET', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            [
                'id' => $testEligibilityTypeTaxonomy->id,
                'parent_id' => $testEligibilityTypeTaxonomy->parent_id,
                'slug' => $testEligibilityTypeTaxonomy->slug,
                'name' => $testEligibilityTypeTaxonomy->name,
                'order' => $testEligibilityTypeTaxonomy->order,
                'children' => [],
                'created_at' => $testEligibilityTypeTaxonomy->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $testEligibilityTypeTaxonomy->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ]);
    }

    /**
     * @test
     */
    public function guest_can_view_one_by_slug(): void
    {
        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        $response = $this->json('GET', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->slug}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            [
                'id' => $testEligibilityTypeTaxonomy->id,
                'parent_id' => $testEligibilityTypeTaxonomy->parent_id,
                'slug' => $testEligibilityTypeTaxonomy->slug,
                'name' => $testEligibilityTypeTaxonomy->name,
                'order' => $testEligibilityTypeTaxonomy->order,
                'children' => [],
                'created_at' => $testEligibilityTypeTaxonomy->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $testEligibilityTypeTaxonomy->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ]);
    }

    /**
     * @test
     */
    public function audit_created_when_viewed(): void
    {
        $this->fakeEvents();

        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        $this->json('GET', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($testEligibilityTypeTaxonomy) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getModel()->id === $testEligibilityTypeTaxonomy->id);
        });
    }

    /*
     * Update a specific service eligibility taxonomy.
     */

    /**
     * @test
     */
    public function guest_cannot_update_one(): void
    {
        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_update_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);
        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_cannot_update_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);
        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_update_one(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);
        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_update_one(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function super_admin_can_update_one(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);
        $payload = [
            'parent_id' => $testEligibilityTypeTaxonomy->parent_id,
            'name' => 'PHPUnit Test Service Eligibility',
            'order' => 1,
        ];

        Passport::actingAs($user);
        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);
    }

    /**
     * @test
     */
    public function order_is_updated_when_updated_to_beginning(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $eligibilityOne = $this->testserviceEligibilityType->children()->create([
            'slug' => 'one',
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $eligibilityTwo = $this->testserviceEligibilityType->children()->create([
            'slug' => 'two',
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $eligibilityThree = $this->testserviceEligibilityType->children()->create([
            'slug' => 'three',
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$eligibilityTwo->id}", [
            'parent_id' => $eligibilityTwo->parent_id,
            'name' => $eligibilityTwo->name,
            'order' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityOne->id, 'order' => 2]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityTwo->id, 'order' => 1]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityThree->id, 'order' => 3]);
    }

    /**
     * @test
     */
    public function order_is_updated_when_updated_to_middle(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $eligibilityOne = $this->testserviceEligibilityType->children()->create([
            'slug' => 'one',
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $eligibilityTwo = $this->testserviceEligibilityType->children()->create([
            'slug' => 'two',
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $eligibilityThree = $this->testserviceEligibilityType->children()->create([
            'slug' => 'three',
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$eligibilityOne->id}", [
            'parent_id' => $eligibilityOne->parent_id,
            'name' => $eligibilityOne->name,
            'order' => 2,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityOne->id, 'order' => 2]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityTwo->id, 'order' => 1]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityThree->id, 'order' => 3]);
    }

    /**
     * @test
     */
    public function order_is_updated_when_updated_to_end(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $eligibilityOne = $this->testserviceEligibilityType->children()->create([
            'slug' => 'one',
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $eligibilityTwo = $this->testserviceEligibilityType->children()->create([
            'slug' => 'two',
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $eligibilityThree = $this->testserviceEligibilityType->children()->create([
            'slug' => 'three',
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$eligibilityTwo->id}", [
            'parent_id' => $eligibilityTwo->parent_id,
            'name' => $eligibilityTwo->name,
            'order' => 3,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityOne->id, 'order' => 1]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityTwo->id, 'order' => 3]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), ['id' => $eligibilityThree->id, 'order' => 2]);
    }

    /**
     * @test
     */
    public function order_is_updated_when_updated_to_beginning_of_another_parent(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $oldEligibilityOne = $this->testserviceEligibilityType->children()->create([
            'slug' => 'one',
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $oldEligibilityTwo = $this->testserviceEligibilityType->children()->create([
            'slug' => 'two',
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $oldEligibilityThree = $this->testserviceEligibilityType->children()->create([
            'slug' => 'three',
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $newParentEligibility = Taxonomy::factory()->create([
            'name' => 'New Service Eligibility Type',
            'parent_id' => function () {
                return Taxonomy::serviceEligibility()->id;
            },
        ]);
        $newEligibilityOne = $newParentEligibility->children()->create([
            'slug' => 'one-1',
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $newEligibilityTwo = $newParentEligibility->children()->create([
            'slug' => 'two-1',
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $newEligibilityThree = $newParentEligibility->children()->create([
            'slug' => 'three-3',
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$oldEligibilityTwo->id}", [
            'parent_id' => $newParentEligibility->id,
            'name' => $oldEligibilityTwo->name,
            'order' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        /*
         * Old parent.
         */
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $this->testserviceEligibilityType->id,
            'id' => $oldEligibilityOne->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $this->testserviceEligibilityType->id,
            'id' => $oldEligibilityThree->id,
            'order' => 2,
        ]);

        /*
         * New parent.
         */
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $oldEligibilityTwo->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityOne->id,
            'order' => 2,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityTwo->id,
            'order' => 3,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityThree->id,
            'order' => 4,
        ]);
    }

    /**
     * @test
     */
    public function order_is_updated_when_updated_to_middle_of_another_parent(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $oldEligibilityOne = $this->testserviceEligibilityType->children()->create([
            'slug' => 'one',
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $oldEligibilityTwo = $this->testserviceEligibilityType->children()->create([
            'slug' => 'two',
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $oldEligibilityThree = $this->testserviceEligibilityType->children()->create([
            'slug' => 'three',
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $newParentEligibility = Taxonomy::factory()->create([
            'name' => 'New Service Eligibility Type',
            'parent_id' => function () {
                return Taxonomy::serviceEligibility()->id;
            },
        ]);
        $newEligibilityOne = $newParentEligibility->children()->create([
            'slug' => 'one-1',
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $newEligibilityTwo = $newParentEligibility->children()->create([
            'slug' => 'two-1',
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $newEligibilityThree = $newParentEligibility->children()->create([
            'slug' => 'three-1',
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$oldEligibilityOne->id}", [
            'parent_id' => $newParentEligibility->id,
            'name' => $oldEligibilityOne->name,
            'order' => 2,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        /*
         * Old parent.
         */
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $this->testserviceEligibilityType->id,
            'id' => $oldEligibilityTwo->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $this->testserviceEligibilityType->id,
            'id' => $oldEligibilityThree->id,
            'order' => 2,
        ]);

        /*
         * New parent.
         */
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityOne->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $oldEligibilityOne->id,
            'order' => 2,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityTwo->id,
            'order' => 3,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityThree->id,
            'order' => 4,
        ]);
    }

    /**
     * @test
     */
    public function order_is_updated_when_updated_to_end_of_another_parent(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $oldEligibilityOne = $this->testserviceEligibilityType->children()->create([
            'slug' => 'one',
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $oldEligibilityTwo = $this->testserviceEligibilityType->children()->create([
            'slug' => 'two',
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $oldEligibilityThree = $this->testserviceEligibilityType->children()->create([
            'slug' => 'three',
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $newParentEligibility = Taxonomy::factory()->create([
            'name' => 'New Service Eligibility Type',
            'parent_id' => function () {
                return Taxonomy::serviceEligibility()->id;
            },
        ]);
        $newEligibilityOne = $newParentEligibility->children()->create([
            'slug' => 'one-1',
            'name' => 'One',
            'order' => 1,
            'depth' => 1,
        ]);
        $newEligibilityTwo = $newParentEligibility->children()->create([
            'slug' => 'two-1',
            'name' => 'Two',
            'order' => 2,
            'depth' => 1,
        ]);
        $newEligibilityThree = $newParentEligibility->children()->create([
            'slug' => 'three-1',
            'name' => 'Three',
            'order' => 3,
            'depth' => 1,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$oldEligibilityTwo->id}", [
            'parent_id' => $newParentEligibility->id,
            'name' => $oldEligibilityTwo->name,
            'order' => 4,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        /*
         * Old parent.
         */
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $this->testserviceEligibilityType->id,
            'id' => $oldEligibilityOne->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $this->testserviceEligibilityType->id,
            'id' => $oldEligibilityThree->id,
            'order' => 2,
        ]);

        /*
         * New parent.
         */
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityOne->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityTwo->id,
            'order' => 2,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $newEligibilityThree->id,
            'order' => 3,
        ]);
        $this->assertDatabaseHas((new Taxonomy())->getTable(), [
            'parent_id' => $newParentEligibility->id,
            'id' => $oldEligibilityTwo->id,
            'order' => 4,
        ]);
    }

    /**
     * @test
     */
    public function order_cannot_be_less_than_1_when_updated(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}", [
            'parent_id' => $testEligibilityTypeTaxonomy->parent_id,
            'name' => $testEligibilityTypeTaxonomy->name,
            'order' => 0,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function order_cannot_be_greater_than_count_plus_1_when_updated(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $testEligibilityTypeTaxonomies = Taxonomy::factory()->count(5)->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomies->get(0)->id}", [
            'parent_id' => $this->testserviceEligibilityType->id,
            'name' => $testEligibilityTypeTaxonomies->get(0)->name,
            'order' => 6,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function updateTaxonomyServiceEligibilityWithUniqueSlugAsSuperAdmin200(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        $category1 = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
            'name' => 'Taxonomy Slug Test',
            'slug' => 'taxonomy-slug-test',
            'order' => 1,
        ]);
        $category2 = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
            'name' => 'Other Taxonomy',
            'slug' => 'other-taxonomy',
            'order' => 2,
        ]);
        $payload = [
            'parent_id' => $this->testserviceEligibilityType->id,
            'name' => 'Taxonomy Slug Test',
            'order' => 2,
        ];

        $this->assertDatabaseHas('taxonomies', [
            'id' => $category1->id,
            'name' => 'Taxonomy Slug Test',
            'slug' => 'taxonomy-slug-test',
        ]);

        Passport::actingAs($user);
        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$category2->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas('taxonomies', [
            'id' => $category2->id,
            'name' => 'Taxonomy Slug Test',
            'slug' => 'taxonomy-slug-test-1',
        ]);
    }

    /**
     * @test
     */
    public function audit_created_when_updated(): void
    {
        $this->fakeEvents();

        $user = User::factory()->create()->makeSuperAdmin();
        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('PUT', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}", [
            'parent_id' => $testEligibilityTypeTaxonomy->parent_id,
            'name' => 'PHPUnit Test Eligibility Type Taxonomy',
            'order' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $testEligibilityTypeTaxonomy) {
            return ($event->getAction() === Audit::ACTION_UPDATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $testEligibilityTypeTaxonomy->id);
        });
    }

    /*
     * Delete a specific category taxonomy.
     */

    /**
     * @test
     */
    public function guest_cannot_delete_one(): void
    {
        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        $response = $this->json('DELETE', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_delete_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);
        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('DELETE', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_cannot_delete_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);
        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('DELETE', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_delete_one(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);
        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('DELETE', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_delete_one(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('DELETE', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function super_admin_can_delete_one(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        $newParentEligibility = Taxonomy::factory()->create([
            'name' => 'New Service Eligibility Type',
            'parent_id' => function () {
                return Taxonomy::serviceEligibility()->id;
            },
        ]);
        $childEligibilities = Taxonomy::factory()->count(5)->create([
            'parent_id' => $newParentEligibility->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('DELETE', "/core/v1/taxonomies/service-eligibilities/{$newParentEligibility->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing((new Taxonomy())->getTable(), ['id' => $newParentEligibility->id]);
        foreach ($childEligibilities as $childEligibility) {
            $this->assertDatabaseMissing((new Taxonomy())->getTable(), ['id' => $childEligibility->id]);
        }
    }

    /**
     * @test
     */
    public function audit_created_when_deleted(): void
    {
        $this->fakeEvents();

        $user = User::factory()->create()->makeSuperAdmin();
        $newParentEligibility = Taxonomy::factory()->create([
            'name' => 'New Service Eligibility Type',
            'parent_id' => function () {
                return Taxonomy::serviceEligibility()->id;
            },
        ]);
        Taxonomy::factory()->create([
            'parent_id' => $newParentEligibility->id,
        ]);

        Passport::actingAs($user);
        $this->json('DELETE', "/core/v1/taxonomies/service-eligibilities/{$newParentEligibility->id}");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $newParentEligibility) {
            return ($event->getAction() === Audit::ACTION_DELETE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $newParentEligibility->id);
        });
    }

    /**
     * @test
     */
    public function service_eligibility_relationships_are_destroyed_when_deleted(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        $service = Service::factory()->create();
        $testEligibilityTypeTaxonomy = Taxonomy::factory()->create([
            'parent_id' => $this->testserviceEligibilityType->id,
        ]);

        $service->serviceEligibilities()->create([
            'taxonomy_id' => $testEligibilityTypeTaxonomy->id,
        ]);

        $this->assertDatabaseHas((new ServiceEligibility())->getTable(), [
            'service_id' => $service->id,
            'taxonomy_id' => $testEligibilityTypeTaxonomy->id,
        ]);

        Passport::actingAs($user);
        $response = $this->json('DELETE', "/core/v1/taxonomies/service-eligibilities/{$testEligibilityTypeTaxonomy->id}");

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing((new ServiceEligibility())->getTable(), [
            'service_id' => $service->id,
            'taxonomy_id' => $testEligibilityTypeTaxonomy->id,
        ]);
    }
}
