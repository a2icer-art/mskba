<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_payment_confirmations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_booking_id');
            $table->unsignedBigInteger('payment_method_id');
            $table->json('payment_method_snapshot');
            $table->text('evidence_comment')->nullable();
            $table->unsignedBigInteger('evidence_media_id')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_comment')->nullable();
            $table->timestamps();

            $table->index(['event_booking_id', 'status']);
            $table->foreign('event_booking_id')
                ->references('id')
                ->on('event_bookings')
                ->onDelete('cascade');
            $table->foreign('payment_method_id')
                ->references('id')
                ->on('payment_methods')
                ->onDelete('restrict');
            $table->foreign('evidence_media_id')
                ->references('id')
                ->on('media')
                ->onDelete('set null');
            $table->foreign('requested_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
            $table->foreign('decided_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        Schema::table('event_bookings', function (Blueprint $table) {
            $table->string('payment_confirm_status', 32)->default('none')->after('payment_due_at');
            $table->timestamp('payment_confirmed_at')->nullable()->after('payment_confirm_status');
            $table->unsignedBigInteger('payment_last_confirmation_id')->nullable()->after('payment_confirmed_at');

            $table->foreign('payment_last_confirmation_id')
                ->references('id')
                ->on('booking_payment_confirmations')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('event_bookings', function (Blueprint $table) {
            $table->dropForeign(['payment_last_confirmation_id']);
            $table->dropColumn([
                'payment_confirm_status',
                'payment_confirmed_at',
                'payment_last_confirmation_id',
            ]);
        });

        Schema::dropIfExists('booking_payment_confirmations');
    }
};
