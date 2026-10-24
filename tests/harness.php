<?php

declare(strict_types=1);

/*
 * Gemeinsamer Testrahmen: bindet AmbientikaDevice an den offiziellen Kernel-Stub
 * (symcon/SymconStubs, Submodul tests/stubs, gepinnt auf 31f2e04).
 * Von testsuite.php anlegen erzeugt - Netz-Naht und modulspezifische Helfer sind ein
 * TODO-Grundgerüst, kein fertiger Testrahmen. Siehe stub-und-vorlagen.md.
 *
 * Einbinden mit require_once __DIR__ . '/harness.php'; Instanzen über neueInstanz().
 */

require_once __DIR__ . '/stubs/autoload.php';

// PHP-Warnungen/-Notices sollen Tests abbrechen, nicht still durchlaufen.
set_error_handler(static function (int $nr, string $text, string $datei, int $zeile): bool {
    if (!(error_reporting() & $nr)) {
        return false;
    }
    if ($nr & (E_USER_ERROR | E_USER_WARNING | E_WARNING | E_NOTICE)) {
        throw new ErrorException($text, 0, $nr, $datei, $zeile);
    }
    return false;
});

require_once dirname(__DIR__) . '/Ambientika Device/module.php';

final class AmbientikaDeviceHarness extends AmbientikaDevice
{
    public const MODULE_ID = '{8CF53BBB-FDB2-7850-79D5-42A14A8649B3}'; // Ambientika Device/module.json

    /** @var list<array{uri: string, params: array}> jede Anfrage an die Cloud-Instanz, Params dekodiert */
    public array $gesendet = [];
    /** Antwort der Cloud auf /Device/device-status (roher JSON-String, Mitschnitt aus tests/fixtures) */
    public string $statusAntwort = '';
    /** @var list<array{0: string, 1: mixed}> jedes SetValue */
    public array $writes = [];
    /** @var list<int> jedes SetStatus */
    public array $status = [];
    /** @var array<string, int> letztes SetTimerInterval je Timer */
    public array $timer  = [];
    private int $logOffset = 0;

    /** Netz-Naht: Anfragen gehen nicht an die Cloud-Instanz, sondern werden mitgeschrieben. */
    protected function SendDataToParent($Data): string
    {
        ['uri' => $uri, 'params' => $params] = Ambientika\Cloud\ForwardData::fromJson($Data);
        $this->gesendet[] = ['uri' => $uri, 'params' => $params === '' ? [] : json_decode($params, true, 512, JSON_THROW_ON_ERROR)];

        return str_starts_with($uri, Ambientika\Cloud\ApiUrl::GetDeviceStatus) ? $this->statusAntwort : '';
    }

    protected function HasActiveParent(): bool
    {
        return true;
    }

    protected function SetValue(string $Ident, $Value): bool
    {
        $ok = parent::SetValue($Ident, $Value);
        $this->writes[] = [$Ident, $Value];
        return $ok;
    }

    protected function SetStatus($Status): bool
    {
        $this->status[] = $Status;
        return parent::SetStatus($Status);
    }

    protected function SetTimerInterval(string $Ident, int $Milliseconds): void
    {
        $this->timer[$Ident] = $Milliseconds;
        parent::SetTimerInterval($Ident, $Milliseconds);
    }

    /** InstanceID ist im Stub protected - für Tests von außen lesbar machen. */
    public function instanzId(): int
    {
        return $this->InstanceID;
    }

    /** Alle Variablenwerte der Instanz (Ident -> Wert), typgetreu aus dem Kernel-Stub. */
    public function werte(): array
    {
        $werte = [];
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $vid) {
            $obj = IPS_GetObject($vid);
            if ($obj['ObjectType'] === 2 /* Variable */) {
                $werte[$obj['ObjectIdent']] = GetValue($vid);
            }
        }
        return $werte;
    }

    public function logsZuruecksetzen(): void
    {
        $this->logOffset = count(IPS\LogServer::getLogMessages((string) $this->InstanceID));
    }

    /** @return list<array{Message: string, Type: int}> */
    public function logsSeitMarke(): array
    {
        return array_values(array_slice(IPS\LogServer::getLogMessages((string) $this->InstanceID), $this->logOffset));
    }
}

/** Legt eine Instanz im Kernel-Stub an (Create + ApplyChanges laufen in createInstance). */
function neueInstanz(): AmbientikaDeviceHarness
{
    $id = IPS\ObjectManager::registerObject(1 /* Instance */);
    IPS\InstanceManager::createInstance($id, [
        'ModuleID'   => AmbientikaDeviceHarness::MODULE_ID,
        'ModuleName' => 'AmbientikaDevice',
        'ModuleType' => 3,
        'Class'      => AmbientikaDeviceHarness::class,
    ]);
    return IPS\InstanceManager::getInstanceInterface($id);
}

/**
 * Instanz mit Seriennummer, deren Statusabfrage den Mitschnitt tests/fixtures/device-status/<name>.json liefert.
 * ApplyChanges legt dann die Variablen an und liest den Status einmal ein.
 */
function geraetMitStatus(string $name): AmbientikaDeviceHarness
{
    $m = neueInstanz();
    $m->statusAntwort = file_get_contents(__DIR__ . '/fixtures/device-status/' . $name . '.json');
    $status = json_decode($m->statusAntwort, true, 512, JSON_THROW_ON_ERROR);
    IPS_SetProperty($m->instanzId(), 'SerialNumber', $status['deviceSerialNumber']);
    IPS_ApplyChanges($m->instanzId());
    $m->gesendet = [];
    $m->writes   = [];
    return $m;
}

$pruefungen = 0;
$fehler     = [];
function pruefe(bool $ok, string $text): void
{
    global $pruefungen, $fehler;
    $pruefungen++;
    if (!$ok) {
        $fehler[] = $text;
    }
    echo ($ok ? '  ok   ' : '  FEHL ') . $text . "\n";
}

/** Schlusszeile und Exit-Code - Format ist Pflicht (K2), rotgruen.php parst genau das. */
function ergebnis(): never
{
    global $pruefungen, $fehler;
    echo "\n$pruefungen Prüfungen, " . count($fehler) . " Fehler\n";
    exit($fehler === [] ? 0 : 1);
}

IPS\Kernel::reset(); // einmal je Testlauf; weitere Instanzen entstehen im selben Kernel
