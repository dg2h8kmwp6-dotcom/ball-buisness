<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ball_types', function (Blueprint $table) {
            // Path on the configured image disk to a square photo of the real
            // ball, uploaded and cropped in the admin area.
            $table->string('image_path')->nullable()->after('color_hex');
        });
    }

    public function down(): void
    {
        Schema::table('ball_types', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
