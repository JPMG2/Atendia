<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A menu button. `screen` is the web's route name: the app maps it to its
 * own screen, and a group has none.
 *
 * @mixin Menu
 */
class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'label' => __($this->label_key),
            'icon' => $this->icon,
            'screen' => $this->route_name,
            'children' => self::collection($this->childrenRecursive),
        ];
    }
}
