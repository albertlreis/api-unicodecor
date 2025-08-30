<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property-read string $email
 */
class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc,dns'],
        ];
    }
}
