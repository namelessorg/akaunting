<?php

namespace App\Jobs\Common;

use App\Abstracts\Job;
use App\Jobs\Auth\DeleteUser;
use App\Services\TelegramService;
use App\Traits\Contacts;
use Telegram\Bot\Exceptions\TelegramResponseException;

class DeleteContact extends Job
{
    use Contacts;

    protected $contact;

    /**
     * Create a new job instance.
     *
     * @param  $contact
     */
    public function __construct($contact)
    {
        $this->contact = $contact;
    }

    /**
     * Execute the job.
     *
     * @return boolean|Exception
     */
    public function handle()
    {
        $this->authorize();

        \DB::transaction(function () {
            if ($user = $this->contact->user) {
                $this->dispatch(new DeleteUser($user));
            }

            try {
                app(TelegramService::class)->kick(
                    $this->contact,
                    $this->contact->company);
            } catch (TelegramResponseException $e) {
                flash()->message($e->getMessage());
            }
            logger("Contact#{$this->contact->id} kicked from telegram group from admin update contact");

            $this->contact->delete();
        });

        return true;
    }

    /**
     * Determine if this action is applicable.
     *
     * @return void
     */
    public function authorize()
    {
        if ($relationships = $this->getRelationships()) {
            $message = trans('messages.warning.deleted', ['name' => $this->contact->name, 'text' => implode(', ', $relationships)]);

            //throw new \Exception($message);
        }
    }

    public function getRelationships()
    {
        $rels = [
            'transactions' => 'transactions',
        ];

        if ($this->isCustomer()) {
            $rels['invoices'] = 'invoices';
        } else {
            $rels['bills'] = 'bills';
        }

        return $this->countRelationships($this->contact, $rels);
    }
}
