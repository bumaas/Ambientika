<?php

declare(strict_types=1);

/**
 * Jeder Mitschnitt aus tests/fixtures/device-status wird ohne Fehler eingelesen: Modus, Rolle und
 * Messwerte landen typgerecht in den Variablen, der Schalter folgt dem Modus.
 *
 * Aufruf: php tests/check-device-status.php
 */

use Ambientika\Device\VariableValues;

require_once __DIR__ . '/harness.php';

foreach (glob(__DIR__ . '/fixtures/device-status/*.json') as $datei) {
    $name   = basename($datei, '.json');
    $status = json_decode(file_get_contents($datei), true, 512, JSON_THROW_ON_ERROR);
    echo "== $name\n";

    $m = geraetMitStatus($name);
    $w = $m->werte();

    pruefe($w['deviceRole'] === VariableValues::DeviceRole[$status['deviceRole']], "$name: Rolle {$status['deviceRole']}");
    if ($status['deviceRole'] === 'Master') {
        pruefe($w['operatingMode'] === VariableValues::OperatingMode[$status['operatingMode']], "$name: Modus {$status['operatingMode']}");
        pruefe($w['lastOperatingMode'] === VariableValues::OperatingMode[$status['lastOperatingMode']], "$name: letzter Modus {$status['lastOperatingMode']}");
        pruefe($w['powerSwitch'] === ($status['operatingMode'] !== 'Off'), "$name: Schalter passt zum Modus");
    } else {
        // Slaves folgen ihrem Master und haben keine Bedienvariablen
        pruefe(!isset($w['operatingMode']) && !isset($w['fanSpeed']) && !isset($w['powerSwitch']), "$name: keine Bedienvariablen am Slave");
    }
    pruefe($w['temperature'] == $status['temperature'], "$name: Temperatur {$status['temperature']}");
    pruefe($w['humidity'] === $status['humidity'], "$name: Feuchte {$status['humidity']}");
    pruefe($w['humidityAlarm'] === $status['humidityAlarm'], "$name: Feuchtealarm");
    pruefe(end($m->status) === IS_ACTIVE, "$name: Instanz aktiv");
}

ergebnis();
