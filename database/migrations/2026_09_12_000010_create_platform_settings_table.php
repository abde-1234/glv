<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('platform_name')->default('GLV');
            $table->string('support_email')->nullable();
            $table->string('support_phone', 30)->nullable();
            $table->text('company_address')->nullable();
            $table->string('logo')->nullable();
            $table->string('default_language', 10)->default('fr');
            $table->string('default_currency', 3)->default('MAD');
            $table->string('date_format', 20)->default('d/m/Y');
            $table->unsignedSmallInteger('per_page')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
