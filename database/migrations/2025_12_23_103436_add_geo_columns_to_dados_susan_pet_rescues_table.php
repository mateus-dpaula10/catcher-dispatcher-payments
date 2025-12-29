<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dados_susan_pet_rescues', function (Blueprint $table) {
            $table->char('_country', 2)->nullable()->after('ip');
            $table->string('_region_code', 32)->nullable()->after('_country');
            $table->string('_region', 120)->nullable()->after('_region_code');
            $table->string('_city', 120)->nullable()->after('_region');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dados_susan_pet_rescues', function (Blueprint $table) {
            $table->dropColumn(['_country', '_region_code', '_region', '_city']);
        });
    }
};
