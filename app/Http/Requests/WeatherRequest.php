<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WeatherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->route('city')) {
            $this->merge([
                'city' => trim((string) $this->route('city')),
            ]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'city' => ['required', 'string', 'max:100', 'regex:/^[\p{L}\s\-\'.]+$/u'],
        ];
    }
}
