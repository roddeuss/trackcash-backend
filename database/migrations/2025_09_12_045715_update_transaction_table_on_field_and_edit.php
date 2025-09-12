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
        Schema::table('transactions', function (Blueprint $table) {
            // Tambahkan kolom kalau belum ada
            if (!Schema::hasColumn('transactions', 'bank_id')) {
                $table->foreignId('bank_id')->nullable()->after('user_id')->constrained('banks')->onDelete('set null');
            }
            if (!Schema::hasColumn('transactions', 'asset_id')) {
                $table->foreignId('asset_id')->nullable()->after('bank_id')->constrained('assets')->onDelete('set null');
            }
            if (!Schema::hasColumn('transactions', 'category_id')) {
                $table->foreignId('category_id')->after('asset_id')->constrained('categories')->onDelete('cascade');
            }
            if (!Schema::hasColumn('transactions', 'amount')) {
                $table->decimal('amount', 20, 2)->after('category_id');
            }
            if (!Schema::hasColumn('transactions', 'description')) {
                $table->text('description')->nullable()->after('amount');
            }
            if (!Schema::hasColumn('transactions', 'transaction_date')) {
                $table->date('transaction_date')->after('description');
            }
            if (!Schema::hasColumn('transactions', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('transaction_date');
            }
            if (!Schema::hasColumn('transactions', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('transactions', 'deleted')) {
                $table->boolean('deleted')->default(false)->after('updated_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'bank_id')) {
                $table->dropForeign(['bank_id']);
                $table->dropColumn('bank_id');
            }
            if (Schema::hasColumn('transactions', 'asset_id')) {
                $table->dropForeign(['asset_id']);
                $table->dropColumn('asset_id');
            }
            if (Schema::hasColumn('transactions', 'category_id')) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            }
            if (Schema::hasColumn('transactions', 'amount')) {
                $table->dropColumn('amount');
            }
            if (Schema::hasColumn('transactions', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('transactions', 'transaction_date')) {
                $table->dropColumn('transaction_date');
            }
            if (Schema::hasColumn('transactions', 'created_by')) {
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('transactions', 'updated_by')) {
                $table->dropColumn('updated_by');
            }
            if (Schema::hasColumn('transactions', 'deleted')) {
                $table->dropColumn('deleted');
            }
        });
    }
};
