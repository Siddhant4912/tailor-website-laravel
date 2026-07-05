<?php
// siddhant pawar : 04-07-2026

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
  public function rules()
{
    return [
        'name'         => 'nullable|string|max:255',
        'email'        => 'nullable|email|max:255',
        'phone'        => 'nullable|string|max:20',
        'address_line' => 'nullable|string|max:255',
        'city'         => 'nullable|string|max:100',
        'state'        => 'nullable|string|max:100',
        'pincode'      => 'nullable|string|max:20',
    ];
}
}
