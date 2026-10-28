<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaFallbackTest extends TestCase
{
    public function test_unknown_api_paths_stay_404_json_and_never_return_the_spa(): void
    {
        $this->getJson('/api/route-inconnue')->assertNotFound();
        // /storage est géré par Laravel (403 ou 404 selon le cas), jamais par l'application React
        $this->assertContains($this->get('/storage/inexistant.jpg')->getStatusCode(), [403, 404]);
    }

    public function test_unknown_pages_do_not_error(): void
    {
        // Sans build du frontend : page d'accueil Laravel ; avec build : index.html (même statut 200)
        $this->get('/loans')->assertOk();
        $this->get('/')->assertOk();
    }
}
