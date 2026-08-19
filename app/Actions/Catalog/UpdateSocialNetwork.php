<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\SocialNetwork;

class UpdateSocialNetwork
{
    /**
     * Update a social network.
     *
     * @param  array{
     *     name: string,
     *     url: string,
     *     icon: string|null,
     *     abbreviation: string|null,
     *     is_active: bool
     * }  $data  Payload YA validado, tal como lo arma SocialNetworkForm::transformServiceData().
     */
    public function handle(int $id, array $data): SocialNetwork
    {
        $network = SocialNetwork::query()->findOrFail($id);
        $network->update($data);

        return $network;
    }
}
