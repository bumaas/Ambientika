<?php

declare(strict_types=1);

/**
 * Ist die Cloud-Instanz nicht aktiv, spiegelt das Gerät das im eigenen Status (inaktiv), statt einen
 * alten Status stehen zu lassen. Sonst zeigt eine Instanz nach dem Eintragen der Seriennummer weiter
 * „Seriennummer nicht gesetzt“, oder ein verstummtes Gerät weiter 203, obwohl die Cloud selbst ausfällt.
 * Wird die Cloud wieder aktiv, holt die nächste Abfrage den Status zurück.
 *
 * Aufruf: php tests/check-parent-inactive.php
 */

use Ambientika\Device\InstanceStatus;

require_once __DIR__ . '/harness.php';

// Seriennummer eintragen, während die Cloud-Instanz inaktiv ist
$m = neueInstanz();
pruefe(end($m->status) === InstanceStatus::SerialNumberNotSet, 'Ausgangslage: Seriennummer fehlt (201)');
$m->statusAntwort = file_get_contents(__DIR__ . '/fixtures/device-status/smart-low-master.json');
$m->parentAktiv   = false;
IPS_SetProperty($m->instanzId(), 'SerialNumber', '000000000001');
IPS_ApplyChanges($m->instanzId());
pruefe(end($m->status) === IS_INACTIVE, 'nach dem Übernehmen inaktiv statt 201 → ' . end($m->status));
pruefe($m->timer['RefreshState'] === 10000, 'Abfrage-Timer läuft weiter');

// Cloud wieder aktiv: nächste Abfrage
$m->parentAktiv = true;
pruefe($m->RequestState() === true && end($m->status) === IS_ACTIVE, 'Cloud wieder aktiv: Instanz aktiv');

// Gerät verstummt (203), danach fällt die Cloud-Instanz aus
$m->statusHttp    = 404;
$m->statusAntwort = file_get_contents(__DIR__ . '/fixtures/device-status/not-reporting-404.txt');
$m->RequestState();
pruefe(end($m->status) === InstanceStatus::DeviceNotReporting, 'Ausgangslage: Gerät meldet sich nicht (203)');
$m->parentAktiv = false;
$m->logsZuruecksetzen();
pruefe($m->RequestState() === false && end($m->status) === IS_INACTIVE, 'Cloud fällt aus: inaktiv statt 203 → ' . end($m->status));
$anzahl = count($m->status);
$m->RequestState();
pruefe(count($m->logsSeitMarke()) <= 1 && count($m->status) === $anzahl, 'weitere Abfragen ohne Cloud setzen den Status nicht erneut');

ergebnis();
