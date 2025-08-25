<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    


        // 3️⃣ Recreate all columns exactly as before
        Schema::table('stores', function (Blueprint $table) {
            
            
            $table->string('latitude')->nullable()->change();
            $table->string('longitude')->nullable()->change();
      
            
          
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        // In down(), you could either drop them again or restore old structure if needed
    }
};
