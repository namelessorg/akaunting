<?php

namespace App\Console\Commands;

use App\Models\Common\Company;
use App\Models\Common\Contact;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Telegram\Bot\Api;

class UserExpirationReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:expiration-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders for user expiration';

    /**
     * Execute the console command.
     *
     */
    public function handle(): void
    {
        // Disable model cache
        config(['laravel-model-caching.enabled' => false]);

        // Get all companies
        /** @var Company $companies */
        $companies = Company::query()->enabled()->cursor();
        $telegram = $this->laravel->get(Api::class);

        foreach ($companies as $company) {
            /** @var Contact[] $customers */
            /** @var Company $company */
            $company->makeCurrent();
            $telegram->setAccessToken($company->telegram_observer_token);
            // Don't send reminders if disabled
            if (!setting('schedule.send_invoice_reminder')) {
                $this->info('Invoice reminders disabled by ' . $company->name . '.');

                continue;
            }

            $days = preg_split('/\D/', setting('schedule.invoice_days', 1));
            $customers = $company
                ->customers()
                ->where('enabled', true)
                ->whereNested(function (Builder $builder) use ($days) {
                    foreach ($days as $day) {
                        $builder->orWhere(\DB::raw('DATEDIFF(expires_at, NOW())'), $day);
                    }
                    $builder->orWhere('expires_at', '<=', now());
                })
                ->cursor();

            foreach ($customers as $customer) {
                $this->info('Sending invoice reminders for ' . $company->id . ' company.');

                $e = null;
                $message = null;
                try {
                    $message = $telegram->sendMessage([
                        'chat_id' => $customer->telegram_chat_id,
                        'text' => now()->diffForHumans($customer->expires_at).' expiration, need to pay the subscription for renew access',
                    ]);
                } catch (\Throwable $e) {}
                logger('Sent expiration message', [
                    'message' => $message ? $message->toArray() : null,
                    'exception' => $e,
                ]);
            }
        }

        Company::forgetCurrent();
    }
}
