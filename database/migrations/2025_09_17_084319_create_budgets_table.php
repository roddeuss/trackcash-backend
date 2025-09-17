<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');        // pemilik budget
            $table->unsignedBigInteger('category_id');    // kategori terkait
            $table->string('name')->nullable();           // nama budget opsional
            $table->decimal('amount', 15, 2);             // batas nominal
            $table->enum('period', ['monthly', 'weekly', 'yearly', 'custom'])->default('monthly');
            $table->date('start_date')->nullable();       // untuk custom
            $table->date('end_date')->nullable();         // untuk custom
            $table->boolean('deleted')->default(false);

            // audit trail
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            // foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
