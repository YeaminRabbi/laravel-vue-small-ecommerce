<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('listings.manage') && ($this->user()->can('shops.manage') || $this->route('shop')->owner_id === $this->user()->id);
    }

    public function rules(): array
    {
        return [
            'product_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer', 'exists:products,id', Rule::unique('shop_products')->where('shop_id', $this->route('shop')->id)->ignore($this->route('listing'))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'thumbnail' => ['nullable', 'url:http,https', 'max:2048'],
            'is_active' => ['required', 'boolean']];
    }
}
