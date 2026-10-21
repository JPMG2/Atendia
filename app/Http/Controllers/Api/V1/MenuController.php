<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuItemResource;
use App\Models\Menu;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** The web's client menu, in the web's order, for the app's home buttons. */
class MenuController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return MenuItemResource::collection(Menu::forApp());
    }
}
