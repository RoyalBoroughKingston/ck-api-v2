<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Page;
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

    public function test_query_matches_page_title()
    {
        $page = factory(Page::class)->create();

        $response = $this->json('POST', '/core/v1/search/pages', [
            'query' => $page->title,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $page->id,
        ]);
    }

    public function test_query_matches_page_content()
    {
        $page = factory(Page::class)->create();

        $response = $this->json('POST', '/core/v1/search/pages', [
            'query' => $page->content['introduction']['copy'][0],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $page->id,
        ]);
    }

    public function test_query_matches_single_word_from_page_content()
    {
        $page = factory(Page::class)->create([
            'content' => [
                'introduction' => [
                    'copy' => [
                        'This is a page that helps to homeless find temporary housing.',
                    ],
                ],
            ],
        ]);

        $response = $this->json('POST', '/core/v1/search/pages', [
            'query' => 'homeless',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $page->id]);
    }

    public function test_query_matches_multiple_words_from_page_content()
    {
        $page = factory(Page::class)->create([
            'content' => [
                'introduction' => [
                    'copy' => [
                        'This is a page that helps to homeless find temporary housing.',
                    ],
                ],
            ],
        ]);

        $response = $this->json('POST', '/core/v1/search/pages', [
            'query' => 'temporary housing',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $page->id]);
    }

    public function test_query_ranks_page_title_above_page_content()
    {
        $page1 = factory(Page::class)->create(['title' => 'Thisisatest']);
        $page2 = factory(Page::class)->create([
            'content' => [
                'introduction' => [
                    'copy' => [
                        'Thisisatest',
                    ],
                ],
            ],
        ]);

        $response = $this->json('POST', '/core/v1/search/pages', [
            'query' => 'Thisisatest',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $page1->id,
        ]);

        $response->assertJsonFragment([
            'id' => $page2->id,
        ]);

        $data = $this->getResponseContent($response)['data'];
        $this->assertEquals($page1->id, $data[0]['id']);
        $this->assertEquals($page2->id, $data[1]['id']);
    }

    public function test_query_matches_collection_name()
    {
        $page1 = factory(Page::class)->create();
        $page1->updateCollections([factory(Collection::class)->create([
            'name' => 'Thisisatest',
        ])->id]);

        $page2 = factory(Page::class)->create();
        $page2->updateCollections([factory(Collection::class)->create([
            'name' => 'Should not match',
        ])->id]);

        $response = $this->json('POST', '/core/v1/search/pages', [
            'query' => 'Thisisatest',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $page1->id]);
        $response->assertJsonMissing(['id' => $page2->id]);

        // Fuzzy
        $response = $this->json('POST', '/core/v1/search/pages', [
            'query' => 'Thsiisatst',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $page1->id]);
        $response->assertJsonMissing(['id' => $page2->id]);
    }

    public function test_query_matches_partial_collection_name()
    {
        $page1 = factory(Page::class)->create();
        $page1->updateCollections([
            factory(Collection::class)->create([
                'name' => 'Testword Anotherphrase',
            ])->id,
        ]);

        $page2 = factory(Page::class)->create();
        $page2->updateCollections([
            factory(Collection::class)->create([
                'name' => 'Should not match',
            ])->id,
        ]);

        $response = $this->json('POST', '/core/v1/search/pages', [
            'query' => 'Anotherphrase',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $page1->id]);
        $response->assertJsonMissing(['id' => $page2->id]);

        // Fuzzy
        $response = $this->json('POST', '/core/v1/search/pages', [
            'query' => 'Another phrase',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $page1->id]);
        $response->assertJsonMissing(['id' => $page2->id]);
    }

    public function test_query_ranks_perfect_match_above_fuzzy_match()
    {
        $page1 = factory(Page::class)->create(['title' => 'Thisisatest']);
        $page2 = factory(Page::class)->create(['title' => 'Thsiisatst']);

        $response = $this->json('POST', '/core/v1/search/pages', [
            'query' => 'Thisisatest',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $page1->id,
        ]);

        $response->assertJsonFragment([
            'id' => $page2->id,
        ]);

        $data = $this->getResponseContent($response)['data'];
        $this->assertEquals($page1->id, $data[0]['id']);
        $this->assertEquals($page2->id, $data[1]['id']);
    }

    public function test_only_enabled_pages_returned()
    {
        $activePage = factory(Page::class)->create([
            'title' => 'Testing Page',
            'enabled' => Page::ENABLED,
        ]);
        $inactivePage = factory(Page::class)->create([
            'title' => 'Testing Page',
            'enabled' => Page::DISABLED,
        ]);

        $response = $this->json('POST', '/core/v1/search/pages', [
            'query' => 'Testing Page',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $activePage->id]);
        $response->assertJsonMissing(['id' => $inactivePage->id]);
    }
}
