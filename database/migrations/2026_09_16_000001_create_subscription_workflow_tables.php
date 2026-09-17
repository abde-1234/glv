<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('renewal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->unsignedBigInteger('pending_key')->nullable()->unique();
            $table->string('current_plan')->nullable();
            $table->date('current_expiration')->nullable();
            $table->text('message')->nullable();
            $table->text('decision_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['agence_id', 'status']);
        });

        Schema::create('subscription_notification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('event_type');
            $table->date('expiration_date')->nullable();
            $table->timestamps();
            $table->unique(['agence_id', 'event_type', 'expiration_date'], 'subscription_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_notification_events');
        Schema::dropIfExists('renewal_requests');
        Schema::dropIfExists('notifications');
    }
};
