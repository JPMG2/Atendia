<?php

declare(strict_types=1);

use App\Enums\ExchangeRateSource;
use App\Services\ExchangeRates\BcvPage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/*
| On 19/11/2026 the BCV changed certificate issuer and the read broke until
| someone fixed it by hand. Her rule: the system learns the new intermediate
| itself, and only keeps it when the chain reaches a root it already trusts.
| The chain here is generated on the spot, so the test never expires.
*/

beforeEach(function (): void {
    $this->storage = sys_get_temp_dir().'/bcv-cert-'.uniqid();
    File::makeDirectory($this->storage.'/app', 0755, true);
    app()->useStoragePath($this->storage);

    $this->cnf = $this->storage.'/openssl.cnf';
    file_put_contents($this->cnf, <<<'CNF'
        [ req ]
        distinguished_name = dn
        [ dn ]
        [ v3_ca ]
        basicConstraints = critical, CA:true
        keyUsage = critical, keyCertSign, cRLSign
        subjectKeyIdentifier = hash
        [ v3_leaf ]
        basicConstraints = CA:false
        keyUsage = critical, digitalSignature, keyEncipherment
        extendedKeyUsage = serverAuth
        subjectAltName = DNS:www.bcv.org.ve
        authorityInfoAccess = caIssuers;URI:http://ca.test/intermediate.crt
        CNF);
});

afterEach(function (): void {
    File::deleteDirectory($this->storage);
});

/** @return array{0: OpenSSLCertificate, 1: OpenSSLAsymmetricKey} */
function testCertificate(string $name, string $cnf, string $extensions, ?array $issuer = null): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'config' => $cnf]);
    $csr = openssl_csr_new(['commonName' => $name], $key, ['config' => $cnf, 'digest_alg' => 'sha256']);
    $certificate = openssl_csr_sign($csr, $issuer[0] ?? null, $issuer[1] ?? $key, 30, [
        'config' => $cnf,
        'x509_extensions' => $extensions,
        'digest_alg' => 'sha256',
    ], random_int(1, PHP_INT_MAX));

    return [$certificate, $key];
}

function pemOf(OpenSSLCertificate $certificate): string
{
    openssl_x509_export($certificate, $pem);

    return $pem;
}

/** The page reader, with the BCV's certificate and this machine's roots swapped for the generated ones. */
function bcvReaderWith(string $leafPem, string $rootsFile): BcvPage
{
    return new class($leafPem, $rootsFile) extends BcvPage
    {
        public function __construct(private readonly string $leaf, private readonly string $roots) {}

        protected function peerCertificate(): ?string
        {
            return $this->leaf;
        }

        protected function systemRoots(): string
        {
            return $this->roots;
        }
    };
}

/** The first visit fails on the certificate, the issuer address serves $intermediateDer, the second visit reads. */
function fakeBcvAfterIssuerChange(string $intermediateDer): void
{
    $visits = 0;

    Http::fake(function (Request $request) use (&$visits, $intermediateDer) {
        if ($request->url() === 'http://ca.test/intermediate.crt') {
            return Http::response($intermediateDer);
        }

        if (++$visits === 1) {
            throw new ConnectionException('cURL error 60: SSL certificate OpenSSL verify result: unable to get local issuer certificate (20)');
        }

        return Http::response('<div id="dolar"><strong>917,77510000</strong></div> Fecha Valor: <span content="2026-11-19T00:00:00-04:00">x</span>');
    });
}

function derOf(string $pem): string
{
    return base64_decode(preg_replace('/-----[^-]+-----|\s/', '', $pem));
}

it('learns the new intermediate on its own when the chain reaches a trusted root, and reads the page', function (): void {
    $root = testCertificate('Test Root', $this->cnf, 'v3_ca');
    $intermediate = testCertificate('Test Intermediate OV', $this->cnf, 'v3_ca', $root);
    [$leaf] = testCertificate('www.bcv.org.ve', $this->cnf, 'v3_leaf', $intermediate);
    file_put_contents($this->storage.'/roots.pem', pemOf($root[0]));
    fakeBcvAfterIssuerChange(derOf(pemOf($intermediate[0])));

    $reading = bcvReaderWith(pemOf($leaf), $this->storage.'/roots.pem')->read();

    expect($reading->official)->toBe(917.7751)
        ->and($reading->source)->toBe(ExchangeRateSource::BcvPage)
        ->and(openssl_x509_parse(file_get_contents(storage_path(BcvPage::LEARNED)))['subject']['CN'])->toBe('Test Intermediate OV');
});

it('refuses an intermediate that does not reach a trusted root, and keeps nothing', function (): void {
    $root = testCertificate('Test Root', $this->cnf, 'v3_ca');
    $intermediate = testCertificate('Test Intermediate OV', $this->cnf, 'v3_ca', $root);
    [$leaf] = testCertificate('www.bcv.org.ve', $this->cnf, 'v3_leaf', $intermediate);
    // This machine trusts another root: the learned chain must not be believed.
    file_put_contents($this->storage.'/roots.pem', pemOf(testCertificate('Some Other Root', $this->cnf, 'v3_ca')[0]));
    fakeBcvAfterIssuerChange(derOf(pemOf($intermediate[0])));

    expect(fn () => bcvReaderWith(pemOf($leaf), $this->storage.'/roots.pem')->read())->toThrow(ConnectionException::class)
        ->and(is_file(storage_path(BcvPage::LEARNED)))->toBeFalse();
});

it('finds the issuer address inside a certificate', function (): void {
    $root = testCertificate('Test Root', $this->cnf, 'v3_ca');
    [$leaf] = testCertificate('www.bcv.org.ve', $this->cnf, 'v3_leaf', $root);

    expect(BcvPage::issuerUrl(pemOf($leaf)))->toBe('http://ca.test/intermediate.crt');
});
