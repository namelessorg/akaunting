<?php

namespace App\Http\Requests\Common;

use Illuminate\Foundation\Http\FormRequest;

class Company extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $logo = 'nullable';

        if ($this->request->get('logo', null)) {
            $logo = 'mimes:' . config('filesystems.mimes') . '|between:0,' . config('filesystems.max_size') * 1024;
        }

        return [
            'name' => 'required|string',
            'telegram_channel_id' => 'required|numeric',
            'telegram_observer_token' => 'required|string',
            'telegram_additional_public_channels' => 'nullable|sometimes|string',
            'currency' => 'required|string',
            'domain' => 'nullable|string',
            'logo' => $logo,
        ];
    }
}
