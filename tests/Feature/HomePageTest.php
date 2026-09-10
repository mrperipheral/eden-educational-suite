<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_page_renders(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee(config('app.name'));
    }
}
