<?php

declare(strict_types=1);

/**
 * Variablen und Darstellungen hängen nur an der Geräterolle und daran, ob das Gerät Turbo meldet. Die
 * Statusabfrage baut sie deshalb nur neu, wenn sich eins davon ändert. Vorher liefen bei jeder Abfrage
 * 15 MaintainVariable samt sieben übersetzten Aufzählungen, bei 8 Geräten und 10 s rund 2.900
 * Translate-Aufrufe pro Minute (Code-Review 25.10.2026, Befund 6).
 *
 * Aufruf: php tests/check-maintain.php
 */

require_once __DIR__ . '/harness.php';

function antwort(AmbientikaDeviceHarness $m, string $name): void
{
    $m->statusAntwort   = file_get_contents(__DIR__ . '/fixtures/device-status/' . $name . '.json');
    $m->maintainAufrufe = 0;
    $m->RequestState();
}

/** @return list<int> Werte der Lüfterstufen-Aufzählung */
function stufen(AmbientikaDeviceHarness $m): array
{
    $darstellung = IPS_GetVariable(IPS_GetObjectIDByIdent('fanSpeed', $m->instanzId()))['VariablePresentation'];
    return array_column(json_decode($darstellung['OPTIONS'], true, 512, JSON_THROW_ON_ERROR), 'Value');
}

$m = geraetMitStatus('smart-low-master');

antwort($m, 'smart-low-master');
pruefe($m->maintainAufrufe === 0, 'gleiche Rolle, kein Turbo: Variablen werden nicht neu gebaut → ' . $m->maintainAufrufe);
antwort($m, 'smart-low-master');
pruefe($m->maintainAufrufe === 0, 'auch bei der nächsten Abfrage nicht → ' . $m->maintainAufrufe);

antwort($m, 'turbo-master');
pruefe($m->maintainAufrufe > 0, 'Gerät meldet Turbo: Variablen werden neu gebaut → ' . $m->maintainAufrufe);
pruefe(stufen($m) === [1, 2, 3, 4], 'Lüfterstufe bietet danach Turbo an → ' . json_encode(stufen($m)));
antwort($m, 'turbo-master');
pruefe($m->maintainAufrufe === 0, 'Turbo unverändert: kein Neubau → ' . $m->maintainAufrufe);

antwort($m, 'smart-night-slave');
pruefe($m->maintainAufrufe > 0, 'Rolle wechselt zu Slave: Variablen werden neu gebaut → ' . $m->maintainAufrufe);
pruefe(!@IPS_GetObjectIDByIdent('powerSwitch', $m->instanzId()), 'Slave hat keine Bedienvariablen');
antwort($m, 'smart-night-slave');
pruefe($m->maintainAufrufe === 0, 'Rolle unverändert: kein Neubau → ' . $m->maintainAufrufe);

// Übernehmen baut immer, etwa nach einem Modul-Update mit geänderten Darstellungen
$m->maintainAufrufe = 0;
IPS_ApplyChanges($m->instanzId());
pruefe($m->maintainAufrufe > 0, 'Übernehmen baut die Variablen neu → ' . $m->maintainAufrufe);

ergebnis();
