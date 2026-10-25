<?php

declare(strict_types=1);

/**
 * Kommt von der Cloud gar keine Antwort (Timeout, DNS, Verbindung abgelehnt), liefert curl_exec false.
 * Die Cloud-Instanz muss das als HTTP 0 an Gerät und Konfigurator weitergeben, statt mit einem
 * TypeError abzubrechen (SendDebug verlangt unter IPSModuleStrict einen String).
 *
 * Der Fehlschlag ist echt: die Anfrage geht über den Benutzerteil der URL an 127.0.0.1:1, dort lehnt
 * der Rechner die Verbindung ab.
 *
 * Aufruf: php tests/check-cloud-no-response.php
 */

use Ambientika\Cloud\CloudResponse;
use Ambientika\Cloud\ForwardData;

require_once __DIR__ . '/harness.php';
require_once dirname(__DIR__) . '/Ambientika Cloud/module.php';

$id = IPS\ObjectManager::registerObject(1 /* Instance */);
IPS\InstanceManager::createInstance($id, [
    'ModuleID'   => '{C10C91F4-1843-CBE1-D974-2395A4620106}', // Ambientika Cloud/module.json
    'ModuleName' => 'AmbientikaCloud',
    'ModuleType' => 1,
    'Class'      => AmbientikaCloud::class,
]);
$cloud = IPS\InstanceManager::getInstanceInterface($id);

$antwort = null;
$fehler1 = '';
try {
    $antwort = $cloud->ForwardData(ForwardData::toJson('@127.0.0.1:1/Device/device-status', ''));
} catch (Throwable $e) {
    $fehler1 = get_class($e) . ': ' . $e->getMessage();
}
pruefe($fehler1 === '', 'ForwardData bricht ohne Antwort nicht ab ' . $fehler1);
pruefe($antwort !== null && CloudResponse::decode($antwort) === ['http' => 0, 'body' => ''], 'Antwort ist HTTP 0 ohne Text → ' . var_export($antwort, true));

$text    = 'nicht gesetzt';
$fehler2 = '';
try {
    $text = $cloud->sendRequest('@127.0.0.1:1/Device/device-status', '');
} catch (Throwable $e) {
    $fehler2 = get_class($e) . ': ' . $e->getMessage();
}
pruefe($fehler2 === '' && $text === null, 'AMBIENTIKA_sendRequest liefert null ' . $fehler2);

ergebnis();
