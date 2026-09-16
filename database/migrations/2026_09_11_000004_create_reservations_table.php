<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('voiture_id')->constrained('voitures')->cascadeOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->decimal('montant', 10, 2)->nullable();
            $table->string('statut')->index();
            $table->timestamps();

            $table->index(['agence_id', 'date_debut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
