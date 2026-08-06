<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name', 150);
            $table->string('business_name', 200)->nullable();
            $table->string('document_type', 10)->nullable();
            $table->string('document_number', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->text('logo')->nullable();
            $table->string('timezone', 50)->default('America/Lima');
            $table->jsonb('settings')->nullable();
            $table->string('status', 20)->default('active'); // active | trial | suspended | cancelled
            $table->softDeletes();
            $table->timestamps();

            // Índices de rendimiento
            $table->index('document_number');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
