<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaptoPkmMelibatkanMhsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession(['sso_user' => [
            'sub' => 'test', 'name' => 'Test', 'email' => 'test@example.test',
            'nomor_identitas' => '199999999999999001',
        ]]);
        \App\Models\Penugasan::create([
            'peran' => 'rektor',
            'nip' => '199999999999999001',
            'nama' => 'Test Rektor',
            'lingkup' => 'universitas',
        ]);
    }

    public function test_pkm_melibatkan_mahasiswa_summary_returns_correct_columns_and_data(): void
    {
        $response = $this->getJson('/api/sapto/tables/pkm-melibatkan-mhs/summary?ts=2021');

        $response->assertOk()
            ->assertJsonPath('table_code', '7')
            ->assertJsonPath('table_name', 'PkM DTPS yang Melibatkan Mahasiswa')
            ->assertJsonStructure([
                'table_code',
                'table_name',
                'ts',
                'columns' => [
                    '*' => ['key', 'label', 'align'],
                ],
                'rows',
                'metrics',
            ]);
    }
}
