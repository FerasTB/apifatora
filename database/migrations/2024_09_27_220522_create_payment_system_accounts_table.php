<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_system_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('System Fees Account');
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
        });

        // Seed initial system account
        DB::table('payment_system_accounts')->insert([
            'name' => 'System Fees Account',
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('payment_system_accounts')->insert([
            'name' => 'System fatorah Account',
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_system_accounts');
    }
};
