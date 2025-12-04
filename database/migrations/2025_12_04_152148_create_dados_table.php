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
        Schema::create('dados', function (Blueprint $table) {
            $table->id();
            
            $table->string('status')->nullable();

            $table->decimal('amount', 10, 2)->nullable();
            $table->integer('amount_cents')->nullable();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('cpf')->nullable();

            $table->string('ip')->nullable();
            $table->string('method')->nullable();
            $table->bigInteger('event_time')->nullable();
            $table->text('page_url')->nullable();

            $table->text('client_user_agent')->nullable();

            $table->text('fbp')->nullable();
            $table->text('fbc')->nullable();
            $table->text('fbclid')->nullable();

            $table->text('utm_source')->nullable();
            $table->text('utm_campaign')->nullable();
            $table->text('utm_medium')->nullable();
            $table->text('utm_content')->nullable();
            $table->text('utm_term')->nullable();

            $table->string('pix_key')->nullable();
            $table->string('pix_description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dados');
    }
};
