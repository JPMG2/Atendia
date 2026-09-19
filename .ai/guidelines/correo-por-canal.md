# Correo por canal — un mail jamás sale por `Mail::` directo (regla de oro)

> Todo correo de AtendIa sale por **`App\Messaging\Channels\Email`** — la
> puerta única que escribe el ritual completo: captura el locale EN el
> request (un worker no tiene sesión y mandaría el fallback) y reporta sin
> romper la operación que lo disparó. Un `Mail::` crudo saltea las dos cosas.

Esta regla está **blindada**: el test guardián
`tests/Feature/GoldenRulesMailChannelTest.php` y el hook
`check-mail-channel-golden-rules.sh` fallan si aparece `Mail::` en `app/`
fuera de `app/Messaging`. Nació de la auditoría de consistencia del
2026-09-19: `DeviceChallenge` había derivado a `Mail::to()` directo.

## Cómo se envía

```php
use App\Messaging\Channels\Email;

// El modelo del que habla el mensaje + a quién va + la clase del Mailable.
(new Email($business, [$business->email], ReferralLink::class))->send();

// Mailable con argumentos extra tras el modelo (ej. un código de un solo uso):
(new Email($user, [$user->email], DeviceChallengeCode::class, [$code]))->send();
```

- **El destinatario lo decide el canal**, nunca el Mailable (el mismo mensaje
  tiene que poder ir a cualquiera).
- El Mailable es `ShouldQueue`: el canal entrega a la cola y vuelve.
- Un medio nuevo (WhatsApp saliente de sistema) = **una subclase** de
  `Channel`, jamás un método más en el contrato
  (ver memoria `atendia-messaging-canales`).

## Checklist de salida

- [ ] Cero `Mail::` fuera de `app/Messaging`.
- [ ] Argumentos extra del Mailable van por el 4º parámetro del canal.
- [ ] `./vendor/bin/pest --filter=GoldenRulesMailChannel` en verde.
