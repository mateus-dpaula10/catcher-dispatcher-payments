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
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();

            $table->string('token', 80)->unique();           // identifica o email
            $table->string('external_id', 80)->index();      // liga na sua doação
            $table->string('to_email')->index();
            $table->string('subject')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('last_opened_at')->nullable();

            $table->unsignedInteger('click_count')->default(0);
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamp('last_clicked_at')->nullable();

            // aqui guardamos os destinos reais dos links (sem querystring no email)
            $table->json('links')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};
