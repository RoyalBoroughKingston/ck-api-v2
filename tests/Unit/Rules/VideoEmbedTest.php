<?php

namespace Tests\Unit\Rules;

use App\Rules\VideoEmbed;
use Tests\TestCase;

class VideoEmbedTest extends TestCase
{
    /**
     * @test
     */
    public function it_passes_video_hosting_urls()
    {
        $badUrls = [
            'https://youtube.com',
            'https://badmovie.com',
        ];

        $goodUrls = [
            'https://www.youtube.com',
            'https://vimeo.com',
            'https://player.vimeo.com',
        ];

        $videoEmbedRule = new VideoEmbed();

        foreach ($badUrls as $badUrl) {
            $this->assertFalse($videoEmbedRule->passes('video_embed', $badUrl));
        }

        foreach ($goodUrls as $goodUrl) {
            $this->assertTrue($videoEmbedRule->passes('video_embed', $goodUrl));
        }
    }
}
