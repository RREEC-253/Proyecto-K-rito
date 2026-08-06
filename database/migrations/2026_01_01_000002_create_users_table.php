<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('username', 50);
            $table->string('email', 150);
            $table->text('password'); // Recomendado 'password' para compatibilidad nativa con Auth de Laravel
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('avatar')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('email_verified')->default(false);
            $table->boolean('must_change_password')->default(false);
            $table->smallInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Índices y Aislamiento Multitenant
            $table->unique(['company_id', 'username']);
            $table->unique(['company_id', 'email']);
            $table->index('company_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
