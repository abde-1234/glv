<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('password_reset_request_id')
                ->constrained('password_reset_requests')
                ->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 40);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['password_reset_request_id', 'created_at'], 'password_reset_event_timeline');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_request_events');
    }
};
