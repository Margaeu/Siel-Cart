<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Models\Category;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryAdminDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        // The current users table intentionally has no email_verified_at
        // column, while the legacy factory still supplies one.
        $this->actingAs(User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'category-admin@example.com',
            'password' => 'password',
        ]));
        Filament::setCurrentPanel('admin');
    }

    public function test_duplicate_category_displays_a_warning_instead_of_a_database_exception(): void
    {
        Category::factory()->create([
            'name' => 'Athletics',
            'slug' => 'athletics',
        ]);

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Athletics',
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['name'])
            ->assertNotified('Category already exists');

        $this->assertSame(1, Category::where('slug', 'athletics')->count());
    }

    public function test_names_that_generate_the_same_slug_are_also_rejected(): void
    {
        Category::factory()->create([
            'name' => 'Tatak CLSU',
            'slug' => 'tatak-clsu',
        ]);

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Tatak--CLSU!',
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['name'])
            ->assertNotified('Category already exists');

        $this->assertSame(1, Category::where('slug', 'tatak-clsu')->count());
    }

    public function test_category_cannot_be_renamed_to_an_existing_category(): void
    {
        $existing = Category::factory()->create([
            'name' => 'Athletics',
            'slug' => 'athletics',
        ]);
        $category = Category::factory()->create([
            'name' => 'Books',
            'slug' => 'books',
        ]);

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['name' => 'Athletics'])
            ->call('save')
            ->assertHasFormErrors(['name'])
            ->assertNotified('Category already exists');

        $this->assertSame('Books', $category->fresh()->name);
        $this->assertSame('Athletics', $existing->fresh()->name);
    }
}
