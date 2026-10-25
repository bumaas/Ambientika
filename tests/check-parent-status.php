<?php

declare(strict_types=1);

/**
 * Das Gerät folgt dem Status der Cloud-Instanz sofort, nicht erst mit der nächsten Abfrage (Hop-by-Hop,
 * IM_CHANGESTATUS am Parent). Sonst stehen nach einem Kernelstart, bei dem die Geräte vor dem Login der
 * Cloud bereit sind, alle Geräte bis zur nächsten Abfrage auf 104 und lehnen jede Bedienung ab. Mit
 * Aktualisierungsintervall 0 bleiben sie dort für immer (Code-Review 25.10.2026, Befund 1).
 *
 * Aufruf: php tests/check-parent-status.php
 */

use Ambientika\Device\Property;

require_once __DIR__ . '/harness.php';

IPS\ModuleLoader::loadLibrary(dirname(__DIR__) . '/library.json');
require_once dirname(__DIR__) . '/Ambientika Cloud/module.php';

function neueCloud(): int
{
    $id = IPS\ObjectManager::registerObject(1 /* Instance */);
    IPS\InstanceManager::createInstance($id, [
        'ModuleID'   => '{C10C91F4-1843-CBE1-D974-2395A4620106}', // Ambientika Cloud/module.json
        'ModuleName' => 'AmbientikaCloud',
        'ModuleType' => 1,
        'Class'      => AmbientikaCloud::class,
    ]);
    return $id;
}

/** Hat das Gerät sich für Statuswechsel dieser Instanz angemeldet? */
function hoertAuf(AmbientikaDeviceHarness $m, int $senderId): bool
{
    return in_array(IM_CHANGESTATUS, $m->nachrichten()[$senderId] ?? [], true);
}

$cloud = neueCloud();
$m     = neueInstanz();
IPS_ConnectInstance($m->instanzId(), $cloud);
$m->statusAntwort = file_get_contents(__DIR__ . '/fixtures/device-status/smart-low-master.json');
IPS_SetProperty($m->instanzId(), Property::SerialNumber, '000000000001');
IPS_SetProperty($m->instanzId(), Property::RefreshStateInterval, 0);

// Kernelstart: das Gerät ist vor der Cloud bereit
$m->parentAktiv = false;
IPS_ApplyChanges($m->instanzId());
pruefe(end($m->status) === IS_INACTIVE, 'Cloud noch nicht angemeldet: Gerät inaktiv → ' . end($m->status));
pruefe(hoertAuf($m, $cloud), 'Gerät meldet sich für Statuswechsel der Cloud-Instanz an');

// Cloud meldet sich an, ohne Abfrage-Timer
$m->parentAktiv = true;
$m->MessageSink(time(), $cloud, IM_CHANGESTATUS, [IS_ACTIVE, IS_INACTIVE]);
pruefe(end($m->status) === IS_ACTIVE, 'Cloud aktiv: Gerät sofort aktiv, ohne Timer → ' . end($m->status));

// Cloud fällt aus
$m->parentAktiv = false;
$m->MessageSink(time(), $cloud, IM_CHANGESTATUS, [IS_INACTIVE, IS_ACTIVE]);
pruefe(end($m->status) === IS_INACTIVE, 'Cloud inaktiv: Gerät sofort inaktiv → ' . end($m->status));

// Umhängen an eine andere Cloud-Instanz
$cloud2 = neueCloud();
IPS_DisconnectInstance($m->instanzId());
$m->MessageSink(time(), $m->instanzId(), FM_DISCONNECT, [$cloud, 0]);
IPS_ConnectInstance($m->instanzId(), $cloud2);
$m->parentAktiv = true;
$m->MessageSink(time(), $m->instanzId(), FM_CONNECT, [$cloud2, 0]);
pruefe(hoertAuf($m, $cloud2) && !hoertAuf($m, $cloud), 'nach dem Umhängen hört das Gerät nur auf die neue Cloud-Instanz');
pruefe(end($m->status) === IS_ACTIVE, 'neue Cloud-Instanz aktiv: Gerät aktiv → ' . end($m->status));

ergebnis();
