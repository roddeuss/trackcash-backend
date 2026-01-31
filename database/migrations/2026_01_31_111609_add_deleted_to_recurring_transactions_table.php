<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->boolean('deleted')->default(false)->after('is_active');
            $table->index(['user_id', 'deleted'], 'rectrx_user_del_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->dropIndex('rectrx_user_del_idx');
            $table->dropColumn('deleted');
        });
    }
};
