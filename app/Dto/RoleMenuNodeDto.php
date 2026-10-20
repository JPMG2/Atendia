<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * One row of the admin menu as the role editor paints it: a screen with the
 * switch that opens it, or a group of them. `actions` are the permissions that
 * live inside a screen (verify a payment, reset a second factor): they are not
 * menu rows, so they hang from the screen they belong to.
 */
final class RoleMenuNodeDto
{
    /**
     * The screen permissions of this subtree: what a group switch moves.
     *
     * @var list<string>
     */
    public array $screenPermissions {
        get => $this->children === []
            ? array_values(array_filter([$this->permission]))
            : array_merge(...array_map(fn (self $child): array => $child->screenPermissions, $this->children));
    }

    /** Whether the row is a group (it has screens under it). */
    public bool $isGroup {
        get => $this->children !== [];
    }

    /** A screen every staff role sees, so there is nothing to switch. */
    public bool $isAlwaysOn {
        get => $this->children === [] && $this->permission === null && $this->actions === [];
    }

    /**
     * @param  list<string>  $actions  Permissions inside this screen, in the order they are shown.
     * @param  list<self>  $children
     */
    public function __construct(
        public readonly string $label,
        public readonly ?string $icon,
        public readonly ?string $permission,
        public readonly ?string $sharedWith,
        public readonly array $actions,
        public readonly array $children,
    ) {}
}
