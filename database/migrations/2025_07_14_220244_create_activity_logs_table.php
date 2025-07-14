<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class, 'user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 100);
            $table->morphs('entity');
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('api_endpoint', 191)->nullable();
            $table->string('request_method', 10)->nullable();
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'idx_user_activity');
            $table->index(['action'], 'idx_action');
            $table->index(['api_endpoint'], 'idx_api_endpoint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
