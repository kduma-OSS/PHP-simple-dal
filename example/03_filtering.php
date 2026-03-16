<?php

/**
 * Filtering and searching records.
 *
 * The Filter builder provides a fluent API for building queries
 * that work identically across all adapters.
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Contracts\Query\SortDirection;
use KDuma\SimpleDAL\DataStore;
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;
use KDuma\SimpleDAL\Query\Filter;

$store = new DataStore(
    adapter: new DatabaseAdapter(new PDO('sqlite::memory:')),
    entities: [
        new CollectionEntityDefinition(
            name: 'certificates',
            indexedFields: ['status', 'subject.commonName'],
        ),
    ],
);

$certs = $store->collection('certificates');

// ── Seed some data ──

$certs->create(['subject' => ['commonName' => 'example.com'],    'status' => 'active',  'not_after' => '2027-01-01', 'priority' => 1], id: 'cert-01');
$certs->create(['subject' => ['commonName' => 'test.com'],       'status' => 'active',  'not_after' => '2026-06-01', 'priority' => 3], id: 'cert-02');
$certs->create(['subject' => ['commonName' => 'staging.com'],    'status' => 'revoked', 'not_after' => '2025-12-01', 'priority' => 2], id: 'cert-03');
$certs->create(['subject' => ['commonName' => 'internal.local'], 'status' => 'active',  'not_after' => '2026-09-01', 'priority' => 4], id: 'cert-04');
$certs->create(['subject' => ['commonName' => 'api.example.com'], 'status' => 'expired', 'not_after' => '2025-01-01', 'priority' => 5], id: 'cert-05');

// ── Simple equality filter ──

echo "Active certificates:\n";
$active = $certs->filter(Filter::where('status', '=', 'active'));
foreach ($active as $r) {
    echo "  {$r->id}: {$r->get('subject.commonName')}\n";
}

// ── Nested field filter (dot notation) ──

echo "\nCertificates for example.com:\n";
$results = $certs->filter(Filter::where('subject.commonName', '=', 'example.com'));
foreach ($results as $r) {
    echo "  {$r->id}: {$r->get('subject.commonName')}\n";
}

// ── Comparison operators ──

echo "\nExpiring before 2026-07-01:\n";
$expiring = $certs->filter(
    Filter::where('not_after', '<', '2026-07-01'),
);
foreach ($expiring as $r) {
    echo "  {$r->id}: expires {$r->get('not_after')}\n";
}

// ── String contains ──

echo "\nDomains containing 'example':\n";
$results = $certs->filter(
    Filter::where('subject.commonName', 'contains', 'example'),
);
foreach ($results as $r) {
    echo "  {$r->id}: {$r->get('subject.commonName')}\n";
}

// ── Multiple conditions (AND) ──

echo "\nActive + expiring before 2026-07-01:\n";
$results = $certs->filter(
    Filter::where('status', '=', 'active')
        ->andWhere('not_after', '<', '2026-07-01'),
);
foreach ($results as $r) {
    echo "  {$r->id}: {$r->get('subject.commonName')} (expires {$r->get('not_after')})\n";
}

// ── IN operator ──

echo "\nActive or expired:\n";
$results = $certs->filter(
    Filter::where('status', 'in', ['active', 'expired']),
);
foreach ($results as $r) {
    echo "  {$r->id}: {$r->get('status')}\n";
}

// ── Sorting ──

echo "\nAll certs sorted by priority (desc):\n";
$results = $certs->filter(
    (new Filter)->orderBy('priority', SortDirection::Desc),
);
foreach ($results as $r) {
    echo "  {$r->id}: priority={$r->get('priority')}\n";
}

// ── Limit + offset (pagination) ──

echo "\nPage 2 (2 per page, sorted by priority asc):\n";
$results = $certs->filter(
    (new Filter)->orderBy('priority', SortDirection::Asc)->limit(2)->offset(2),
);
foreach ($results as $r) {
    echo "  {$r->id}: priority={$r->get('priority')}\n";
}

// ── Count with filter ──

$activeCount = $certs->count(Filter::where('status', '=', 'active'));
echo "\nActive count: {$activeCount}\n";
echo "Total count: {$certs->count()}\n";

echo "\nDone.\n";
