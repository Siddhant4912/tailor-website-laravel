<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CheckFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        return auth()->check() && auth()->user()->role === 'ADM';
    }

    //  public function authorize()
    // {
    //     return true; // testing sathi true
    // }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',

            'email' => 'required|email|unique:users,email',

            'phone' => 'required|unique:users,phone',

            'password' => 'required|min:6',

            'role' => 'required|in:ADM,TLR,USR'
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Name is required',

            'email.required' => 'Email is required',
            'email.email' => 'Please enter valid email',
            'email.unique' => 'Email already registered',

            'phone.required' => 'Phone required',
            'phone.unique' => 'Phone already registered',

            'password.required' => 'Password required',
            'password.min' => 'Password must be 6 characters',

            'role.required' => 'Role required',
            'role.in' => 'Invalid role'
        ];
    }

      protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation Failed',
            'errors' => $validator->errors()
        ], 422));
    }
}