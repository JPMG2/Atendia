# Correo — siempre por el canal

- Un mail jamás sale por `Mail::` directo. La puerta única es `App\Messaging\Channels\Email`,
  que captura el locale EN el request (un worker no tiene sesión) y reporta sin romper la
  operación que lo disparó.
- `(new Email($modelo, [$destinatario], MiMailable::class, [$extra]))->send()`. El
  destinatario lo decide el CANAL, nunca el Mailable. Los argumentos extra van en el 4º parámetro.
- El Mailable es `ShouldQueue`: el canal entrega a la cola y vuelve.
- Un medio nuevo (WhatsApp saliente de sistema) es una **subclase** de `Channel`, no un
  método más en el contrato.

Candados: `GoldenRulesMailChannelTest` + `check-mail-channel-golden-rules.sh` (cero `Mail::`
en `app/` fuera de `app/Messaging`).
