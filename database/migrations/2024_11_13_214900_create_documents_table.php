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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('type'); // invoice, bill, etc.
            $table->string('logo')->nullable();
            $table->string('header')->nullable();
            $table->string('subheader')->nullable();
            $table->string('document_number')->nullable();
            $table->string('order_number')->nullable(); // PO, SO, etc.
            $table->date('date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('draft');
            $table->string('currency_code')->nullable();
            $table->integer('subtotal')->default(0);
            $table->integer('tax_total')->default(0);
            $table->integer('discount_total')->default(0);
            $table->integer('total')->default(0);
            $table->integer('amount_paid')->default(0);
            $table->integer('amount_due')->storedAs('total - amount_paid');
            $table->text('terms')->nullable(); // terms, notes
            $table->text('footer')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
