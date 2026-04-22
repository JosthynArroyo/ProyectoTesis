<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contact_messages')) {
            Schema::create('contact_messages', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('correo');
                $table->string('telefono', 30)->nullable();
                $table->string('asunto');
                $table->text('mensaje');
                $table->string('estado', 30)->default('nuevo');
                $table->timestamps();
                $table->index('estado');
                $table->index('created_at');
            });

            return;
        }

        Schema::table('contact_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('contact_messages', 'nombre')) {
                $table->string('nombre')->nullable();
            }

            if (! Schema::hasColumn('contact_messages', 'correo')) {
                $table->string('correo')->nullable();
            }

            if (! Schema::hasColumn('contact_messages', 'telefono')) {
                $table->string('telefono', 30)->nullable();
            }

            if (! Schema::hasColumn('contact_messages', 'asunto')) {
                $table->string('asunto')->nullable();
            }

            if (! Schema::hasColumn('contact_messages', 'mensaje')) {
                $table->text('mensaje')->nullable();
            }

            if (! Schema::hasColumn('contact_messages', 'estado')) {
                $table->string('estado', 30)->default('nuevo');
            }

            if (! Schema::hasColumn('contact_messages', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (! Schema::hasColumn('contact_messages', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
