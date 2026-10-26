<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:100', 'phone' => ['required', 'regex:/^(?:\+?88)?01[3-9][0-9]{8}$/'], 'email' => 'nullable|email|max:150', 'district' => 'required|string|max:100', 'area' => 'required|in:inside,outside', 'address' => 'required|string|min:8|max:1000', 'notes' => 'nullable|string|max:1000', 'coupon' => 'nullable|string|max:40', 'idempotency_key' => 'required|uuid'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => preg_replace('/^(?:\+?88)/', '', preg_replace('/[\s-]/', '', (string) $this->phone))]);
    }
}
