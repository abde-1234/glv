<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agences', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->string('ville')->nullable();
            $table->text('adresse')->nullable();
            $table->string('logo')->nullable();
            $table->enum('statut', ['actif', 'essai', 'suspendu', 'expire'])->default('essai');
            $table->string('type_abonnement')->nullable();
            $table->date('date_expiration')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agences');
    }
};
