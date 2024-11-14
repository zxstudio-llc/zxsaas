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
        Schema::create('offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name')->index();
            $table->string('description')->nullable();
            $table->string('type')->nullable(); // product, service, etc.
            $table->integer('price')->default(0);
            $table->foreignId('income_account_id')->nullable()->constrained('accounts')->nullOnDelete(); // income account e.g. sales/invoice
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts')->nullOnDelete(); // expense account e.g. purchase/bill
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offerings');
    }
};
