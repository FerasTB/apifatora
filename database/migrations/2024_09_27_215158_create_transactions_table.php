<?php

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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            // User who initiated the transaction
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            // User who is the third-party app (if applicable)
            $table->foreignId('third_party_app_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('type', ['deposit', 'withdrawal', 'payment', 'refund']);
            $table->decimal('amount', 15, 2);
            $table->decimal('fee', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2); // amount + fee
            $table->enum('status', ['pending', 'completed', 'refunded', 'failed'])->default('pending');
            $table->unsignedBigInteger('reference_id')->nullable(); // Could reference invoices or external IDs
            $table->text('description')->nullable();
            $table->timestamp('refundable_until')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'third_party_app_id', 'type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
