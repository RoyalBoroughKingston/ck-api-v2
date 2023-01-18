<?php

declare(strict_types=1);

use App\Search\ElasticSearch\Settings\PagesIndexSettings;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;

final class CreatePageIndex implements MigrationInterface
{
    /**
     * The mapping for the fields.
     *
     * @var array
     */
    protected $mapping = [
        'properties' => [
            'id' => ['type' => 'keyword'],
            'enabled' => ['type' => 'boolean'],
            'title' => [
                'type' => 'text',
                'fields' => [
                    'keyword' => ['type' => 'keyword'],
                ],
            ],
            'content' => [
                'properties' => [
                    'introduction' => [
                        'properties' => [
                            'title' => ['type' => 'text'],
                            'content' => ['type' => 'text'],
                        ],
                    ],
                    'about' => [
                        'properties' => [
                            'title' => ['type' => 'text'],
                            'content' => ['type' => 'text'],
                        ],
                    ],
                    'info_pages' => [
                        'properties' => [
                            'title' => ['type' => 'text'],
                            'content' => ['type' => 'text'],
                        ],
                    ],
                    'collections' => [
                        'properties' => [
                            'title' => ['type' => 'text'],
                            'content' => ['type' => 'text'],
                        ],
                    ],
                ],
            ],
            'collection_categories' => ['type' => 'text'],
            'collection_personas' => ['type' => 'text'],
        ],
    ];

    /**
     * Run the migration.
     */
    public function up(): void
    {
        $settings = (new PagesIndexSettings())->getSettings();
        Index::createIfNotExistsRaw('pages', $this->mapping, $settings);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Index::dropIfExists('pages');
    }
}
