<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\SimulatedMalwareScanner;
use Illuminate\Console\Command;

class ScanQuarantinedDocuments extends Command
{
    protected $signature = 'documents:scan';

    protected $description = 'Run simulated malware scanning on quarantined documents';

    public function handle(SimulatedMalwareScanner $scanner): int
    {
        Document::query()
            ->where('status', 'quarantined')
            ->chunkById(100, function ($documents) use ($scanner) {
                foreach ($documents as $document) {
                    try {
                        $result = $scanner->scan($document);
                        $document->update([
                            'status' => $result,
                            'scanned_at' => now(),
                        ]);
                        $this->info("Document {$document->id}: {$result}");
                    } catch (\Throwable $exception) {
                        $this->error("Document {$document->id}: scan failed");
                        // Remains quarantined (fail closed).
                    }
                }
            });

        return self::SUCCESS;
    }
}
