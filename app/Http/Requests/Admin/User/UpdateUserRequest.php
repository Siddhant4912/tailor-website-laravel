<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        return auth()->check() && auth()->user()->role === 'ADM';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'role' => 'required|in:ADM,TLR,USR',
            'status' => 'required|in:active,blocked'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages()
    {
        return [
            'role.required' => 'Role is required',
            'role.in' => 'Invalid role selected',
            'status.required' => 'Status is required',
            'status.in' => 'Invalid status selected'
        ];
    }
}