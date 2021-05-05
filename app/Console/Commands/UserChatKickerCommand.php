<?php

namespace App\Console\Commands;

use App\Models\Common\Company;
use App\Models\Common\Contact;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class UserChatKickerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:chat-kicker';

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
        $telegram = $this->laravel->make(TelegramService::class);

        foreach ($companies as $company) {
            /** @var Contact[] $customers */
            /** @var Company $company */
            $company->makeCurrent();

            $customers = $company
                ->customers()
                ->where('expires_at', '<=', now())
                ->whereNotNull('expires_at')
                ->where('enabled', true)
                ->cursor();

            foreach ($customers as $customer) {
                /** @var Contact $customer */
                $this->info('Start to kick ' . $customer->id . ' from chat.');

                $e = null;
                $result = false;
                try {
                    $result = $telegram->kick($customer, $company);
                } catch (\Throwable $e) {}
                logger('Kicked ' . ($result  ? 'successfully' : 'unsuccessfully'), [
                    'from chat_id' => $company->telegram_channel_id,
                    'user_id' => $customer->telegram_id,
                    'exception' => $e,
                ]);

                if ($result) {
                    $customer->enabled = false;
                    $customer->expires_at = null;
                    $customer->save();
                }
            }
        }

        Company::forgetCurrent();
    }
}
