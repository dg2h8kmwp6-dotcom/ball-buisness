<?php

namespace App\Http\Requests;

use App\Enums\PaymentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCheckoutRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'provider' => ['required', Rule::enum(PaymentProvider::class)],

            'shipping_name' => ['required', 'string', 'max:255'],
            'shipping_phone' => ['nullable', 'string', 'max:40'],
            'shipping_line1' => ['required', 'string', 'max:255'],
            'shipping_line2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:120'],
            'shipping_region' => ['required', 'string', 'max:120'],
            'shipping_postal_code' => ['required', 'string', 'max:20'],
            'shipping_country' => [
                'required',
                'string',
                Rule::in(array_keys(config('ballpit.shipping_countries'))),
            ],
            'delivery_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'shipping_name' => 'recipient name',
            'shipping_line1' => 'street address',
            'shipping_city' => 'city',
            'shipping_region' => 'state or province',
            'shipping_postal_code' => 'postal code',
            'shipping_country' => 'country',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'provider.required' => 'Choose how you would like to pay.',
            'shipping_country.in' => 'Sorry — we do not ship to that country yet. Email us and we will see what we can do.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('shipping_country'))) {
            $this->merge(['shipping_country' => strtoupper($this->input('shipping_country'))]);
        }
    }

    public function provider(): PaymentProvider
    {
        return PaymentProvider::from($this->validated('provider'));
    }

    /**
     * The order columns this request supplies.
     *
     * @return array<string, mixed>
     */
    public function orderAttributes(): array
    {
        return collect($this->validated())
            ->except('provider')
            ->all();
    }
}
