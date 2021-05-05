<?php

namespace App\Jobs\Common;

use App\Abstracts\Job;
use App\Models\Auth\User;
use App\Models\Auth\Role;
use App\Models\Common\Contact;
use App\Services\TelegramService;
use Illuminate\Support\Str;

class CreateContact extends Job
{
    /**
     * @var Contact
     */
    protected $contact;

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
     * @return Contact
     */
    public function handle()
    {
        \DB::transaction(function () {
            if ($this->request->get('create_user', 'false') === 'true') {
                $this->createUser();
            }

            $this->contact = Contact::create($this->request->all());

            $telegramService = app(TelegramService::class);
            if ($this->contact->isCustomer() && $this->request->has('enabled')) {
                if ($this->request->get('enabled', false)) {
                    $telegramService->addUser(
                        $this->contact,
                        $this->contact->company
                    );
                    logger("Contact#{$this->contact->id} added to telegram group for company# `{$this->contact->company->name}` from admin update contact");
                } else {
                    // чтобы можно было кикнуть левых юзеров
                    $telegramService->kick(
                        $this->contact,
                        $this->contact->company);
                    logger("Contact#{$this->contact->id} kicked from telegram group for company# `{$this->contact->company->name}` from admin update contact");
                }
            }
            // Upload logo
            if ($this->request->file('logo')) {
                $media = $this->getMedia($this->request->file('logo'), Str::plural($this->contact->type));

                $this->contact->attachMedia($media, 'logo');
            }
        });

        return $this->contact;
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
}
