<?php

use App\Domain\Users\Enums\ContactType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('type');
            $table->string('value');
            $table->dateTime('confirmed_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'type', 'value'], 'uq_user_contacts_user_type_value');
            $table->index(['type'], 'idx_user_contacts_type');
        });

        if (Schema::hasTable('user_emails')) {
            $emails = DB::table('user_emails')->orderBy('id')->get();
            foreach ($emails as $email) {
                DB::table('user_contacts')->insert([
                    'user_id' => $email->user_id,
                    'type' => ContactType::Email->value,
                    'value' => $email->email,
                    'confirmed_at' => $email->confirmed_at,
                    'created_by' => $email->created_by,
                    'updated_by' => $email->updated_by,
                    'deleted_by' => $email->deleted_by,
                    'created_at' => $email->created_at,
                    'updated_at' => $email->updated_at,
                    'deleted_at' => $email->deleted_at,
                ]);
            }

            Schema::dropIfExists('user_emails');
        }
    }

    public function down(): void
    {
        Schema::create('user_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('email')->unique();
            $table->dateTime('confirmed_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        $contacts = DB::table('user_contacts')
            ->where('type', ContactType::Email->value)
            ->orderBy('id')
            ->get();

        foreach ($contacts as $contact) {
            DB::table('user_emails')->insert([
                'user_id' => $contact->user_id,
                'email' => $contact->value,
                'confirmed_at' => $contact->confirmed_at,
                'created_by' => $contact->created_by,
                'updated_by' => $contact->updated_by,
                'deleted_by' => $contact->deleted_by,
                'created_at' => $contact->created_at,
                'updated_at' => $contact->updated_at,
                'deleted_at' => $contact->deleted_at,
            ]);
        }

        Schema::dropIfExists('user_contacts');
    }
};
