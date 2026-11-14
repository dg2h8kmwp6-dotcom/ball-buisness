<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Step the quantity controls in 5s rather than 10s.
     *
     * Only rows still on the previous default of 10 are touched. A colour the
     * admin has deliberately given some other step keeps it.
     */
    public function up(): void
    {
        DB::table('ball_types')->where('quantity_step', 10)->update(['quantity_step' => 5]);
    }

    public function down(): void
    {
        DB::table('ball_types')->where('quantity_step', 5)->update(['quantity_step' => 10]);
    }
};
