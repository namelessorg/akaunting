<?php

namespace App\Http\Requests\Setting;

use App\Abstracts\Http\FormRequest;
use App\Models\Setting\Category as CategoryModel;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Category extends FormRequest
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
        return [
            'name' => ['required', 'string', function (string $name, string $value) {
                try {
                    CategoryModel::getTypeAndArgumentByCategoryName($value);
                } catch (BadRequestException $e) {
                    throw new BadRequestHttpException($e->getMessage());
                }
            }],
            'type' => 'required|string',
            'color' => 'required|string',
        ];
    }
}
