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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Shown to customers and staff as DVD-1001, DVD-1002, ...
            $table->unsignedInteger('number')->unique();
            // Unguessable id for the customer's order status page.
            $table->string('public_token', 64)->unique();
            $table->string('status')->index();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            // Null means "as soon as possible".
            $table->timestamp('pickup_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('total_cents');
            $table->string('payment_method');
            $table->string('payment_status');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Kept for reporting; the name, price and options below are copies, so old orders stay correct
            // after the menu changes or the item is deleted.
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            // Item price plus the chosen options, for one.
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('line_total_cents');
            // [{group, name, price_cents}, ...]
            $table->json('options')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
