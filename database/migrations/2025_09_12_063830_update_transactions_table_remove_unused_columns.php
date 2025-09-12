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
            if (Schema::hasColumn('transactions', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('transactions', 'date')) {
                $table->dropColumn('date');
            }
            if (Schema::hasColumn('transactions', 'note')) {
                $table->dropColumn('note');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'type')) {
                $table->string('type')->nullable()->after('category_id');
            }
            if (!Schema::hasColumn('transactions', 'date')) {
                $table->date('date')->nullable()->after('amount');
            }
            if (!Schema::hasColumn('transactions', 'note')) {
                $table->text('note')->nullable()->after('description');
            }
        });
    }
};
