<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('adresse')->nullable();
            $table->text('notes')->nullable();
            $table->string('statut')->default('actif')->index();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('reference')->nullable()->unique();
            $table->decimal('prix_jour', 10, 2)->nullable();
            $table->text('notes')->nullable();
        });

        Schema::table('contrats', function (Blueprint $table) {
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn(['reference', 'prix_jour', 'notes']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['statut']);
            $table->dropColumn(['adresse', 'notes', 'statut']);
        });
    }
};
