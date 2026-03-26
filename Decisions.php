Schema::create('decisions', function (Blueprint $table) {
    $table->id();
    $table->string('sku');
    $table->decimal('current_price', 10, 2);
    $table->decimal('suggested_price', 10, 2);
    $table->string('status');
    $table->timestamps();
});