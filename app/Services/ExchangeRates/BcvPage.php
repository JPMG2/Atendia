<?php

declare(strict_types=1);

namespace App\Services\ExchangeRates;

use App\Dto\ExchangeRateReadingDto;
use App\Enums\ExchangeRateSource;
use App\Models\ExchangeRate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Reads the dollar box of the BCV home page. The BCV has no API, so this
 * breaks the day its design changes: that is why it throws instead of
 * guessing, and the chain moves on to the next source.
 */
class BcvPage
{
    public const string URL = 'https://www.bcv.org.ve/';

    /**
     * The site serves the wrong intermediate certificate, so the right ones
     * ship with the code and verification stays on. Both Sectigo R36 issuers
     * are kept: on 2026-11-19 the BCV moved from DV to OV and the read broke.
     */
    public const string INTERMEDIATE = 'certs/sectigo-r36.pem';

    /** Intermediates learned on their own when the BCV changes issuer, under storage (code is read-only for that). */
    public const string LEARNED = 'app/certs/bcv-learned.pem';

    private const string HOST = 'www.bcv.org.ve';

    /**
     * @throws RuntimeException when the page does not answer or no longer looks the way it is read
     * @throws ConnectionException when it cannot be reached, or its certificate cannot be trusted even after learning
     */
    public function read(): ExchangeRateReadingDto
    {
        try {
            $response = $this->fetch();
        } catch (ConnectionException $e) {
            // Her rule (19/11/2026): a certificate change is fixed by the system, not by the admin.
            if (! str_contains($e->getMessage(), 'cURL error 60') || ! $this->learnIntermediate()) {
                throw $e;
            }

            $response = $this->fetch();
        }

        if (! $response->successful()) {
            throw new RuntimeException('BCV page answered HTTP '.$response->status());
        }

        return self::parse($response->body());
    }

    private function fetch(): Response
    {
        return Http::withOptions(['verify' => $this->trustFile()])
            ->withUserAgent('Mozilla/5.0 (compatible; Aliadito rate check)')
            ->connectTimeout(10)
            ->timeout(20)
            ->get(self::URL);
    }

    /** cURL takes one file: the shipped intermediates, plus any learned since, joined under storage. */
    private function trustFile(): string
    {
        $learned = storage_path(self::LEARNED);

        if (! is_file($learned)) {
            return resource_path(self::INTERMEDIATE);
        }

        $bundle = storage_path('app/certs/bcv-bundle.pem');
        file_put_contents($bundle, file_get_contents(resource_path(self::INTERMEDIATE))."\n".file_get_contents($learned));

        return $bundle;
    }

    /**
     * The BCV serves its certificate without the intermediate that signed it.
     * When that issuer changes, fetch it from the address the certificate
     * itself names (its AIA) and keep it ONLY if the whole chain reaches a
     * root the system already trusts. Verification is never switched off.
     */
    private function learnIntermediate(): bool
    {
        try {
            $leaf = $this->peerCertificate();
            $url = $leaf === null ? null : self::issuerUrl($leaf);

            if ($url === null) {
                return false;
            }

            $body = Http::connectTimeout(10)->timeout(15)->get($url)->throw()->body();
            $pem = str_contains($body, 'BEGIN CERTIFICATE')
                ? $body
                : "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($body), 64, "\n")."-----END CERTIFICATE-----\n";

            if (openssl_x509_read($pem) === false || ! $this->chainsToTrustedRoot($leaf, $pem)) {
                Log::warning('BCV page: the intermediate named by its certificate did not chain to a trusted root', ['url' => $url]);

                return false;
            }

            File::ensureDirectoryExists(dirname(storage_path(self::LEARNED)));
            file_put_contents(storage_path(self::LEARNED), trim($pem)."\n", FILE_APPEND);
            Log::info('BCV page: learned the intermediate of its new certificate issuer', ['url' => $url]);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /** The issuer address inside a certificate ("CA Issuers - URI:..."), or null when it names none. */
    public static function issuerUrl(string $certificatePem): ?string
    {
        $info = openssl_x509_parse($certificatePem)['extensions']['authorityInfoAccess'] ?? '';

        return preg_match('/CA Issuers - URI:(https?:\/\/\S+)/', $info, $match) === 1 ? $match[1] : null;
    }

    private function chainsToTrustedRoot(string $leafPem, string $intermediatePem): bool
    {
        $untrusted = tempnam(sys_get_temp_dir(), 'bcv-intermediate');
        file_put_contents($untrusted, $intermediatePem);

        try {
            return openssl_x509_checkpurpose($leafPem, X509_PURPOSE_SSL_SERVER, [$this->systemRoots()], $untrusted) === true;
        } finally {
            @unlink($untrusted);
        }
    }

    /**
     * What the BCV presents, read without trusting it: it only tells where
     * its issuer lives, and nothing is kept unless the chain checks out.
     */
    protected function peerCertificate(): ?string
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => self::HOST,
        ]]);

        $socket = @stream_socket_client('ssl://'.self::HOST.':443', $errno, $error, 10, STREAM_CLIENT_CONNECT, $context);

        if ($socket === false) {
            return null;
        }

        $certificate = stream_context_get_params($socket)['options']['ssl']['peer_certificate'] ?? null;
        fclose($socket);

        return $certificate !== null && openssl_x509_export($certificate, $pem) ? $pem : null;
    }

    /** The roots this machine already trusts: the yardstick for anything learned. */
    protected function systemRoots(): string
    {
        return openssl_get_cert_locations()['default_cert_file'];
    }

    /** @throws RuntimeException when the dollar box or its value date is not where it was */
    public static function parse(string $html): ExchangeRateReadingDto
    {
        if (preg_match('/id="dolar".*?<strong[^>]*>\s*([\d.,]+)\s*<\/strong>/s', $html, $rate) !== 1) {
            throw new RuntimeException('BCV page: the dollar box was not found');
        }

        if (preg_match('/Fecha\s+Valor:\s*<span[^>]*content="([^"]+)"/u', $html, $date) !== 1) {
            throw new RuntimeException('BCV page: the value date was not found');
        }

        return new ExchangeRateReadingDto(
            ExchangeRateSource::BcvPage,
            CarbonImmutable::parse($date[1])->setTimezone(ExchangeRate::TIMEZONE)->startOfDay(),
            self::number($rate[1]),
        );
    }

    /** "889,57540000" or "1.234,5600": the BCV writes the comma as the decimal mark. */
    private static function number(string $raw): float
    {
        $value = (float) str_replace(['.', ','], ['', '.'], $raw);

        if ($value <= 0) {
            throw new RuntimeException("BCV page: '{$raw}' is not a rate");
        }

        return $value;
    }
}
