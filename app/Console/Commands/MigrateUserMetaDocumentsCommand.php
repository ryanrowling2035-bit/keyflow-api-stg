<?php

namespace App\Console\Commands;

use App\Models\EmployeeDocument;
use App\Models\UserMeta;
use Illuminate\Console\Command;

class MigrateUserMetaDocumentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:migrate-documents-to-table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing compliance documents from user_meta into employee_documents table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting migration of documents from user_meta to employee_documents...');

        $docTypeMap = [
            '_passportFile'           => ['type' => 'Passport', 'title' => 'Passport', 'expKey' => '_passportExpiryDate'],
            '_emiratesIdFile'         => ['type' => 'Emirates ID', 'title' => 'Emirates ID', 'expKey' => '_emiratesIdExpiryDate'],
            '_visaFile'               => ['type' => 'Visa', 'title' => 'Visa', 'expKey' => '_visaExpiryDate'],
            '_labourContractFile'     => ['type' => 'Labour Contract', 'title' => 'Labour Contract', 'expKey' => '_labourContractExpiryDate'],
            '_ndaFile'                => ['type' => 'NDA', 'title' => 'NDA', 'expKey' => '_ndaExpiryDate'],
            '_employmentContractFile' => ['type' => 'Employment Contract', 'title' => 'Employment Contract', 'expKey' => '_employmentContractExpiryDate'],
            '_certificatesFile'       => ['type' => 'Certificates', 'title' => 'Certificates', 'expKey' => '_certificatesExpiryDate'],
            '_insuranceFile'          => ['type' => 'Medical Insurance', 'title' => 'Medical Insurance', 'expKey' => '_insuranceExpiryDate'],
        ];

        $migratedCount = 0;
        $updatedCount = 0;

        foreach ($docTypeMap as $metaKey => $config) {
            $metas = UserMeta::where('metaKey', $metaKey)->get();

            foreach ($metas as $meta) {
                $userId = $meta->userId;
                $val = $meta->metaValue;
                $decoded = json_decode($val, true);

                if (! is_array($decoded)) {
                    continue;
                }

                // Find expiry date for this user & doc if available
                $expiryDate = null;
                if ($config['expKey']) {
                    $expMeta = UserMeta::where('userId', $userId)
                        ->where('metaKey', $config['expKey'])
                        ->first();
                    $expiryDate = $expMeta?->metaValue;
                }

                foreach ($decoded as $fileData) {
                    if (! is_array($fileData) || empty($fileData['filePath'])) {
                        continue;
                    }

                    $filePath = $fileData['filePath'];
                    // Clean filePath if it contains full domain
                    if (str_contains($filePath, '/api/documents/')) {
                        $parts = explode('/api/documents/', $filePath);
                        $filePath = 'documents/' . end($parts);
                    }

                    $fileName = $fileData['fileName'] ?? basename($filePath);
                    $fileType = $fileData['type'] ?? null;

                    $existing = EmployeeDocument::where('user_id', $userId)
                        ->where('document_type', $config['type'])
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'file_path'   => $existing->file_path ?: $filePath,
                            'file_name'   => $existing->file_name ?: $fileName,
                            'file_type'   => $existing->file_type ?: $fileType,
                            'expiry_date' => $existing->expiry_date ?: $expiryDate,
                        ]);
                        $updatedCount++;
                    } else {
                        EmployeeDocument::create([
                            'user_id'       => $userId,
                            'document_type' => $config['type'],
                            'title'         => $config['title'],
                            'file_path'     => $filePath,
                            'file_name'     => $fileName,
                            'file_type'     => $fileType,
                            'expiry_date'   => $expiryDate,
                        ]);
                        $migratedCount++;
                    }
                }
            }
        }

        // Also check if any expiry dates exist in user_meta that do not have an employee_document yet
        $expiryOnlyMap = [
            '_passportExpiryDate'       => 'Passport',
            '_emiratesIdExpiryDate'     => 'Emirates ID',
            '_visaExpiryDate'           => 'Visa',
            '_labourContractExpiryDate' => 'Labour Contract',
            '_labourCardExpiryDate'     => 'Labour Contract',
            '_insuranceExpiryDate'      => 'Medical Insurance',
            '_ndaExpiryDate'            => 'NDA',
            '_employmentContractExpiryDate' => 'Employment Contract',
            '_certificatesExpiryDate'   => 'Certificates',
        ];

        foreach ($expiryOnlyMap as $expKey => $docType) {
            $expMetas = UserMeta::where('metaKey', $expKey)->whereNotNull('metaValue')->get();
            foreach ($expMetas as $meta) {
                $userId = $meta->userId;
                $expiryDate = $meta->metaValue;

                $existing = EmployeeDocument::where('user_id', $userId)
                    ->where('document_type', $docType)
                    ->first();

                if (! $existing && ! empty($expiryDate)) {
                    EmployeeDocument::create([
                        'user_id'       => $userId,
                        'document_type' => $docType,
                        'title'         => $docType,
                        'file_path'     => null,
                        'expiry_date'   => $expiryDate,
                    ]);
                    $migratedCount++;
                } elseif ($existing && empty($existing->expiry_date) && ! empty($expiryDate)) {
                    $existing->update(['expiry_date' => $expiryDate]);
                    $updatedCount++;
                }
            }
        }

        $this->info("Completed migration: {$migratedCount} documents created, {$updatedCount} documents updated.");

        return Command::SUCCESS;
    }
}

