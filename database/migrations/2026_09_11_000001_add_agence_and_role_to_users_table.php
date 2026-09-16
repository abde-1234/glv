<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agence_id')
                ->nullable()
                ->after('id')
                ->constrained('agences')
                ->nullOnDelete();
            $table->enum('role', ['super_admin', 'admin_agence'])
                ->default('admin_agence')
                ->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['agence_id']);
            $table->dropColumn(['agence_id', 'role']);
        });
    }
};
