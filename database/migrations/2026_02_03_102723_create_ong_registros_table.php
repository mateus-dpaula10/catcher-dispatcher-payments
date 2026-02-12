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
        Schema::create('ong_registros', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email');
            $table->string('cnpj', 32)->nullable();
            $table->boolean('cnpj_not_available')->default(false);
            $table->string('phone', 32)->nullable();
            $table->unsignedInteger('animal_count')->nullable();
            $table->unsignedInteger('caregiver_count')->nullable();
            $table->date('foundation_date')->nullable();

            $table->longText('description')->nullable();

            $table->string('street')->nullable();
            $table->string('number', 32)->nullable();
            $table->string('complement')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 16)->nullable();

            $table->string('facebook')->nullable();
            $table->boolean('facebook_not_available')->default(false);
            $table->string('instagram')->nullable();
            $table->boolean('instagram_not_available')->default(false);
            $table->string('website')->nullable();
            $table->boolean('website_not_available')->default(false);

            $table->decimal('portion_value', 12, 2)->nullable();
            $table->decimal('medicines_value', 12, 2)->nullable();
            $table->decimal('veterinarian_value', 12, 2)->nullable();
            $table->decimal('collaborators_value', 12, 2)->nullable();

            $table->json('photo_urls')->nullable();

            $table->json('monthly_costs')->nullable();

            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();

            $table->index(['email']);
            $table->index(['cnpj']);

            $table->string('source_tag')->nullable();
            $table->string('source_url', 2048)->nullable();

            $table->decimal('other_costs_value', 12, 2)->nullable();
            $table->longText('other_costs_description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ong_registros');
    }
};
