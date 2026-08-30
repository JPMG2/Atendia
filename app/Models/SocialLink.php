<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SocialLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * La cuenta de alguien en una red social.
 *
 * El dueño es polimórfico (`linkable`): hoy la compañía y los negocios. La red
 * en sí es el maestro `SocialNetwork`; acá vive el enlace.
 */
#[Fillable(['social_network_id', 'url', 'sort_order'])]
class SocialLink extends Model
{
    /** @use HasFactory<SocialLinkFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * Auditoría del enlace: quién lo cargó, lo corrigió o lo borró, y cuándo.
     *
     * Acá el rastro pesa más que en un maestro: la baja es INMEDIATA —el tacho
     * borra la fila en el momento, no al guardar— y no hay papelera. Sin log, un
     * enlace que desaparece no deja forma de saber quién lo sacó.
     *
     * El causante (el usuario logueado) lo resuelve spatie solo.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['social_network_id', 'url', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('social');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Quién es el dueño de la cuenta: `Company` o `Business`.
     *
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<SocialNetwork, $this>
     */
    public function socialNetwork(): BelongsTo
    {
        return $this->belongsTo(SocialNetwork::class);
    }

    /**
     * Un enlace no lleva espacios: se quitan TODOS, no solo los de las puntas.
     * Mismo criterio que `SocialNetwork::normalizeUrl` — un espacio pegado al
     * copiar ("https://x.com/atendia ") rebota sin que el usuario vea por qué.
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => SocialNetwork::normalizeUrl($value),
        );
    }
}
