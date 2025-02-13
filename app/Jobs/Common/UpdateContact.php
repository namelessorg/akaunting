<?php

namespace App\Jobs\Common;

use App\Abstracts\Job;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Common\Contact;
use App\Services\TelegramService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Telegram\Bot\Exceptions\TelegramResponseException;

class UpdateContact extends Job
{
    /**
     * @var Contact
     */
    protected $contact;

    protected $request;

    /**
     * Create a new job instance.
     *
     * @param  $contact
     * @param  $request
     */
    public function __construct($contact, $request)
    {
        $this->contact = $contact;
        $this->request = $this->getRequestInstance($request);
    }

    /**
     * Execute the job.
     *
     * @return Contact
     */
    public function handle()
    {
        $this->authorize();

        \DB::transaction(function () {
            if ($this->request->get('create_user', 'false') === 'true') {
                $this->createUser();
            } elseif ($this->contact->user) {
                $this->contact->user->update($this->request->all());
            }

            // Upload logo
            if ($this->request->file('logo')) {
                $media = $this->getMedia($this->request->file('logo'), Str::plural($this->contact->type));

                $this->contact->attachMedia($media, 'logo');
            }

            $telegramService = app(TelegramService::class);
            if ($this->contact->isCustomer() && $this->request->has('enabled') && $this->contact->enabled != $this->request->get('enabled')) {
                if ($this->request->get('enabled', false)) {
                    try {
                        $telegramService->addUser(
                            $this->contact,
                            $this->contact->company
                        );
                    } catch (TelegramResponseException $e) {
                        throw new BadRequestHttpException('Telegram integration error. ' . $e->getMessage());
                    }
                    logger("Contact#{$this->contact->id} added to telegram group for company# `{$this->contact->company->name}` from admin update contact");
                } else {
                    try {
                        $telegramService->kick(
                            $this->contact,
                            $this->contact->company);
                    } catch (TelegramResponseException $e) {
                        flash()->message($e->getMessage());
                    }
                    logger("Contact#{$this->contact->id} kicked from telegram group for company# `{$this->contact->company->name}` from admin update contact");
                }
            }

            $this->contact->update($this->request->all());
        });

        return $this->contact;
    }

    /**
     * Determine if this action is applicable.
     *
     * @return void
     */
    public function authorize()
    {
        if (($this->request['enabled'] == 0) && ($relationships = $this->getRelationships())) {
            $message = trans('messages.warning.disabled', ['name' => $this->contact->name, 'text' => implode(', ', $relationships)]);

            //throw new \Exception($message);
        }
    }

    public function createUser()
    {
        // Check if user exist
        if ($user = User::where('email', $this->request['email'])->first()) {
            $message = trans('messages.error.customer', ['name' => $user->name]);

            throw new \Exception($message);
        }

        $data = $this->request->all();
        $data['locale'] = setting('default.locale', 'en-GB');

        $customer_role = Role::all()->filter(function ($role) {
            return $role->hasPermission('read-client-portal');
        })->first();

        $user = User::create($data);
        $user->roles()->attach($customer_role);
        $user->companies()->attach($data['company_id']);

        $this->request['user_id'] = $user->id;
    }

    public function getRelationships()
    {
        $rels = [
            'transactions' => 'transactions',
        ];

        if ($this->contact->type == 'customer') {
            $rels['invoices'] = 'invoices';
        } else {
            $rels['bills'] = 'bills';
        }

        return $this->countRelationships($this->contact, $rels);
    }
}
