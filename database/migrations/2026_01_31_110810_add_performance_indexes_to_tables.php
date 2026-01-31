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
            $table->index(['user_id', 'deleted', 'transaction_date'], 'trx_user_del_date_idx');
            $table->index(['user_id', 'deleted', 'category_id'], 'trx_user_del_cat_idx');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->index(['user_id', 'deleted'], 'budgets_user_del_idx');
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->index(['user_id', 'deleted'], 'inv_user_del_idx');
        });

        Schema::table('banks', function (Blueprint $table) {
            $table->index(['user_id', 'deleted'], 'banks_user_del_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['user_id'], 'cat_user_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('trx_user_del_date_idx');
            $table->dropIndex('trx_user_del_cat_idx');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropIndex('budgets_user_del_idx');
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->dropIndex('inv_user_del_idx');
        });

        Schema::table('banks', function (Blueprint $table) {
            $table->dropIndex('banks_user_del_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('cat_user_idx');
        });
    }
};
