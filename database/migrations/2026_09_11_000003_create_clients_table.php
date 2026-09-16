<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('nom');
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('cin')->nullable();
            $table->string('ville')->nullable();
            $table->timestamps();

            $table->index(['agence_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
