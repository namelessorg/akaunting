<?php

namespace App\Jobs\Common;

use App\Abstracts\Job;
use App\Events\Common\CompanyUpdated;
use App\Events\Common\CompanyUpdating;
use App\Models\Common\Company;
use App\Services\TelegramService;
use App\Traits\Users;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class UpdateCompany extends Job
{
    use Users;

    /**
     * @var Company
     */
    protected $company;

    protected $request;

    protected $current_company_id;

    /**
     * Create a new job instance.
     *
     * @param  $company
     * @param  $request
     */
    public function __construct($company, $request)
    {
        $this->company = $company;
        $this->request = $this->getRequestInstance($request);
        $this->current_company_id = company_id();
    }

    /**
     * Execute the job.
     *
     * @return Company
     */
    public function handle()
    {
        $this->authorize();

        event(new CompanyUpdating($this->company, $this->request));

        \DB::transaction(function () {
            $this->company->update($this->request->all());

            $this->company->makeCurrent();

            if ($this->request->has('name')) {
                setting()->set('company.name', $this->request->get('name'));
            }

            if ($this->request->has('email')) {
                setting()->set('company.email', $this->request->get('email'));
            }

            if ($this->request->has('tax_number')) {
                setting()->set('company.tax_number', $this->request->get('tax_number'));
            }

            if ($this->request->has('phone')) {
                setting()->set('company.phone', $this->request->get('phone'));
            }

            if ($this->request->has('address')) {
                setting()->set('company.address', $this->request->get('address'));
            }

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

            if ($this->request->get('install_webhook', false)) {
                $telegramService->setWebhook(setting('company.telegram_observer_token'), $this->company->id);
            }

            if ($this->request->has('currency')) {
                setting()->set('default.currency', $this->request->get('currency'));
            }

            if ($this->request->has('locale')) {
                setting()->set('default.locale', $this->request->get('locale'));
            }

            if ($this->request->file('logo')) {
                $company_logo = $this->getMedia($this->request->file('logo'), 'settings', $this->company->id);

                if ($company_logo) {
                    $this->company->attachMedia($company_logo, 'company_logo');

                    setting()->set('company.logo', $company_logo->id);
                }
            }

            setting()->set('wizard.completed', 1);

            setting()->save();

        });

        event(new CompanyUpdated($this->company, $this->request));

        if (!empty($this->current_company_id)) {
            company($this->current_company_id)->makeCurrent();
        }

        return $this->company;
    }

    /**
     * Determine if this action is applicable.
     *
     * @return void
     */
    public function authorize()
    {
        // Can't disable active company
        if (($this->request->get('enabled', 1) == 0) && ($this->company->id == $this->current_company_id)) {
            $message = trans('companies.error.disable_active');

            throw new \Exception($message);
        }

        // Check if user can access company
        if ($this->isNotUserCompany($this->company->id)) {
            $message = trans('companies.error.not_user_company');

            throw new \Exception($message);
        }
    }
}
