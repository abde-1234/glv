<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->text('description')->nullable()->after('nom');
            $table->string('code_postal', 20)->nullable()->after('ville');
            $table->string('pays', 100)->default('Maroc')->after('code_postal');
            $table->string('site_web')->nullable()->after('telephone');
            $table->string('devise', 3)->default('MAD')->after('date_expiration');
            $table->string('format_date', 20)->default('d/m/Y')->after('devise');
            $table->string('langue', 10)->default('fr')->after('format_date');
            $table->unsignedSmallInteger('elements_par_page')->default(10)->after('langue');
            $table->boolean('notifications_email')->default(true)->after('elements_par_page');
            $table->boolean('rappel_reservation')->default(true)->after('notifications_email');
            $table->boolean('rapport_mensuel')->default(false)->after('rappel_reservation');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'admin_agence', 'employe'])
                ->default('admin_agence')
                ->change();
            $table->enum('statut', ['actif', 'inactif'])
                ->default('actif')
                ->after('role')
                ->index();
        });

        Schema::create('agence_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('nom');
            $table->string('type', 50);
            $table->string('fichier');
            $table->unsignedBigInteger('taille')->nullable();
            $table->timestamps();
            $table->index(['agence_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agence_documents');

        DB::table('users')->where('role', 'employe')->update(['role' => 'admin_agence']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['statut']);
            $table->dropColumn('statut');
            $table->enum('role', ['super_admin', 'admin_agence'])
                ->default('admin_agence')
                ->change();
        });

        Schema::table('agences', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'code_postal',
                'pays',
                'site_web',
                'devise',
                'format_date',
                'langue',
                'elements_par_page',
                'notifications_email',
                'rappel_reservation',
                'rapport_mensuel',
            ]);
        });
    }
};
