<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\SocialNetwork;

class CreateSocialNetwork
{
    /**
     * Create a social network.
     *
     * @param  array{
     *     name: string,
     *     url: string,
     *     icon: string|null,
     *     abbreviation: string|null,
     *     is_active: bool
     * }  $data  Payload YA validado, tal como lo arma SocialNetworkForm::transformServiceData().
     */
    public function handle(array $data): SocialNetwork
    {
        return SocialNetwork::query()->create($data);
    }
}
