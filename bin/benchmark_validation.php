#!/usr/bin/env php
<?php

/**
 * Copyright © 2003-2026 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

/**
 * Validation Benchmark Script
 * 
 * This script benchmarks the validation performance of Galette entities
 * to establish baseline metrics and compare different validation approaches.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

if (!isset($basepath)) {
    if (file_exists('../galette/index.php')) {
        $basepath = '../galette/';
    } elseif (file_exists('galette/index.php')) {
        $basepath = 'galette/';
    } else {
        die('Unable to define GALETTE_BASE_PATH :\'(');
    }
}

define('GALETTE_ENV', 'CLI');
define('GALETTE_ROOT', realpath(__DIR__ . '/../galette/') . '/');

require_once $basepath . 'vendor/autoload.php';
require_once $basepath . 'includes/galette.inc.php';

use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\Preferences;
use Galette\Core\History;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\SavedSearch;
use Galette\Entity\FieldsConfig;
use Analog\Analog;

/**
 * Benchmark configuration
 */
$iterations = [
    'quick' => 100,
    'normal' => 1000,
    'intensive' => 10000
];

$mode = $argv[1] ?? 'normal';
if (!isset($iterations[$mode])) {
    echo "Usage: php benchmark_validation.php [quick|normal|intensive]\n";
    echo "  quick: 100 iterations (fast test)\n";
    echo "  normal: 1000 iterations (default)\n";
    echo "  intensive: 10000 iterations (thorough test)\n";
    exit(1);
}

$count = $iterations[$mode];

echo "\n";
echo "======================================\n";
echo "  Galette Validation Benchmark\n";
echo "======================================\n";
echo "Mode: $mode ($count iterations)\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "======================================\n\n";

/**
 * Initialize Galette environment
 */
global $zdb, $preferences, $login, $hist, $i18n, $container, $members_fields;

if (!isset($container)) {
    die("Container not available. Please ensure Galette is properly installed.\n");
}

$zdb = $container->get(Db::class);
$preferences = $container->get(Preferences::class);
$login = $container->get(Login::class);
$hist = $container->get(History::class);
$i18n = $container->get(\Galette\Core\I18n::class);
$members_fields = $container->get('members_fields');

/**
 * Benchmark helper functions
 */
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

function benchmarkEntity(string $name, callable $setup, callable $validate, int $count): array
{
    echo "Benchmarking $name validation...\n";
    
    // Setup
    $entity = $setup();
    
    // Warm-up (1 iteration to initialize caches)
    $validate($entity);
    
    // Clear memory before benchmark
    gc_collect_cycles();
    $memory_start = memory_get_usage(true);
    $peak_start = memory_get_peak_usage(true);
    
    // Benchmark
    $time_start = microtime(true);
    for ($i = 0; $i < $count; $i++) {
        $entity = $setup();
        $validate($entity);
    }
    $time_end = microtime(true);
    
    // Memory after benchmark
    $memory_end = memory_get_usage(true);
    $peak_end = memory_get_peak_usage(true);
    
    $total_time = $time_end - $time_start;
    $avg_time = $total_time / $count;
    $memory_used = $memory_end - $memory_start;
    $peak_memory = $peak_end - $peak_start;
    
    echo "  ✓ Completed in " . number_format($total_time, 4) . "s\n";
    echo "  ✓ Average: " . number_format($avg_time * 1000, 4) . "ms per validation\n";
    echo "  ✓ Memory used: " . formatBytes($memory_used) . "\n";
    echo "  ✓ Peak memory: " . formatBytes($peak_memory) . "\n\n";
    
    return [
        'name' => $name,
        'iterations' => $count,
        'total_time' => $total_time,
        'avg_time' => $avg_time,
        'avg_time_ms' => $avg_time * 1000,
        'memory_used' => $memory_used,
        'peak_memory' => $peak_memory,
        'throughput' => $count / $total_time
    ];
}

/**
 * Test data generators
 */
function getSavedSearchData(): array
{
    return [
        'name' => 'Test Search ' . uniqid(),
        'form' => 'Adherent',
        'parameters' => [
            'name' => 'test',
            'surname' => 'user'
        ]
    ];
}

function getContributionData(): array
{
    global $zdb;
    
    // Get a valid member ID
    $select = $zdb->select(\Galette\Entity\Adherent::TABLE);
    $select->columns([\Galette\Entity\Adherent::PK])->limit(1);
    $results = $zdb->execute($select);
    $member = $results->current();
    $member_id = $member ? $member->id_adh : 1;
    
    return [
        'id_type_cotis' => 1,
        'id_adh' => $member_id,
        'date_enreg' => date('Y-m-d'),
        'date_debut_cotis' => date('Y-m-d'),
        'date_fin_cotis' => date('Y-m-d', strtotime('+1 year')),
        'montant_cotis' => '50.00',
        'type_paiement_cotis' => 1,
        'info_cotis' => 'Test contribution'
    ];
}

function getAdherentData(): array
{
    $unique = uniqid();
    return [
        'nom_adh' => 'TestName',
        'prenom_adh' => 'TestSurname',
        'email_adh' => 'test' . $unique . '@example.com',
        'login_adh' => 'testuser' . $unique,
        'mdp_adh' => 'TestP@ssw0rd123',
        'mdp_adh2' => 'TestP@ssw0rd123',
        'bool_admin_adh' => false,
        'bool_exempt_adh' => false,
        'bool_display_info' => true,
        'activite_adh' => true,
        'id_statut' => 9,
        'date_crea_adh' => date('Y-m-d'),
        'pref_lang' => 'en_US',
        'sexe_adh' => 0
    ];
}

/**
 * Run benchmarks
 */
$results = [];

// 1. SavedSearch validation
$results[] = benchmarkEntity(
    'SavedSearch',
    function () use ($zdb, $login) {
        return new SavedSearch($zdb, $login);
    },
    function ($entity) {
        $data = getSavedSearchData();
        $entity->check($data);
    },
    $count
);

// 2. Contribution validation
$results[] = benchmarkEntity(
    'Contribution',
    function () use ($zdb, $login) {
        return new Contribution($zdb, $login);
    },
    function ($entity) {
        $data = getContributionData();
        $required = [
            'id_type_cotis' => 1,
            'id_adh' => 1,
            'date_enreg' => 1,
            'date_debut_cotis' => 1,
            'montant_cotis' => 1
        ];
        $disabled = [];
        $entity->check($data, $required, $disabled);
    },
    $count
);

// 3. Adherent validation
$results[] = benchmarkEntity(
    'Adherent',
    function () use ($zdb, $preferences, $members_fields, $hist) {
        $adh = new Adherent($zdb);
        $adh->setDependencies($preferences, $members_fields, $hist);
        return $adh;
    },
    function ($entity) use ($members_fields) {
        $data = getAdherentData();
        $fc = FieldsConfig::loadAll();
        $required = [];
        $disabled = [];
        foreach ($members_fields as $key => $field) {
            if ($fc[$key]->required) {
                $required[$key] = 1;
            }
        }
        $entity->check($data, $required, $disabled);
    },
    $count
);

/**
 * Display summary
 */
echo "======================================\n";
echo "  Summary\n";
echo "======================================\n\n";

$total_time = 0;
$total_memory = 0;

foreach ($results as $result) {
    echo "{$result['name']}:\n";
    echo "  Throughput: " . number_format($result['throughput'], 2) . " validations/sec\n";
    echo "  Avg time: " . number_format($result['avg_time_ms'], 4) . " ms\n";
    echo "  Memory: " . formatBytes($result['peak_memory']) . "\n\n";
    
    $total_time += $result['total_time'];
    $total_memory += $result['peak_memory'];
}

echo "Total execution time: " . number_format($total_time, 2) . "s\n";
echo "Total peak memory: " . formatBytes($total_memory) . "\n";

/**
 * Save results to JSON
 */
$output_file = __DIR__ . '/../tests/benchmark_results.json';
$output_data = [
    'date' => date('Y-m-d H:i:s'),
    'mode' => $mode,
    'iterations' => $count,
    'php_version' => PHP_VERSION,
    'memory_limit' => ini_get('memory_limit'),
    'results' => $results,
    'total_time' => $total_time,
    'total_memory' => $total_memory
];

// Load existing results if any
$all_results = [];
if (file_exists($output_file)) {
    $existing = file_get_contents($output_file);
    $all_results = json_decode($existing, true) ?? [];
}

$all_results[] = $output_data;

file_put_contents($output_file, json_encode($all_results, JSON_PRETTY_PRINT));
echo "\nResults saved to: $output_file\n";

echo "\n✓ Benchmark completed successfully!\n\n";

