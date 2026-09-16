<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->date('date_debut_abonnement')->nullable()->after('type_abonnement');
            $table->decimal('montant_abonnement', 12, 2)->nullable()->after('date_expiration');
        });
    }

    public function down(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->dropColumn(['date_debut_abonnement', 'montant_abonnement']);
        });
    }
};
