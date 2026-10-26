<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_compiled_assets_use_the_page_origin_when_app_url_differs(): void
    {
        $this->seed();
        $response = $this->get('http://127.0.0.1:8000/')->assertOk();
        $html = $response->getContent();
        preg_match_all('/(?:href|src)="([^"]*\/build\/[^"]+)"/', $html, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $path) {
            $this->assertStringStartsWith('/build/', $path);
            $this->assertFileExists(public_path($path));
        }
        $this->assertStringContainsString("script-src 'self'", $response->headers->get('Content-Security-Policy'));
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $scripts);
        foreach ($scripts[1] as $json) {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('https://schema.org', $data['@context'] ?? null);
        }
    }
}
