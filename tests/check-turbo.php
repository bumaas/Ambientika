<?php

declare(strict_types=1);

/**
 * Geräte der neueren Generation haben über „hoch“ eine vierte Stufe, in der App als Rakete, in der
 * Cloud als fanSpeed „Turbo“ (Forum, chrisH 25.10.2026). Ob ein Gerät sie kann, meldet es selbst mit
 * isTurboAvailable. Die Stufe wird gelesen, nur bei diesen Geräten angeboten und als „Turbo“ gesendet.
 * Dafür tragen die Variablen Darstellungen statt der globalen Profile Ambientika.*, die für alle
 * Geräte gleich wären.
 *
 * Aufruf: php tests/check-turbo.php
 */

use Ambientika\Cloud\ApiUrl;

require_once __DIR__ . '/harness.php';

function variable(AmbientikaDeviceHarness $m, string $ident): array
{
    return IPS_GetVariable(IPS_GetObjectIDByIdent($ident, $m->instanzId()));
}

/** @return list<int|bool> Werte der Optionen einer Aufzählung, [] ohne Darstellung */
function optionswerte(AmbientikaDeviceHarness $m, string $ident): array
{
    $darstellung = variable($m, $ident)['VariablePresentation'];
    if (($darstellung['PRESENTATION'] ?? '') !== VARIABLE_PRESENTATION_ENUMERATION) {
        return [];
    }
    return array_column(json_decode($darstellung['OPTIONS'], true, 512, JSON_THROW_ON_ERROR), 'Value');
}

function letzteStufe(AmbientikaDeviceHarness $m): mixed
{
    foreach (array_reverse($m->gesendet) as $anfrage) {
        if ($anfrage['uri'] === ApiUrl::ChangeMode) {
            return $anfrage['params']['fanSpeed'] ?? null;
        }
    }
    return null;
}

// Neues Gerät auf Rakete
$neu = geraetMitStatus('turbo-master');
pruefe($neu->werte()['fanSpeed'] === 4, 'Turbo wird als Stufe 4 gelesen → ' . var_export($neu->werte()['fanSpeed'], true));
pruefe(optionswerte($neu, 'fanSpeed') === [1, 2, 3, 4], 'Lüfterstufe bietet Turbo an → ' . json_encode(optionswerte($neu, 'fanSpeed')));

$neu->RequestAction('fanSpeed', 3);
pruefe(letzteStufe($neu) === 'High', 'Stufe hoch geht als High raus → ' . var_export(letzteStufe($neu), true));
$neu->RequestAction('fanSpeed', 4);
pruefe(letzteStufe($neu) === 'Turbo', 'Turbo geht als Turbo raus → ' . var_export(letzteStufe($neu), true));

// Altes Gerät ohne Turbo
$alt = geraetMitStatus('smart-low-master');
pruefe(optionswerte($alt, 'fanSpeed') === [1, 2, 3], 'altes Gerät bietet Turbo nicht an → ' . json_encode(optionswerte($alt, 'fanSpeed')));

// Darstellungen statt globaler Profile
$legacy = [];
foreach (IPS_GetChildrenIDs($neu->instanzId()) as $vid) {
    $var = IPS_GetVariable($vid);
    if (($var['VariablePresentation']['PRESENTATION'] ?? VARIABLE_PRESENTATION_LEGACY) === VARIABLE_PRESENTATION_LEGACY) {
        $legacy[] = IPS_GetObject($vid)['ObjectIdent'];
    }
}
pruefe($legacy === [], 'keine Variable trägt ein Profil → ' . implode(', ', $legacy));
$profile = array_values(array_filter(IPS_GetVariableProfileList(), static fn (string $p): bool => str_starts_with($p, 'Ambientika.')));
pruefe($profile === [], 'das Modul legt keine Profile Ambientika.* an → ' . implode(', ', $profile));

ergebnis();
