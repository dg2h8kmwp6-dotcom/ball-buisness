<?php

namespace App\Http\Requests;

use App\Models\BallType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBallSelectionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantities' => ['required', 'array', 'min:1'],
            'quantities.*' => [
                'integer',
                'min:1',
                'max:'.config('ballpit.max_quantity_per_type'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantities.required' => 'Pick at least one type of ball before placing your order.',
            'quantities.min' => 'Pick at least one type of ball before placing your order.',
            'quantities.*.max' => 'That is more balls than we can take online — email us for a bulk quote.',
        ];
    }

    /**
     * Drop any ball type the customer left at zero before validating.
     */
    protected function prepareForValidation(): void
    {
        /** @var array<int|string, mixed> $quantities */
        $quantities = $this->input('quantities', []);

        $this->merge([
            'quantities' => collect($quantities)
                ->map(fn ($quantity): int => (int) $quantity)
                ->filter(fn (int $quantity): bool => $quantity > 0)
                ->all(),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $selectedIds = array_map('intval', array_keys($this->selection()));

            $availableIds = BallType::query()
                ->active()
                ->whereIn('id', $selectedIds)
                ->pluck('id')
                ->all();

            if (array_diff($selectedIds, $availableIds) !== []) {
                $validator->errors()->add(
                    'quantities',
                    'One of the ball types you selected is no longer available.'
                );
            }
        });
    }

    /**
     * The validated selection, as a map of ball type ID => quantity.
     *
     * @return array<int, int>
     */
    public function selection(): array
    {
        /** @var array<int|string, int> $quantities */
        $quantities = $this->validated()['quantities'] ?? [];

        $selection = [];

        foreach ($quantities as $ballTypeId => $quantity) {
            $selection[(int) $ballTypeId] = (int) $quantity;
        }

        return $selection;
    }
}
