<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_provider')->nullable()->index()->after('status');
            $table->string('paypal_order_id')->nullable()->index()->after('stripe_payment_intent_id');
            $table->string('paypal_capture_id')->nullable()->index()->after('paypal_order_id');
        });

        // Everything taken before PayPal existed went through Stripe.
        DB::table('orders')
            ->whereNull('payment_provider')
            ->update(['payment_provider' => 'stripe']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_provider', 'paypal_order_id', 'paypal_capture_id']);
        });
    }
};
