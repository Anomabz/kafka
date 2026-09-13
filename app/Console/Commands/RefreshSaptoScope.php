<?php

namespace App\Console\Commands;

use App\Services\SaptoReportRefresher;
use Illuminate\Console\Command;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Hitung ulang cache ringkasan laporan SAPTO untuk satu lingkup dari data
 * lokal (tanpa menarik dari API PD UNSRI) -- lihat SaptoReportRefresher.
 * Biasanya dilepas oleh tombol "Segarkan laporan" dengan lock yang sudah
 * dipegang (--scope-owner/--slot-owner); dijalankan manual tanpa itu, command
 * mengambil lock-nya sendiri dengan penjaga yang sama.
 */
class RefreshSaptoScope extends Command
{
    protected $signature = 'sapto:refresh-scope
        {--fakultas= : ID fakultas (lingkup dekan)}
        {--prodi= : ID program studi (lingkup korprodi)}
        {--by=cli : Pemicu, untuk log}
        {--scope-owner= : Pemilik lock lingkup (dari endpoint)}
        {--slot= : Nomor slot concurrency (dari endpoint)}
        {--slot-owner= : Pemilik lock slot (dari endpoint)}';

    protected $description = 'Hitung ulang cache laporan SAPTO untuk satu lingkup (prodi/fakultas/universitas) dari data lokal';

    public function handle(SaptoReportRefresher $refresher): int
    {
        try {
            $scope = $refresher->scope($this->option('fakultas') ?: null, $this->option('prodi') ?: null);

            if ($this->option('scope-owner') && $this->option('slot') && $this->option('slot-owner')) {
                $scopeOwner = (string) $this->option('scope-owner');
                $slot = (int) $this->option('slot');
                $slotOwner = (string) $this->option('slot-owner');
            } else {
                [$scopeLock, $slot, $slotLock] = $refresher->acquire($scope);
                $scopeOwner = $scopeLock->owner();
                $slotOwner = $slotLock->owner();
            }
        } catch (HttpException $e) {
            $this->warn($e->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf('[%s] mulai %s (oleh %s)', now()->toDateTimeString(), $scope['key'], $this->option('by')));

        $result = $refresher->run($scope, $scopeOwner, $slot, $slotOwner, (string) $this->option('by'));

        $this->line(sprintf('[%s] selesai %s: %d ms, %d key ditulis ulang, gagal: %s',
            now()->toDateTimeString(), $scope['key'], $result['duration_ms'], count($result['rewritten']),
            $result['failed'] === [] ? '-' : implode(', ', $result['failed'])));

        if ($this->output->isVerbose()) {
            foreach ($result['timings'] as $table => $ms) {
                $this->line(sprintf('  %-24s %7d ms', $table, $ms));
            }

            foreach ($result['rewritten'] as $key) {
                $this->line('  KEY '.$key);
            }
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
