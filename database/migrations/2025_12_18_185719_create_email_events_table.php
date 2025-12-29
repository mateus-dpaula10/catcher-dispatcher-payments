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
        Schema::create('email_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('email_message_id')->constrained('email_messages')->cascadeOnDelete();
            $table->string('type', 10); // open | click
            $table->string('link_key', 50)->nullable(); // ex: "site", "facebook", "contact"
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['email_message_id', 'type']);
            $table->index(['email_message_id', 'link_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_events');
    }
};
