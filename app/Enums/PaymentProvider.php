<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case Stripe = 'stripe';
    case PayPal = 'paypal';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Card',
            self::PayPal => 'PayPal',
        };
    }
}
