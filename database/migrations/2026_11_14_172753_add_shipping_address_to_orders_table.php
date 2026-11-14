<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The shipping address is collected by us at checkout rather than by the
     * payment provider, so it is stored in discrete columns the warehouse can
     * read and filter on.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_name')->nullable()->after('customer_email');
            $table->string('shipping_phone')->nullable()->after('shipping_name');
            $table->string('shipping_line1')->nullable()->after('shipping_phone');
            $table->string('shipping_line2')->nullable()->after('shipping_line1');
            $table->string('shipping_city')->nullable()->after('shipping_line2');
            $table->string('shipping_region')->nullable()->after('shipping_city');
            $table->string('shipping_postal_code')->nullable()->after('shipping_region');
            $table->string('shipping_country', 2)->nullable()->after('shipping_postal_code');
            $table->text('delivery_notes')->nullable()->after('shipping_country');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_name',
                'shipping_phone',
                'shipping_line1',
                'shipping_line2',
                'shipping_city',
                'shipping_region',
                'shipping_postal_code',
                'shipping_country',
                'delivery_notes',
            ]);
        });
    }
};
