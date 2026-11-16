<?php

namespace Tests\Feature;

use App\Services\PangkalanDataApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Jalur data SAPTO -> Gravitee -> PD UNSRI (lihat docs/gravitee.md): alamat
 * gateway dan kunci langganan dibaca dari config, token tetap langsung ke
 * server kampus.
 */
class PangkalanDataGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.pangkalan_data.base_url' => 'https://kampus.test',
            'services.pangkalan_data.email' => 'api@kampus.test',
            'services.pangkalan_data.password' => 'rahasia',
            'services.pangkalan_data.gateway_url' => 'http://gateway.test/sapto-api',
        ]);

        Http::fake([
            'kampus.test/api/get-token*' => Http::response(['token' => 'tkn-123']),
            'gateway.test/*' => Http::response(['data' => []]),
        ]);
    }

    public function test_data_goes_through_the_configured_gateway_with_the_api_key(): void
    {
        config(['services.pangkalan_data.gateway_api_key' => 'kunci-sapto']);

        app(PangkalanDataApiClient::class)->getRaw('api/v1/negara', ['limit' => 1]);

        Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'http://gateway.test/sapto-api/api/v1/negara')
            && $r->header('X-Gravitee-Api-Key') === ['kunci-sapto']
            && $r->header('Authorization') === ['Bearer tkn-123']);
    }

    public function test_api_key_header_is_omitted_when_not_configured(): void
    {
        config(['services.pangkalan_data.gateway_api_key' => null]);

        app(PangkalanDataApiClient::class)->probe('api/v1/program-studi', ['limit' => 1]);

        Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'http://gateway.test/')
            && ! $r->hasHeader('X-Gravitee-Api-Key'));
    }

    public function test_token_is_requested_directly_from_campus_not_through_the_gateway(): void
    {
        // Kredensial api/get-token dikirim sebagai query string: lewat gateway
        // berarti ikut tercatat di log analitik Gravitee.
        config(['services.pangkalan_data.gateway_api_key' => 'kunci-sapto']);

        app(PangkalanDataApiClient::class)->getRaw('api/v1/negara');

        Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://kampus.test/api/get-token'));
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'gateway.test') && str_contains($r->url(), 'get-token'));
    }
}
