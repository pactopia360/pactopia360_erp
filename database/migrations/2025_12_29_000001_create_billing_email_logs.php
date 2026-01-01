<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $conn = 'mysql_admin';

    public function up(): void
    {
        if (Schema::connection($this->conn)->hasTable('billing_email_logs')) {
            Schema::connection($this->conn)->table('billing_email_logs', function (Blueprint $t) {
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','email_id')) {
                    $t->string('email_id', 64)->nullable()->after('id')->index();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','account_id')) {
                    $t->string('account_id', 64)->nullable()->index();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','period')) {
                    $t->string('period', 7)->nullable()->index();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','to_list')) {
                    $t->text('to_list')->nullable();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','provider_message_id')) {
                    $t->string('provider_message_id', 190)->nullable()->index();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','open_count')) {
                    $t->unsignedInteger('open_count')->default(0);
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','click_count')) {
                    $t->unsignedInteger('click_count')->default(0);
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','first_open_at')) {
                    $t->timestamp('first_open_at')->nullable();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','last_open_at')) {
                    $t->timestamp('last_open_at')->nullable();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','first_click_at')) {
                    $t->timestamp('first_click_at')->nullable();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','last_click_at')) {
                    $t->timestamp('last_click_at')->nullable();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','sent_at')) {
                    $t->timestamp('sent_at')->nullable();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','failed_at')) {
                    $t->timestamp('failed_at')->nullable();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','queued_at')) {
                    $t->timestamp('queued_at')->nullable()->index();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','status')) {
                    $t->string('status', 32)->default('queued')->index();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','template')) {
                    $t->string('template', 80)->default('statement')->index();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','email')) {
                    $t->string('email', 190)->nullable()->index();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','subject')) {
                    $t->string('subject', 255)->nullable();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','provider')) {
                    $t->string('provider', 50)->nullable()->index();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','payload')) {
                    $t->longText('payload')->nullable();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','meta')) {
                    $t->longText('meta')->nullable();
                }
                if (!Schema::connection($this->conn)->hasColumn('billing_email_logs','created_at')) {
                    $t->timestamps();
                }
            });

            return;
        }

        Schema::connection($this->conn)->create('billing_email_logs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('email_id', 64)->nullable()->index();

            $t->string('account_id', 64)->nullable()->index();
            $t->string('period', 7)->nullable()->index();

            $t->string('email', 190)->nullable()->index();   // principal (compat)
            $t->text('to_list')->nullable();                 // lista real (csv)
            $t->string('template', 80)->default('statement')->index();
            $t->string('status', 32)->default('queued')->index(); // queued|sent|failed
            $t->string('provider', 50)->nullable()->index();
            $t->string('provider_message_id', 190)->nullable()->index();
            $t->string('subject', 255)->nullable();

            $t->longText('payload')->nullable();
            $t->longText('meta')->nullable();

            $t->unsignedInteger('open_count')->default(0);
            $t->unsignedInteger('click_count')->default(0);
            $t->timestamp('first_open_at')->nullable();
            $t->timestamp('last_open_at')->nullable();
            $t->timestamp('first_click_at')->nullable();
            $t->timestamp('last_click_at')->nullable();

            $t->timestamp('queued_at')->nullable()->index();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('failed_at')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        // No bajamos en prod, pero aquí queda por consistencia
        // Schema::connection($this->conn)->dropIfExists('billing_email_logs');
    }
};
