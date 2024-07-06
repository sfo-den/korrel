<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use App\Values\LicenseStatus;
use Illuminate\Console\Command;
use Throwable;

class CheckLicenseStatusCommand extends Command
{
    protected $signature = 'koel:license:status';
    protected $description = 'Check the current Koel Plus license status.';

    public function __construct(private LicenseService $licenseService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->components->info('Checking your Koel Plus license status…');

        try {
            $status = $this->licenseService->getLicenseStatus(checkCache: false);

            switch ($status->status) {
                case LicenseStatus::STATUS_VALID:
                    $this->output->success('You have a valid Koel Plus license. Thanks for supporting Koel!');
                    $this->components->twoColumnDetail('License Key', $status->license->short_key);

                    $this->components->twoColumnDetail(
                        'Registered To',
                        "{$status->license->meta->customerName} <{$status->license->meta->customerEmail}>"
                    );

                    $this->components->twoColumnDetail('Expires On', 'Never ever');
                    $this->line('');
                    break;

                case LicenseStatus::STATUS_NO_LICENSE:
                    $this->components->info(
                        'No license found. Please consider purchasing one at https://plus.koel.dev.'
                    );
                    break;

                case LicenseStatus::STATUS_INVALID:
                    $this->components->error('Your license is invalid. Koel Plus features will not be available.');
                    break;

                default:
                    $this->components->warn('Your license status is unknown. Please try again later.');
            }
        } catch (Throwable $e) {
            $this->output->error($e->getMessage());
        }

        return self::SUCCESS;
    }
}
