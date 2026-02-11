<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'name' => ['required', 'string', 'max:255'],
            'paternal_surname' => ['nullable', 'string', 'max:255'],
            'maternal_surname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'personal_email' => ['nullable', 'string', 'email', 'max:255'],
            'personal_phone' => ['nullable', 'string', 'max:20'],
            'company_phone' => ['nullable', 'string', 'max:20'],
            'birthdate' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:M,F,O'],
            'address' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
