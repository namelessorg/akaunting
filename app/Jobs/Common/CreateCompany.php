<?php

namespace App\Jobs\Common;

use App\Abstracts\Job;
use App\Events\Common\CompanyCreated;
use App\Events\Common\CompanyCreating;
use App\Models\Common\Company;
use App\Services\TelegramService;
use Artisan;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CreateCompany extends Job
{
    /** @var Company */
    protected $company;

    protected $request;

    /**
     * Create a new job instance.
     *
     * @param  $request
     */
    public function __construct($request)
    {
        $this->request = $this->getRequestInstance($request);
    }

    /**
     * Execute the job.
     *
     * @return Company
     */
    public function handle()
    {
        $current_company_id = company_id();

        event(new CompanyCreating($this->request));

        \DB::transaction(function () {
            $this->company = Company::create($this->request->all());

            $this->company->makeCurrent();

            if ($this->request->has('telegram_channel_id')) {
                setting()->set('company.telegram_channel_id', $this->request->get('telegram_channel_id'));
            }

            if ($this->request->has('telegram_observer_token')) {
                setting()->set('company.telegram_observer_token', $this->request->get('telegram_observer_token'));
            }

            /** @var TelegramService $telegramService */
            $telegramService = app(TelegramService::class);
            if ($this->request->has('telegram_additional_public_channels')) {
                $publicChannels = trim($this->request->get('telegram_additional_public_channels'));
                if (!$publicChannels) {
                    setting()->set('company.telegram_additional_public_channels', '[]');
                } else {
                    $publicChannelsRaw = preg_split('/[^\d-]/', $publicChannels);
                    $verifiedPublicChannels = [];
                    foreach ($publicChannelsRaw as $channel) {
                        if (!is_numeric($channel)) {
                            continue;
                        }
                        $channel = trim($channel);
                        if (!$telegramService->isAccessedChannel(setting('company.telegram_observer_token'), $channel)) {
                            throw new BadRequestHttpException("Chat#$channel is not a channel/group/supergroup");
                        }
                        $verifiedPublicChannels[$channel] = true;
                    }

                    setting()->set('company.telegram_additional_public_channels', json_encode(array_keys($verifiedPublicChannels), JSON_THROW_ON_ERROR));
                }
            }

            setting()->set('wizard.completed', 1);

            $telegramService->setWebhook(setting('company.telegram_observer_token'), $this->company->id);

            setting()->save();

            $this->callSeeds();

            $this->updateSettings();
        });

        event(new CompanyCreated($this->company));

        if (!empty($current_company_id)) {
            company($current_company_id)->makeCurrent();
        }

        return $this->company;
    }

    protected function callSeeds()
    {
        // Set custom locale
        if ($this->request->has('locale')) {
            app()->setLocale($this->request->get('locale'));
        }

        // Company seeds
        Artisan::call('company:seed', [
            'company' => $this->company->id
        ]);

        if (!$user = user()) {
            return;
        }

        // Attach company to user logged in
        $user->companies()->attach($this->company->id);

        // User seeds
        Artisan::call('user:seed', [
            'user' => $user->id,
            'company' => $this->company->id,
        ]);
    }

    protected function updateSettings()
    {
        if ($this->request->file('logo')) {
            $company_logo = $this->getMedia($this->request->file('logo'), 'settings', $this->company->id);

            if ($company_logo) {
                $this->company->attachMedia($company_logo, 'company_logo');

                setting()->set('company.logo', $company_logo->id);
            }
        }

        // Create settings
        setting()->set([
            'company.name' => $this->request->get('name'),
            'company.email' => $this->request->get('email'),
            'company.tax_number' => $this->request->get('tax_number'),
            'company.phone' => $this->request->get('phone'),
            'company.address' => $this->request->get('address'),
            'default.currency' => $this->request->get('currency'),
            'default.locale' => $this->request->get('locale', 'en-GB'),
        ]);

        if (!empty($this->request->settings)) {
            foreach ($this->request->settings as $name => $value) {
                setting()->set([$name => $value]);
            }
        }

        setting()->save();
    }
}
