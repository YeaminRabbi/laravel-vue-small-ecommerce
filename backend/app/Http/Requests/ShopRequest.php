<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('shops.manage');
    }

    public function rules(): array
    {
        return [
            'owner_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('id', User::role('shop_owner')->select('users.id')))],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('shops')->ignore($this->route('shop'))],
            'description' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['required', 'boolean']];
    }
}
