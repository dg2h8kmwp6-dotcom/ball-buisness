<?php

namespace Tests\Feature;

use App\Models\BallType;
use App\Models\Order;
use App\Models\User;
use App\Support\BallPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBallTypeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['ballpit.image_disk' => 'public']);

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_the_index_lists_every_ball_type_including_hidden_ones(): void
    {
        BallType::factory()->create(['name' => 'Classic Red']);
        BallType::factory()->inactive()->create(['name' => 'Retired Beige']);

        $this->actingAs($this->admin)
            ->get(route('admin.ball-types.index'))
            ->assertOk()
            ->assertSee('Classic Red')
            ->assertSee('Retired Beige');
    }

    public function test_an_admin_can_add_a_ball_type(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.ball-types.store'), [
            'name' => 'Neon Coral',
            'color_hex' => '#FF5A4D',
            'price_per_ball_cents' => 18,
            'diameter_mm' => 70,
            'min_quantity' => 100,
            'quantity_step' => 100,
            'description' => 'A loud coral.',
            'sort_order' => 3,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.ball-types.index'));

        $ballType = BallType::sole();

        $this->assertSame('Neon Coral', $ballType->name);
        $this->assertSame('neon-coral', $ballType->slug);
        // Hex values are normalised to lowercase.
        $this->assertSame('#ff5a4d', $ballType->color_hex);
        $this->assertSame(18, $ballType->price_per_ball_cents);
        $this->assertTrue($ballType->is_active);
    }

    public function test_adding_a_ball_type_with_a_clashing_name_gets_a_distinct_slug(): void
    {
        BallType::factory()->create(['name' => 'Classic Red', 'slug' => 'classic-red']);

        $this->actingAs($this->admin)->post(route('admin.ball-types.store'), [
            ...$this->validAttributes(),
            'name' => 'Classic Red',
        ])->assertRedirect();

        $this->assertSame('classic-red-2', BallType::where('name', 'Classic Red')->latest('id')->value('slug'));
    }

    public function test_an_admin_can_edit_a_ball_type(): void
    {
        $ballType = BallType::factory()->create([
            'name' => 'Classic Red',
            'slug' => 'classic-red',
            'price_per_ball_cents' => 12,
        ]);

        $this->actingAs($this->admin)->put(route('admin.ball-types.update', $ballType), [
            ...$this->validAttributes(),
            'name' => 'Classic Scarlet',
            'price_per_ball_cents' => 21,
        ])->assertRedirect(route('admin.ball-types.index'));

        $ballType->refresh();

        $this->assertSame('Classic Scarlet', $ballType->name);
        $this->assertSame(21, $ballType->price_per_ball_cents);
        // The slug keys the default catalogue and photo filenames, so it stays put.
        $this->assertSame('classic-red', $ballType->slug);
    }

    public function test_invalid_attributes_are_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.ball-types.store'), [
            'name' => '',
            'color_hex' => 'not-a-colour',
            'price_per_ball_cents' => 0,
            'diameter_mm' => 2,
            'min_quantity' => 0,
            'quantity_step' => 0,
        ])->assertSessionHasErrors([
            'name',
            'color_hex',
            'price_per_ball_cents',
            'diameter_mm',
            'min_quantity',
            'quantity_step',
        ]);

        $this->assertSame(0, BallType::count());
    }

    public function test_a_ball_type_can_be_hidden_from_the_shop(): void
    {
        $ballType = BallType::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)->put(route('admin.ball-types.update', $ballType), [
            ...$this->validAttributes(),
            // An unchecked checkbox is simply absent from the request.
        ])->assertRedirect();

        $this->assertFalse($ballType->refresh()->is_active);
    }

    public function test_an_uploaded_photo_is_cropped_and_stored(): void
    {
        $ballType = BallType::factory()->create(['slug' => 'classic-red']);

        $this->actingAs($this->admin)->put(route('admin.ball-types.update', $ballType), [
            ...$this->validAttributes(),
            'photo' => $this->photo(900, 600),
            'crop_x' => 150,
            'crop_y' => 50,
            'crop_size' => 500,
        ])->assertRedirect();

        $path = $ballType->refresh()->image_path;

        $this->assertNotNull($path);
        $this->assertStringStartsWith('ball-types/classic-red-', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);

        // Stored square, at the crop size capped to the maximum.
        [$width, $height] = $this->storedDimensions($path);
        $this->assertSame($width, $height);
        $this->assertSame(500, $width);
    }

    public function test_a_large_crop_is_downsized_to_the_maximum(): void
    {
        $ballType = BallType::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.ball-types.update', $ballType), [
            ...$this->validAttributes(),
            'photo' => $this->photo(1600, 1600),
            'crop_x' => 0,
            'crop_y' => 0,
            'crop_size' => 1600,
        ])->assertRedirect();

        [$width, $height] = $this->storedDimensions($ballType->refresh()->image_path);

        $this->assertSame(BallPhoto::SIZE, $width);
        $this->assertSame(BallPhoto::SIZE, $height);
    }

    public function test_a_crop_outside_the_image_is_clamped_rather_than_failing(): void
    {
        $ballType = BallType::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.ball-types.update', $ballType), [
            ...$this->validAttributes(),
            'photo' => $this->photo(400, 400),
            // Deliberately out of bounds.
            'crop_x' => 9000,
            'crop_y' => 9000,
            'crop_size' => 9000,
        ])->assertRedirect();

        [$width, $height] = $this->storedDimensions($ballType->refresh()->image_path);

        $this->assertSame(400, $width);
        $this->assertSame(400, $height);
    }

    public function test_replacing_a_photo_deletes_the_previous_file(): void
    {
        $ballType = BallType::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.ball-types.update', $ballType), [
            ...$this->validAttributes(),
            'photo' => $this->photo(),
            'crop_x' => 0, 'crop_y' => 0, 'crop_size' => 400,
        ]);

        $first = $ballType->refresh()->image_path;

        $this->actingAs($this->admin)->put(route('admin.ball-types.update', $ballType), [
            ...$this->validAttributes(),
            'photo' => $this->photo(),
            'crop_x' => 0, 'crop_y' => 0, 'crop_size' => 400,
        ]);

        $second = $ballType->refresh()->image_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_a_photo_can_be_removed_leaving_the_ball_type_in_place(): void
    {
        $ballType = BallType::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.ball-types.update', $ballType), [
            ...$this->validAttributes(),
            'photo' => $this->photo(),
            'crop_x' => 0, 'crop_y' => 0, 'crop_size' => 400,
        ]);

        $path = $ballType->refresh()->image_path;

        $this->actingAs($this->admin)
            ->delete(route('admin.ball-types.photo.destroy', $ballType))
            ->assertRedirect();

        $this->assertNull($ballType->refresh()->image_path);
        Storage::disk('public')->assertMissing($path);
        $this->assertTrue($ballType->exists());
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        $ballType = BallType::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.ball-types.update', $ballType), [
            ...$this->validAttributes(),
            'photo' => UploadedFile::fake()->create('notes.pdf', 64, 'application/pdf'),
            'crop_x' => 0, 'crop_y' => 0, 'crop_size' => 400,
        ])->assertSessionHasErrors('photo');

        $this->assertNull($ballType->refresh()->image_path);
    }

    public function test_a_tiny_crop_is_rejected_with_a_helpful_message(): void
    {
        $ballType = BallType::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.ball-types.update', $ballType), [
            ...$this->validAttributes(),
            'photo' => $this->photo(),
            'crop_x' => 0, 'crop_y' => 0, 'crop_size' => 20,
        ])->assertSessionHasErrors('crop_size');

        $this->assertNull($ballType->refresh()->image_path);
    }

    public function test_a_ball_type_with_no_orders_can_be_deleted(): void
    {
        $ballType = BallType::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.ball-types.destroy', $ballType))
            ->assertRedirect(route('admin.ball-types.index'));

        $this->assertSame(0, BallType::count());
    }

    public function test_a_ball_type_in_past_orders_cannot_be_deleted(): void
    {
        $ballType = BallType::factory()->create(['name' => 'Classic Red']);
        $order = Order::factory()->create();

        $order->items()->create([
            'ball_type_id' => $ballType->id,
            'ball_type_name' => $ballType->name,
            'color_hex' => $ballType->color_hex,
            'quantity' => 500,
            'unit_price_cents' => 12,
            'line_total_cents' => 6000,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.ball-types.destroy', $ballType))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, BallType::count());
    }

    public function test_a_photo_shows_on_the_storefront_instead_of_the_flat_colour(): void
    {
        $ballType = BallType::factory()->create(['name' => 'Classic Red']);

        $this->actingAs($this->admin)->put(route('admin.ball-types.update', $ballType), [
            ...$this->validAttributes(),
            'name' => 'Classic Red',
            'is_active' => '1',
            'photo' => $this->photo(),
            'crop_x' => 0, 'crop_y' => 0, 'crop_size' => 400,
        ]);

        $path = $ballType->refresh()->image_path;

        $this->get(route('order.create'))
            ->assertOk()
            ->assertSee($path, escape: false)
            ->assertSee('data-image="'.$ballType->imageUrl().'"', escape: false);
    }

    public function test_the_order_page_explains_itself_when_the_catalogue_is_empty(): void
    {
        $this->get(route('order.create'))
            ->assertOk()
            ->assertSee('No ball colours are available yet.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validAttributes(): array
    {
        return [
            'name' => 'Classic Red',
            'color_hex' => '#e8392e',
            'price_per_ball_cents' => 12,
            'diameter_mm' => 70,
            'min_quantity' => 100,
            'quantity_step' => 100,
            'sort_order' => 0,
        ];
    }

    /**
     * A real PNG, so GD actually has something to decode and crop.
     */
    protected function photo(int $width = 400, int $height = 400): UploadedFile
    {
        return UploadedFile::fake()->image('ball.png', $width, $height);
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function storedDimensions(string $path): array
    {
        $contents = Storage::disk('public')->get($path);
        $image = imagecreatefromstring($contents);

        try {
            return [imagesx($image), imagesy($image)];
        } finally {
            imagedestroy($image);
        }
    }
}
