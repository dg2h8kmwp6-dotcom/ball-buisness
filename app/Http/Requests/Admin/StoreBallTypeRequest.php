<?php

namespace App\Http\Requests\Admin;

use App\Support\BallPhoto;
use Illuminate\Foundation\Http\FormRequest;

class StoreBallTypeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'color_hex' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'price_per_ball_cents' => ['required', 'integer', 'min:1', 'max:100000'],
            'diameter_mm' => ['required', 'integer', 'min:10', 'max:500'],
            'quantity_step' => ['required', 'integer', 'min:1', 'max:10000'],
            'min_quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],

            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:12288'],
            'crop_x' => ['nullable', 'integer', 'min:0'],
            'crop_y' => ['nullable', 'integer', 'min:0'],
            'crop_size' => ['nullable', 'integer', 'min:'.BallPhoto::MIN_CROP],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'color_hex.regex' => 'Pick a colour, or enter a hex value like #e8392e.',
            'photo.max' => 'That photo is larger than 12MB. Please use a smaller one.',
            'crop_size.min' => 'That crop is too small to make a sharp photo — zoom out a little.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'color_hex' => is_string($this->input('color_hex'))
                ? strtolower(trim($this->input('color_hex')))
                : $this->input('color_hex'),
        ]);
    }

    /**
     * The crop rectangle, when a photo was uploaded with one.
     *
     * @return array{x: int, y: int, size: int}|null
     */
    public function crop(): ?array
    {
        if (! $this->hasFile('photo')) {
            return null;
        }

        $size = $this->integer('crop_size');

        if ($size <= 0) {
            return null;
        }

        return [
            'x' => $this->integer('crop_x'),
            'y' => $this->integer('crop_y'),
            'size' => $size,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ballTypeAttributes(): array
    {
        return collect($this->validated())
            ->only([
                'name',
                'color_hex',
                'price_per_ball_cents',
                'diameter_mm',
                'quantity_step',
                'min_quantity',
                'description',
                'sort_order',
                'is_active',
            ])
            ->all();
    }
}
