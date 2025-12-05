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
        Schema::create('automatic_pix_authorizations', function (Blueprint $table) {
            $table->id();
            $table->string('transfeera_id')->nullable()->unique(); 
            $table->bigInteger('amount_cents');
            $table->string('cpf'); 
            $table->string('email'); 
            $table->string('cellphone'); 
            $table->string('periodicity')->default('once');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automatic_pix_authorizations');
    }
};
