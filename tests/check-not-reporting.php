<?php

declare(strict_types=1);

/**
 * Ein Gerät, das sich nicht mehr bei der Cloud meldet (HTTP 404 „Status packet not found!“), bekommt
 * einen eigenen Status mit Klartext im Log, statt wie ein Cloud- oder Login-Fehler auszusehen. Die
 * Meldung kommt einmal beim Wechsel, die Rückkehr wird gemeldet.
 *
 * Aufruf: php tests/check-not-reporting.php
 */

use Ambientika\Device\InstanceStatus;

require_once __DIR__ . '/harness.php';

$m = geraetMitStatus('smart-low-master');
$gut = $m->statusAntwort;
pruefe(end($m->status) === IS_ACTIVE, 'Ausgangslage: Gerät meldet, Instanz aktiv');

// Gerät verstummt
$m->statusHttp    = 404;
$m->statusAntwort = file_get_contents(__DIR__ . '/fixtures/device-status/not-reporting-404.txt');
$m->logsZuruecksetzen();
$ok = $m->RequestState();

pruefe($ok === false, 'RequestState meldet den Fehlschlag');
pruefe(end($m->status) === 203, 'eigener Status 203 statt 202');
pruefe(defined(InstanceStatus::class . '::DeviceNotReporting') && end($m->status) === constant(InstanceStatus::class . '::DeviceNotReporting'), 'Status ist als DeviceNotReporting benannt');
$logs = $m->logsSeitMarke();
pruefe(count($logs) === 1 && $logs[0]['Type'] === KL_WARNING, 'genau eine Warnung im Log → ' . json_encode(array_column($logs, 'Message'), JSON_UNESCAPED_UNICODE));
pruefe(isset($logs[0]) && str_contains($logs[0]['Message'], '000000000001'), 'Warnung nennt die Seriennummer');

// zweiter Fehlschlag: keine neue Meldung
$m->logsZuruecksetzen();
$m->RequestState();
pruefe(end($m->status) === 203 && $m->logsSeitMarke() === [], 'weiterer Fehlschlag bleibt still');

// Übernehmen oder Neuladen, während das Gerät schweigt: keine falsche Erfolgsmeldung
$m->logsZuruecksetzen();
IPS_ApplyChanges($m->instanzId());
pruefe(end($m->status) === 203 && $m->logsSeitMarke() === [], 'ApplyChanges meldet weder Rückkehr noch neue Warnung → ' . json_encode(array_column($m->logsSeitMarke(), 'Message'), JSON_UNESCAPED_UNICODE));

// Gerät meldet sich wieder
$m->statusHttp    = 200;
$m->statusAntwort = $gut;
$m->logsZuruecksetzen();
pruefe($m->RequestState() === true && end($m->status) === IS_ACTIVE, 'Rückkehr: Instanz wieder aktiv');
pruefe(count($m->logsSeitMarke()) === 1, 'Rückkehr wird einmal gemeldet');

// Formular kennt den Status
$form = json_decode(file_get_contents(dirname(__DIR__) . '/Ambientika Device/form.json'), true, 512, JSON_THROW_ON_ERROR);
pruefe(in_array(203, array_column($form['status'], 'code'), true), 'form.json beschreibt Status 203');

ergebnis();
