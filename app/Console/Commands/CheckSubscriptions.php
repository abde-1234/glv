<?php

namespace App\Console\Commands;

use App\Services\SubscriptionNotificationService;
use Illuminate\Console\Command;

class CheckSubscriptions extends Command
{
    protected $signature = 'subscriptions:check';
    protected $description = 'Crée les notifications d’expiration d’abonnement sans doublon';

    public function handle(SubscriptionNotificationService $service): int
    {
        $count = $service->checkAll();
        $this->info("{$count} alerte(s) créée(s).");

        return self::SUCCESS;
    }
}
