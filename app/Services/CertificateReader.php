<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Asks a server which TLS certificate it presents for a name and when that
 * certificate stops being valid. The only thing in the app that opens a raw
 * TLS socket, so a test swaps this class and never touches the network.
 */
class CertificateReader
{
    /**
     * @param  string  $connectTo  Where to open the socket: the origin itself, not the name the public resolves to.
     * @param  string  $serverName  The name asked for (SNI), which picks the certificate.
     *
     * @throws RuntimeException when nothing answers or the answer carries no certificate
     */
    public function expiresAt(string $connectTo, string $serverName, int $port = 443): CarbonImmutable
    {
        // Only the date is read, so the chain is not judged: an expired or
        // untrusted certificate must still tell us when it expires.
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'peer_name' => $serverName,
            'SNI_enabled' => true,
        ]]);

        $socket = @stream_socket_client("ssl://{$connectTo}:{$port}", $code, $message, 5, STREAM_CLIENT_CONNECT, $context);

        if ($socket === false) {
            throw new RuntimeException("{$connectTo}:{$port} did not answer ({$message})");
        }

        $certificate = stream_context_get_params($socket)['options']['ssl']['peer_certificate'] ?? null;
        $parsed = $certificate === null ? false : openssl_x509_parse($certificate);

        if ($parsed === false || ! isset($parsed['validTo_time_t'])) {
            throw new RuntimeException("{$connectTo}:{$port} sent no readable certificate");
        }

        return CarbonImmutable::createFromTimestamp((int) $parsed['validTo_time_t']);
    }
}
