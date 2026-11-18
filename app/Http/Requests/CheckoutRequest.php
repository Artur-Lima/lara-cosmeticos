<?php

namespace App\Http\Requests;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('cpf')) {
            $this->merge(['cpf' => Cpf::normalize($this->input('cpf'))]);
        }
    }

    public function rules(): array
    {
        $delivery = $this->input('delivery_method') === DeliveryMethod::Delivery->value;

        return [
            // CPF só é pedido se a conta ainda não tem (exigido pelo Asaas).
            'cpf' => [
                Rule::requiredIf(blank($this->user()->cpf)),
                'nullable',
                new Cpf,
                Rule::unique('users', 'cpf')->ignore($this->user()->id),
            ],
            'delivery_method' => ['required', Rule::enum(DeliveryMethod::class)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'shipping_zone_id' => [
                Rule::requiredIf($delivery), 'nullable', 'integer',
                Rule::exists('shipping_zones', 'id')->where('is_active', true),
            ],
            'street' => [Rule::requiredIf($delivery), 'nullable', 'string', 'max:150'],
            'number' => [Rule::requiredIf($delivery), 'nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:80'],
            'reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
            // Subtotal que o cliente viu na tela; se o preço mudou, ele revisa antes de pagar.
            'expected_subtotal' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'cpf.required' => 'Informe seu CPF para emitir a cobrança.',
            'cpf.unique' => 'Este CPF já está em outra conta. Entre com ela ou fale com a loja.',
            'shipping_zone_id.required' => 'Escolha o seu bairro.',
            'shipping_zone_id.exists' => 'Não entregamos nesse bairro. Escolha outro ou retire na loja.',
            'street.required' => 'Informe a rua.',
            'number.required' => 'Informe o número (ou "s/n").',
        ];
    }

    public function attributes(): array
    {
        return [
            'street' => 'rua',
            'number' => 'número',
            'complement' => 'complemento',
            'reference' => 'ponto de referência',
            'notes' => 'observações',
        ];
    }
}
