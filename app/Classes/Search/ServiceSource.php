<?php

declare(strict_types=1);

namespace App\Classes\Search;

use App\Dto\SearchHitDto;
use App\Interfaces\Main\SearchSource;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The tenant's services, by words and by meaning: the vector is what finds
 * "para la tiroides" when the shelf says "Perfil tiroideo".
 */
class ServiceSource implements SearchSource
{
    public string $group {
        get => 'search.groups.services';
    }

    public string $icon {
        get => 'scissors';
    }

    public int $order {
        get => 1;
    }

    public function byWords(string $term, int $limit): Collection
    {
        return Service::matching($term, $limit)
            ->map(fn (Service $service): SearchHitDto => $this->hit(
                (int) $service->id,
                (string) $service->name,
                (string) ($service->description ?? ''),
            ))
            ->values();
    }

    public function byMeaning(array $vector, int $limit): Collection
    {
        return collect(Service::closestMany($vector, $limit))
            ->map(fn (array $row): SearchHitDto => $this->hit(
                $row['id'], $row['name'], '', semantic: true,
            ));
    }

    private function hit(int $id, string $name, string $description, bool $semantic = false): SearchHitDto
    {
        return new SearchHitDto(
            group: $this->group,
            icon: $this->icon,
            title: $name,
            subtitle: $description !== '' ? Str::limit($description, 70) : __('search.subtitles.service'),
            url: route('my-services', ['buscar' => $name]),
            key: SearchHitDto::keyFor($this->group, $id),
            semantic: $semantic,
        );
    }
}
