<?php

namespace Tests\Feature;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeBannerCarouselTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_banners_render_as_a_swipeable_horizontal_track(): void
    {
        foreach (range(1, 3) as $position) {
            Banner::query()->create([
                'title' => "Banner {$position}",
                'subtitle' => "Promotion {$position}",
                'image_path' => "banners/banner-{$position}.jpg",
                'is_active' => true,
                'sort_order' => $position,
            ]);
        }

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('aria-roledescription="carousel"', false)
            ->assertSee('aria-label="1 of 3"', false)
            ->assertSee('aria-label="2 of 3"', false)
            ->assertSee('aria-label="3 of 3"', false)
            ->assertSee('translate3d(calc(-', false)
            ->assertSee('beginSwipe($event)', false)
            ->assertSee('settleLoop()', false)
            ->assertDontSee('x-show="active ===', false);
    }
}
