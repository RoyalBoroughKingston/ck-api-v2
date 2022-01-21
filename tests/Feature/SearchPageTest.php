<?php

namespace Tests\Feature;

use Illuminate\Http\Response;
use Tests\TestCase;
use Tests\UsesElasticsearch;

class SearchPageTest extends TestCase implements UsesElasticsearch
{
    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateTaxonomies();
        $this->truncateCollectionCategories();
        $this->truncateCollectionPersonas();
    }

    /*
     * Perform a search for services.
     */

    public function test_guest_can_search()
    {
        $response = $this->json('POST', '/core/v1/search/pages', [
            'query' => 'test',
        ]);

        $response->assertStatus(Response::HTTP_OK);
    }
}
