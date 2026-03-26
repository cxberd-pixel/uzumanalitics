Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('sku');
    $table->decimal('price', 10, 2);
    $table->decimal('commission', 10, 2)->nullable();
    $table->string('status');
    $table->timestamps();
});