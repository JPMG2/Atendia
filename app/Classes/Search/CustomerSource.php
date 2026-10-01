<?php

declare(strict_types=1);

namespace App\Classes\Search;

use App\Dto\SearchHitDto;
use App\Interfaces\Main\SearchSource;
use App\Models\Customer;
use Illuminate\Support\Collection;

/**
 * The tenant's people. Words only, and that is the right call: a name, a
 * phone and an email are identifiers, and meaning search blurs exactly those.
 */
class CustomerSource implements SearchSource
{
    public string $group {
        get => 'search.groups.customers';
    }

    public string $icon {
        get => 'users';
    }

    public int $order {
        get => 3;
    }

    public function byWords(string $term, int $limit): Collection
    {
        return Customer::matching($term, $limit)
            ->map(fn (Customer $customer): SearchHitDto => new SearchHitDto(
                group: $this->group,
                icon: $this->icon,
                title: (string) ($customer->name ?? $customer->profile_name ?? $customer->phone),
                subtitle: (string) ($customer->phone ?? $customer->email ?? ''),
                url: route('customers', ['buscar' => $customer->name ?? $customer->phone]),
                key: SearchHitDto::keyFor($this->group, (int) $customer->id),
            ))
            ->values();
    }

    public function byMeaning(array $vector, int $limit): Collection
    {
        return collect();
    }
}
