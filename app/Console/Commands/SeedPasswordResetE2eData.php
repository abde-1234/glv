<?php

namespace App\Console\Commands;

use App\Models\Agence;
use App\Models\PasswordResetRequest;
use App\Models\PasswordResetRequestEvent;
use App\Models\User;
use App\Notifications\GlvNotification;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedPasswordResetE2eData extends Command
{
    protected $signature = 'glv:e2e-password-reset-data';

    protected $description = 'Crée le jeu de données E2E idempotent des demandes de réinitialisation';

    private const REJECTION_REASON = 'Veuillez contacter le Super Admin afin de confirmer votre identité avant la réinitialisation de l’accès.';

    /** @var array<string, string> */
    private const AGENCY_ADMINS = [
        'Atlas Mobility' => 'admin@atlas-mobility.test',
        'Rif Drive' => 'admin@rif-drive.test',
        'Palm Cars' => 'admin@palm-cars.test',
    ];

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('Cette commande est réservée aux environnements local/test.');

            return self::FAILURE;
        }

        $superAdmin = User::query()->where('email', 'admin@glv.test')->first();
        $agencies = [];
        $admins = [];

        foreach (self::AGENCY_ADMINS as $agencyName => $email) {
            $agency = Agence::query()->where('nom', $agencyName)->first();
            $admin = User::query()->where('email', $email)->first();

            if ($agency !== null) {
                $agencies[$agencyName] = $agency;
            }
            if ($admin !== null) {
                $admins[$email] = $admin;
            }
        }

        $this->line('Agences trouvées : '.count($agencies));
        $this->line('Admins trouvés : '.count($admins));

        if ($superAdmin === null
            || $superAdmin->role !== User::ROLE_SUPER_ADMIN
            || $superAdmin->agence_id !== null
            || count($agencies) !== count(self::AGENCY_ADMINS)
            || count($admins) !== count(self::AGENCY_ADMINS)) {
            $this->error('Dataset E2E incomplet : les trois agences, leurs administrateurs et admin@glv.test sont requis.');

            return self::FAILURE;
        }

        foreach (self::AGENCY_ADMINS as $agencyName => $email) {
            $admin = $admins[$email];
            if ($admin->role !== User::ROLE_ADMIN_AGENCE || $admin->agence_id !== $agencies[$agencyName]->id) {
                $this->error("Association invalide pour {$email} et {$agencyName}.");

                return self::FAILURE;
            }
        }

        $requestsCreated = 0;
        $requestsIgnored = 0;
        $notificationsCreated = 0;
        $notificationsIgnored = 0;
        $now = now();

        DB::transaction(function () use (
            $superAdmin,
            $agencies,
            $admins,
            $now,
            &$requestsCreated,
            &$requestsIgnored,
            &$notificationsCreated,
            &$notificationsIgnored,
        ): void {
            $atlasAdmin = $admins['admin@atlas-mobility.test'];
            $rifAdmin = $admins['admin@rif-drive.test'];
            $palmAdmin = $admins['admin@palm-cars.test'];

            $pending = $this->createRequest(
                $agencies['Atlas Mobility'],
                $atlasAdmin,
                PasswordResetRequest::STATUS_PENDING,
                $now->copy()->subMinutes(10),
                null,
                $now->copy()->addDay(),
                null,
                null,
            );
            $this->countCreation($pending, $requestsCreated, $requestsIgnored);

            $approved = $this->createRequest(
                $agencies['Rif Drive'],
                $rifAdmin,
                PasswordResetRequest::STATUS_APPROVED,
                $now->copy()->subHours(2),
                $superAdmin,
                $now->copy()->addHour(),
                $now->copy()->subMinutes(110),
                null,
            );
            $this->countCreation($approved, $requestsCreated, $requestsIgnored);

            $rejected = $this->createRequest(
                $agencies['Palm Cars'],
                $palmAdmin,
                PasswordResetRequest::STATUS_REJECTED,
                $now->copy()->subDay(),
                $superAdmin,
                $now->copy()->subMinutes(15),
                $now->copy()->subDay()->addMinutes(15),
                self::REJECTION_REASON,
            );
            $this->countCreation($rejected, $requestsCreated, $requestsIgnored);

            $expired = $this->createRequest(
                $agencies['Atlas Mobility'],
                $atlasAdmin,
                PasswordResetRequest::STATUS_EXPIRED,
                $now->copy()->subDays(10),
                null,
                $now->copy()->subDays(9),
                $now->copy()->subDays(9),
                null,
            );
            $this->countCreation($expired, $requestsCreated, $requestsIgnored);

            $completed = $this->createRequest(
                $agencies['Rif Drive'],
                $rifAdmin,
                PasswordResetRequest::STATUS_COMPLETED,
                $now->copy()->subDays(3),
                $superAdmin,
                $now->copy()->subDays(3)->addMinutes(60),
                $now->copy()->subDays(3)->addMinutes(30),
                null,
            );
            $this->countCreation($completed, $requestsCreated, $requestsIgnored);

            collect([$pending, $approved, $rejected, $expired, $completed])
                ->unique('id')
                ->each(fn (PasswordResetRequest $resetRequest) => $this->ensureLifecycleEvents($resetRequest, $superAdmin));

            $this->createNotification($superAdmin, 'atlas-pending', [
                'type' => 'password_reset_request',
                'title' => 'Nouvelle demande de réinitialisation',
                'message' => 'admin@atlas-mobility.test demande la réinitialisation de son accès.',
                'url' => route('super-admin.password-resets.show', $pending),
                'password_reset_request_id' => $pending->id,
            ], $notificationsCreated, $notificationsIgnored);

            $this->createNotification($rifAdmin, 'rif-completed', [
                'type' => 'password_reset_completed',
                'title' => 'Demande de réinitialisation traitée',
                'message' => 'Votre demande de réinitialisation a été traitée.',
                'url' => route('login'),
                'password_reset_request_id' => $completed->id,
            ], $notificationsCreated, $notificationsIgnored);

            $this->createNotification($palmAdmin, 'palm-rejected', [
                'type' => 'password_reset_rejected',
                'title' => 'Demande de réinitialisation refusée',
                'message' => self::REJECTION_REASON,
                'url' => route('login'),
                'password_reset_request_id' => $rejected->id,
            ], $notificationsCreated, $notificationsIgnored);
        });

        $orphans = $this->orphanCount();
        $duplicates = $this->duplicatePendingCount();

        $this->newLine();
        $this->info('Demandes créées : '.$requestsCreated);
        $this->line('Demandes existantes ignorées : '.$requestsIgnored);
        $this->info('Notifications créées : '.$notificationsCreated);
        $this->line('Notifications existantes ignorées : '.$notificationsIgnored);
        $this->line('Doublons évités : '.($requestsIgnored + $notificationsIgnored));
        $this->newLine();
        $this->line('Demandes reset total : '.PasswordResetRequest::query()->count());
        $this->line('Pending : '.PasswordResetRequest::query()->where('status', PasswordResetRequest::STATUS_PENDING)->count());
        $this->line('Approved/Completed : '.PasswordResetRequest::query()->whereIn('status', [PasswordResetRequest::STATUS_APPROVED, PasswordResetRequest::STATUS_COMPLETED])->count());
        $this->line('Rejected : '.PasswordResetRequest::query()->where('status', PasswordResetRequest::STATUS_REJECTED)->count());
        $this->line('Expired : '.PasswordResetRequest::query()->where('status', PasswordResetRequest::STATUS_EXPIRED)->count());
        $this->line('Orphelines : '.$orphans);
        $this->line('Doublons : '.$duplicates);

        return $orphans === 0 && $duplicates === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function createRequest(
        Agence $agency,
        User $admin,
        string $status,
        CarbonInterface $requestedAt,
        ?User $processor,
        ?CarbonInterface $expiresAt,
        ?CarbonInterface $processedAt,
        ?string $rejectionReason,
    ): PasswordResetRequest {
        if (in_array($status, [PasswordResetRequest::STATUS_PENDING, PasswordResetRequest::STATUS_APPROVED], true)) {
            $activeRequest = PasswordResetRequest::query()
                ->where('user_id', $admin->id)
                ->active()
                ->first();

            if ($activeRequest !== null) {
                return $activeRequest;
            }
        }

        $request = PasswordResetRequest::query()->firstOrCreate(
            ['agence_id' => $agency->id, 'user_id' => $admin->id, 'status' => $status],
            [
                'processed_by' => $processor?->id,
                'pending_key' => in_array($status, [PasswordResetRequest::STATUS_PENDING, PasswordResetRequest::STATUS_APPROVED], true) ? $admin->id : null,
                'requested_at' => $requestedAt,
                'processed_at' => $processedAt,
                'expires_at' => $expiresAt,
                'rejection_reason' => $rejectionReason,
            ],
        );

        return $request;
    }

    private function ensureLifecycleEvents(PasswordResetRequest $request, User $superAdmin): void
    {
        $this->ensureEvent($request, PasswordResetRequestEvent::TYPE_CREATED, $request->user, $request->requested_at);

        if (in_array($request->status, [PasswordResetRequest::STATUS_APPROVED, PasswordResetRequest::STATUS_COMPLETED], true)) {
            $this->ensureEvent($request, PasswordResetRequestEvent::TYPE_APPROVED, $request->processor ?? $superAdmin, $request->processed_at);
        }

        if ($request->status === PasswordResetRequest::STATUS_REJECTED) {
            $this->ensureEvent($request, PasswordResetRequestEvent::TYPE_REJECTED, $request->processor ?? $superAdmin, $request->processed_at, [
                'reason' => $request->rejection_reason,
            ]);
        }

        if ($request->status === PasswordResetRequest::STATUS_EXPIRED) {
            $this->ensureEvent($request, PasswordResetRequestEvent::TYPE_EXPIRED, null, $request->processed_at);
        }

        if ($request->status === PasswordResetRequest::STATUS_COMPLETED) {
            $this->ensureEvent($request, PasswordResetRequestEvent::TYPE_PASSWORD_CHANGED, $request->user, $request->processed_at);
        }
    }

    private function countCreation(PasswordResetRequest $request, int &$created, int &$ignored): void
    {
        $request->wasRecentlyCreated ? $created++ : $ignored++;
    }

    /** @param array<string, mixed>|null $metadata */
    private function ensureEvent(
        PasswordResetRequest $request,
        string $type,
        ?User $actor,
        ?CarbonInterface $createdAt,
        ?array $metadata = null,
    ): void {
        if ($request->events()->where('event_type', $type)->exists()) {
            return;
        }

        $event = new PasswordResetRequestEvent;
        $event->forceFill([
            'event_type' => $type,
            'actor_id' => $actor?->id,
            'metadata' => $metadata,
            'created_at' => $createdAt ?? now(),
        ]);
        $request->events()->save($event);
    }

    /** @param array<string, mixed> $payload */
    private function createNotification(User $recipient, string $key, array $payload, int &$created, int &$ignored): void
    {
        $notificationId = $this->notificationId($key, $recipient->id);
        if ($recipient->notifications()->whereKey($notificationId)->exists()) {
            $ignored++;

            return;
        }

        $notification = new GlvNotification([...$payload, 'e2e_key' => $key]);
        $notification->id = $notificationId;
        $recipient->notify($notification);
        $created++;
    }

    private function notificationId(string $key, int $recipientId): string
    {
        $hex = md5("glv:e2e-password-reset:{$key}:{$recipientId}");

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }

    private function orphanCount(): int
    {
        return DB::table('password_reset_requests as requests')
            ->leftJoin('agences', 'agences.id', '=', 'requests.agence_id')
            ->leftJoin('users as applicants', 'applicants.id', '=', 'requests.user_id')
            ->leftJoin('users as processors', 'processors.id', '=', 'requests.processed_by')
            ->where(function ($query): void {
                $query->whereNull('agences.id')
                    ->orWhereNull('applicants.id')
                    ->orWhere(function ($query): void {
                        $query->whereNotNull('requests.processed_by')->whereNull('processors.id');
                    });
            })
            ->count();
    }

    private function duplicatePendingCount(): int
    {
        return DB::query()
            ->fromSub(
                PasswordResetRequest::query()
                    ->select('user_id')
                    ->where('status', PasswordResetRequest::STATUS_PENDING)
                    ->groupBy('user_id')
                    ->havingRaw('COUNT(*) > 1'),
                'duplicates',
            )
            ->count();
    }
}
