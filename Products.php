Schema::create('products', function (Blueprint $table) {
    $table->string('sku')->primary();
    $table->decimal('cost_price', 10, 2);
    $table->decimal('min_margin', 5, 2);
});