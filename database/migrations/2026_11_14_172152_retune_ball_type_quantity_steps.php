<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Move the catalogue from a bulk scale to a retail one.
     *
     * The original defaults stepped in hundreds, which is too coarse now that
     * customers order in single-digit quantities. Only rows still sitting on
     * the old default are touched, so anything already tuned in the admin
     * area is left alone.
     */
    public function up(): void
    {
        DB::table('ball_types')->where('quantity_step', 100)->update(['quantity_step' => 10]);
        DB::table('ball_types')->where('min_quantity', 100)->update(['min_quantity' => 10]);
    }

    public function down(): void
    {
        DB::table('ball_types')->where('quantity_step', 10)->update(['quantity_step' => 100]);
        DB::table('ball_types')->where('min_quantity', 10)->update(['min_quantity' => 100]);
    }
};
