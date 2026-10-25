<?php

declare(strict_types=1);

/**
 * Schalter und Aufzählung verlangen laut Kernel-Definition eine Variable mit Aktion
 * (IPS_GetPresentation: restrictions.requestAction = true). Reine Statuswerte tragen deshalb die
 * Wertanzeige, mit Beschriftungen für jeden Wert (Code-Review 25.10.2026, Befund 3).
 *
 * Aufruf: php tests/check-presentations.php
 */

use Ambientika\Device\VariableValues;

require_once __DIR__ . '/harness.php';

$m = geraetMitStatus('smart-low-master');

$bedienbar = [VARIABLE_PRESENTATION_SWITCH, VARIABLE_PRESENTATION_ENUMERATION];
$falsch    = [];
$darstellungen = [];
foreach (IPS_GetChildrenIDs($m->instanzId()) as $vid) {
    $var   = IPS_GetVariable($vid);
    $ident = IPS_GetObject($vid)['ObjectIdent'];
    $darstellungen[$ident] = $var['VariablePresentation'];
    if ($var['VariableAction'] === 0 && in_array($var['VariablePresentation']['PRESENTATION'] ?? '', $bedienbar, true)) {
        $falsch[] = $ident;
    }
}
pruefe($falsch === [], 'keine Statusvariable ohne Aktion trägt Schalter oder Aufzählung → ' . implode(', ', $falsch));

/** Beschriftete Werte einer Wertanzeige: Boolean über OPTIONS, Zahlen über INTERVALS mit Konstante */
function beschriftet(array $darstellung): array
{
    if (($darstellung['PRESENTATION'] ?? '') !== VARIABLE_PRESENTATION_VALUE_PRESENTATION) {
        return [];
    }
    if (isset($darstellung['OPTIONS'])) {
        return array_column(json_decode($darstellung['OPTIONS'], true, 512, JSON_THROW_ON_ERROR), 'Value');
    }
    if (($darstellung['INTERVALS_ACTIVE'] ?? false) !== true) {
        return [];
    }
    $werte = [];
    foreach (json_decode($darstellung['INTERVALS'], true, 512, JSON_THROW_ON_ERROR) as $intervall) {
        if ($intervall['ConstantActive'] && $intervall['IntervalMinValue'] === $intervall['IntervalMaxValue'] && $intervall['ConstantValue'] !== '') {
            $werte[] = $intervall['IntervalMinValue'];
        }
    }
    return $werte;
}

foreach (['humidityAlarm' => [false, true], 'nightAlarm' => [false, true]] as $ident => $erwartet) {
    pruefe(beschriftet($darstellungen[$ident]) === $erwartet, "$ident zeigt Aus und Ein → " . json_encode(beschriftet($darstellungen[$ident])));
}
foreach (
    [
        'airQuality'        => VariableValues::AirQuality,
        'filtersStatus'     => VariableValues::FiltersStatus,
        'deviceRole'        => VariableValues::DeviceRole,
        'lastOperatingMode' => VariableValues::OperatingMode,
    ] as $ident => $tabelle
) {
    pruefe(beschriftet($darstellungen[$ident]) === array_values($tabelle), "$ident beschriftet jeden Wert → " . json_encode(beschriftet($darstellungen[$ident])));
}

// Wertebereiche der früheren Profile (Ambientika.Temperature, ~Humidity, Ambientika.SignalStrenght) für
// Diagramm und Anzeige (Code-Review 25.10.2026, Befund 4)
foreach (['temperature' => [-15, 50, ' °C'], 'humidity' => [0, 100, ' %'], 'signalStrenght' => [-15, 250, '']] as $ident => [$min, $max, $suffix]) {
    $d = $darstellungen[$ident];
    pruefe(
        ($d['MIN'] ?? null) === $min && ($d['MAX'] ?? null) === $max && ($d['SUFFIX'] ?? '') === $suffix,
        "$ident: Bereich $min..$max → " . json_encode(['MIN' => $d['MIN'] ?? null, 'MAX' => $d['MAX'] ?? null, 'SUFFIX' => $d['SUFFIX'] ?? null], JSON_UNESCAPED_UNICODE)
    );
}

// Bedienbare Variablen behalten ihre Bedienelemente
foreach (['powerSwitch', 'operatingMode', 'fanSpeed', 'humidityLevel', 'lightSensorLevel', 'filterReset'] as $ident) {
    pruefe(in_array($darstellungen[$ident]['PRESENTATION'] ?? '', $bedienbar, true), "$ident bleibt bedienbar");
}

ergebnis();
