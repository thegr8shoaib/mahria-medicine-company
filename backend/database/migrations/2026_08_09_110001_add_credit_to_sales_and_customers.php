<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('credit', 12, 2)->default(0)->after('address');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'card', 'mobile', 'credit'])->default('cash')->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('credit');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'card', 'mobile'])->default('cash')->change();
        });
    }
};