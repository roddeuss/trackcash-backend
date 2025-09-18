<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // notifikasi milik user tertentu
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // kategori/jenis pemicu (contoh: budget_threshold, transaction_created, system_info, dll)
            $table->string('type', 50);

            // tingkat pentingnya & judul/body
            $table->enum('severity', ['info', 'success', 'warning', 'error'])->default('info');
            $table->string('title');
            $table->text('message')->nullable();

            // payload tambahan (id transaksi, id budget, dll) & optional link aksi
            $table->json('data')->nullable();
            $table->string('action_url')->nullable();

            // status baca
            $table->timestamp('read_at')->nullable();

            // audit & soft delete (flag)
            $table->boolean('deleted')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            // index berguna
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'deleted']);
            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
