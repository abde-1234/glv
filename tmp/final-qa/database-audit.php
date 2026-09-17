<?php

// Audit local en lecture seule. Ne lance ni migration ni envoi de notification.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tables = ['agences', 'users', 'voitures', 'clients', 'reservations', 'contrats', 'agence_documents', 'notifications', 'renewal_requests', 'subscription_notification_events'];
$result = [];
foreach ($tables as $table) {
    $result['tables'][$table] = [
        'rows' => DB::table($table)->count(),
        'foreign_keys' => Schema::getForeignKeys($table),
        'indexes' => array_map(fn ($index) => ['name' => $index['name'], 'columns' => $index['columns'], 'unique' => $index['unique']], Schema::getIndexes($table)),
    ];
}
$orphans = [];
foreach (['users', 'voitures', 'clients', 'reservations', 'contrats', 'agence_documents', 'renewal_requests', 'subscription_notification_events'] as $table) {
    $orphans[$table.'_agency'] = DB::table($table.' as t')->leftJoin('agences as a', 'a.id', '=', 't.agence_id')->whereNotNull('t.agence_id')->whereNull('a.id')->count();
}
foreach (['reservations', 'contrats'] as $table) {
    foreach (['client_id' => 'clients', 'voiture_id' => 'voitures'] as $key => $parent) {
        $orphans[$table.'_'.$key] = DB::table($table.' as t')->leftJoin($parent.' as p', 'p.id', '=', 't.'.$key)->whereNull('p.id')->count();
        $result['cross_agency'][$table.'_'.$key] = DB::table($table.' as t')->join($parent.' as p', 'p.id', '=', 't.'.$key)->whereColumn('t.agence_id', '!=', 'p.agence_id')->count();
    }
}
$orphans['contract_reservation'] = DB::table('contrats as t')->leftJoin('reservations as p', 'p.id', '=', 't.reservation_id')->whereNotNull('t.reservation_id')->whereNull('p.id')->count();
$result['cross_agency']['contract_reservation'] = DB::table('contrats as t')->join('reservations as p', 'p.id', '=', 't.reservation_id')->where(fn ($q) => $q->whereColumn('t.agence_id', '!=', 'p.agence_id')->orWhereColumn('t.client_id', '!=', 'p.client_id')->orWhereColumn('t.voiture_id', '!=', 'p.voiture_id'))->count();
foreach (['requested_by', 'processed_by'] as $key) {
    $orphans['renewal_'.$key] = DB::table('renewal_requests as t')->leftJoin('users as u', 'u.id', '=', 't.'.$key)->whereNotNull('t.'.$key)->whereNull('u.id')->count();
}
$result['cross_agency']['renewal_requester'] = DB::table('renewal_requests as t')->join('users as u', 'u.id', '=', 't.requested_by')->whereColumn('t.agence_id', '!=', 'u.agence_id')->count();
$orphans['notifications_user'] = DB::table('notifications as t')->leftJoin('users as u', 'u.id', '=', 't.notifiable_id')->where('t.notifiable_type', App\Models\User::class)->whereNull('u.id')->count();
$result['orphans'] = $orphans;
$result['duplicates']['pending'] = DB::table('renewal_requests')->where('status', 'pending')->select('agence_id')->groupBy('agence_id')->havingRaw('COUNT(*) > 1')->get()->count();
$result['duplicates']['events'] = DB::table('subscription_notification_events')->select('agence_id', 'event_type', 'expiration_date')->groupBy('agence_id', 'event_type', 'expiration_date')->havingRaw('COUNT(*) > 1')->get()->count();
$result['duplicates']['contracts_per_reservation'] = DB::table('contrats')->whereNotNull('reservation_id')->select('reservation_id')->groupBy('reservation_id')->havingRaw('COUNT(*) > 1')->get()->count();
$result['pending_key_mismatch'] = DB::table('renewal_requests')->where(fn ($q) => $q->where('status', 'pending')->where(fn ($q) => $q->whereNull('pending_key')->orWhereColumn('pending_key', '!=', 'agence_id')))->count();
$result['agencies'] = DB::table('agences')->select('id', 'nom', 'statut', 'type_abonnement', 'date_expiration')->get();
$result['test_like_rows'] = [
    'agency_names' => DB::table('agences')->where(fn ($q) => $q->where('nom', 'like', '%test%')->orWhere('nom', 'like', '%QA%')->orWhere('nom', 'like', '%E2E%'))->pluck('nom'),
    'users_dot_test' => DB::table('users')->where('email', 'like', '%.test')->count(),
];
$result['business_images'] = ['agency_logos' => DB::table('agences')->whereNotNull('logo')->count(), 'car_photos' => DB::table('voitures')->whereNotNull('photo')->count()];
$result['db_account_is_root'] = config('database.connections.'.config('database.default').'.username') === 'root';
$result['db_password_present'] = filled(config('database.connections.'.config('database.default').'.password'));
$result['foreign_key_checks'] = DB::selectOne('SELECT @@foreign_key_checks AS enabled')->enabled;
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
