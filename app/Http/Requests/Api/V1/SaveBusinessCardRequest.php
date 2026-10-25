<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Classes\Main\BusinessCards;
use App\Classes\Main\Client;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One card of "Mi negocio", rule for rule as the web's own form validates it:
 * the rules come from that form and so does the cleaning of the values. The
 * business is the caller's, never a field of the body.
 */
class SaveBusinessCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // A PUT is partial: what the body does not say stays as the card holds it today.
        $cards = $this->cards();

        $this->replace($cards->tidy($this->card(), [...$cards->current($this->card()), ...$this->except('logo_file')]));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // The logo is a file: it has its own endpoint and its own moderation.
        return collect($this->cards()->rulesFor($this->card()))->except('logo_file')->all();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->cards()->namesFor($this->card());
    }

    public function card(): string
    {
        return (string) $this->route('card');
    }

    private function cards(): BusinessCards
    {
        $cards = Client::for($this->user())->businessCards;

        // EnsureAppAccess already refused anyone without a business.
        abort_if($cards === null, 404);

        return $cards;
    }
}
