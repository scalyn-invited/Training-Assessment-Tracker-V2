<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_homepage_routes_to_the_protected_workspace(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');
    }
}
