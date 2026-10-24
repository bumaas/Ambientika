<?php

declare(strict_types=1);

/**
 * Jeder Schaltbefehl an /device/change-mode trägt Modus, Lüfterstufe, Feuchtegrenze und Lichtsensor
 * zusammen. Fehlt ein Feld, setzt die Cloud es zurück: Wer die Lüfterstufe ändert, landet sonst im
 * Modus „Smart“ (Forum, chrisH 24.10.2026). Ausgangszustand sind die echten Statusmitschnitte.
 *
 * Aufruf: php tests/check-change-mode.php
 */

use Ambientika\Cloud\ApiUrl;
use Ambientika\Device\VariableValues;

require_once __DIR__ . '/harness.php';

/** Letzter change-mode-Befehl ohne Seriennummer, oder null, wenn keiner ging */
function letzterBefehl(AmbientikaDeviceHarness $m): ?array
{
    foreach (array_reverse($m->gesendet) as $anfrage) {
        if ($anfrage['uri'] === ApiUrl::ChangeMode) {
            unset($anfrage['params']['deviceSerialNumber']);
            return $anfrage['params'];
        }
    }
    return null;
}

function pruefeBefehl(AmbientikaDeviceHarness $m, array $erwartet, string $text): void
{
    $befehl = letzterBefehl($m);
    if ($befehl !== null) {
        ksort($befehl);
    }
    ksort($erwartet);
    pruefe($befehl === $erwartet, $text . ' → ' . json_encode($befehl, JSON_UNESCAPED_UNICODE));
}

// Badezimmer: Master, AwayHome, Low, Normal, kein Lichtsensor
$fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/device-status/awayhome-master.json'), true, 512, JSON_THROW_ON_ERROR);
$stand = [
    'operatingMode'    => $fixture['operatingMode'],
    'fanSpeed'         => $fixture['fanSpeed'],
    'humidityLevel'    => $fixture['humidityLevel'],
    'lightSensorLevel' => $fixture['lightSensorLevel'],
];

$m = geraetMitStatus('awayhome-master');

$m->RequestAction('fanSpeed', VariableValues::FanSpeed['High']);
pruefeBefehl($m, ['fanSpeed' => 'High'] + $stand, 'Lüfterstufe hoch behält den Modus');

$m->RequestAction('operatingMode', VariableValues::OperatingMode['ManualHeatRecovery']);
pruefeBefehl($m, ['operatingMode' => 'ManualHeatRecovery'] + $stand, 'Wärmerückgewinnung behält die Lüfterstufe');

$m->RequestAction('humidityLevel', VariableValues::HumidityLevel['Moist']);
pruefeBefehl($m, ['humidityLevel' => 'Moist'] + $stand, 'Feuchtegrenze behält Modus und Stufe');

$m->RequestAction('powerSwitch', false);
pruefeBefehl($m, ['operatingMode' => 'Off'] + $stand, 'Ausschalten behält die Lüfterstufe');

$m->RequestAction('powerSwitch', true);
pruefeBefehl($m, ['operatingMode' => $fixture['lastOperatingMode']] + $stand, 'Einschalten nimmt den letzten Modus und behält die Stufe');

pruefe(count(array_filter($m->gesendet, static fn($a) => $a['uri'] === ApiUrl::ChangeMode)) === 5, 'genau ein Befehl je Schaltvorgang');

ergebnis();
