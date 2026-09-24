<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One universal intent ("Precios", "Horarios"...): the stable half of a
 * topic, shared by every business of every sector.
 */
#[Fillable(['key', 'name', 'description', 'sort_order'])]
class QuestionIntent extends Model
{
    /**
     * The menu the conversation analyst chooses from, keyed for the reply.
     *
     * @return array<string, array{id: int, name: string, description: string}>
     */
    public static function forAnalysis(): array
    {
        return self::query()
            ->orderBy('sort_order')
            ->get(['id', 'key', 'name', 'description'])
            ->mapWithKeys(fn (self $intent): array => [$intent->key => [
                'id' => (int) $intent->id,
                'name' => $intent->name,
                'description' => $intent->description,
            ]])
            ->all();
    }
}
