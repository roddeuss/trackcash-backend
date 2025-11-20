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
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();

            // User yang memiliki recurring rule
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Detail recurring
            $table->string('name');  // contoh: Gaji Bulanan
            $table->enum('type', ['income', 'expense']);
            $table->decimal('amount', 15, 2)->default(0);

            // Relasi opsional
            $table->foreignId('category_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('bank_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('asset_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Frekuensi recurring
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'yearly']);

            // Parameter frekuensi tertentu
            $table->unsignedTinyInteger('day_of_month')->nullable(); // untuk monthly (1–31)
            $table->unsignedTinyInteger('day_of_week')->nullable();  // untuk weekly (0=Sun → 6=Sat)

            // Range aktif
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Jadwal berikutnya
            $table->timestamp('next_run_at')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
