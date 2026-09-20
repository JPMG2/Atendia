<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\Business;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;

/**
 * The "Mis clientes" piece: the tenant's customer directory. The inbox is
 * the day's flow; this is the asset it leaves behind.
 */
class Directory
{
    public function __construct(private Business $business) {}

    /**
     * Every customer, freshest activity first.
     *
     * @var Collection<int, Customer>
     */
    public Collection $customers {
        get => $this->business->customers()
            ->orderByDesc('last_activity_at')
            ->get();
    }

    /** One customer of THIS business, or null when it is not the tenant's. */
    public function customer(int $id): ?Customer
    {
        return $this->business->customers()->find($id);
    }
}
