<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('begeniler');
    }

    public function down(): void
    {
        // The like system was removed from the app; this migration is intentionally irreversible.
    }
};
