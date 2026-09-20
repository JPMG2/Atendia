<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The assistant's pen: when the customer states a real datum (name, email),
 * the model files it on THIS customer's record. The customer is pinned at
 * construction — the model never picks whose record it writes.
 */
class RememberCustomerFact implements Tool
{
    private const array FIELDS = ['name', 'email', 'birthday', 'marketing_opt_in'];

    public function __construct(
        private readonly Customer $customer,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Guarda un dato REAL que el cliente acaba de decir en la conversación: '
            .'su nombre ("name"), su correo ("email"), su cumpleaños ("birthday", formato '
            .'AAAA-MM-DD; sin año usá 0004) o su aceptación de recibir ofertas '
            .'("marketing_opt_in", valor "yes", SOLO si aceptó explícitamente). Usala '
            .'apenas el cliente lo diga, una vez por dato. No guardes apodos ni datos '
            .'que el cliente no dijo.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $field = (string) $request['field'];
        $value = trim((string) $request['value']);

        if (! in_array($field, self::FIELDS, true) || $value === '') {
            return 'Dato no guardado: campo desconocido o vacío.';
        }

        if ($field === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return 'Dato no guardado: el correo no parece válido.';
        }

        if ($field === 'birthday' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return 'Dato no guardado: el cumpleaños va en formato AAAA-MM-DD.';
        }

        if ($field === 'marketing_opt_in') {
            if ($value !== 'yes') {
                return 'Dato no guardado: el permiso solo se sella con "yes".';
            }

            $this->customer->sealOptIn();

            return 'Permiso anotado.';
        }

        $this->customer->rememberFact($field, $value);

        return 'Anotado.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'field' => $schema->string()->required(),
            'value' => $schema->string()->required(),
        ];
    }
}
