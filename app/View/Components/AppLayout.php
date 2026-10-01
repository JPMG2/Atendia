<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\Menu;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public ?string $title;

    /**
     * Screens reached as a Blade view with a nested component (the whole client
     * panel) never run Livewire's page-component title, so the tab would say
     * the brand and nothing else: the menu names it instead. It is resolved
     * here, not in render(): the component's own props overwrite whatever data
     * render() hands the layout.
     */
    public function __construct(?string $title = null)
    {
        $this->title = $title ?? Menu::titleFor(request()->route()?->getName());
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
