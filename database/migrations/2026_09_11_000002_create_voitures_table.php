<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voitures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('marque');
            $table->string('modele');
            $table->string('immatriculation');
            $table->string('categorie')->nullable();
            $table->unsignedSmallInteger('annee')->nullable();
            $table->decimal('prix_jour', 10, 2)->nullable();
            $table->unsignedInteger('kilometrage')->nullable();
            $table->string('statut')->index();
            $table->string('photo')->nullable();
            $table->timestamps();

            $table->unique(['agence_id', 'immatriculation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voitures');
    }
};
