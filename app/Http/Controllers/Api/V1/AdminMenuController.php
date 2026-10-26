<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuItemResource;
use App\Models\Menu;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** The admin menu of the web, in its order, for the app's staff home: only what the person may open. */
class AdminMenuController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return MenuItemResource::collection(Menu::forAdminApp());
    }
}
