<?php

declare(strict_types=1);

use Ambientika\Cloud\ApiUrl;
use Ambientika\Cloud\ForwardData;
use Ambientika\Device\Property;
use Ambientika\Device\Timer;
use Ambientika\Device\Variables;
use Ambientika\Device\VariableValues;
use Ambientika\Guid;

eval('namespace AmbientikaDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');

require_once dirname(__DIR__) . '/libs/AmbientikaConsts.php';

/**
 * @method void RegisterProfileEx(int $VarTyp, string $Name, string $Icon, string $Prefix, string $Suffix, int|array $Associations, float $MaxValue = -1, float $StepSize = 0, int $Digits = 0)
 * @method void RegisterProfile(int $VarTyp, string $Name, string $Icon, string $Prefix, string $Suffix, float $MinValue, float $MaxValue, float $StepSize, int $Digits = 0)
 */
class AmbientikaDevice extends IPSModule
{
    use \AmbientikaDevice\VariableProfileHelper;

    private const int DEVICE_SLEEP_TIME_MICROSECONDS = 1000000; // 700ms


    public function Create(): void
    {
        IPS_LogMessage(__FUNCTION__, 'started');

        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString(Property::HouseId, '');
        $this->RegisterPropertyString(Property::SerialNumber, '');
        $this->RegisterPropertyInteger(Property::RefreshStateInterval, 60);

        $this->RequireParent(Guid::CloudIO);

        $this->RegisterTimer(
            Timer::RefreshState,
            0,
            'IPS_RequestAction(' . $this->InstanceID . ',"' . Timer::RefreshState . '",true);'
        );
        IPS_LogMessage(__FUNCTION__, 'finished');

    }


    public function ApplyChanges(): void
    {
        IPS_LogMessage(__FUNCTION__, 'started');
        //Never delete this line!
        parent::ApplyChanges();

        // Wenn Kernel nicht fertig, dann nichts machen und darauf warten
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }
        IPS_RequestAction($this->InstanceID, 'InitConnection', '');

        $this->SetStatus(IS_ACTIVE);
        IPS_LogMessage(__FUNCTION__, 'finished');
        //$this->InitConnection();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message === IPS_KERNELSTARTED) {
            $this->KernelReady();
        }
    }

    public function RequestAction($Ident, $Value): void
    {
        if (($this->GetStatus() !== IS_ACTIVE) && ($Ident !== 'InitConnection')) {
            trigger_error($this->Translate('Instance is not active'), E_USER_NOTICE);
            return;
        }

        switch ($Ident) {
            case Timer::RefreshState:
                $this->RequestState();
                return;

            case 'InitConnection':
                $this->InitConnection();
                return;

            case Variables::OperatingMode:
            case Variables::FanSpeed:
                $value = $this->mapValueIfExists($Ident, $Value);
                $this->changeDeviceMode($Ident, $value);
                return;

            default:
                trigger_error($this->Translate('Invalid Ident') . ': ' . $Ident, E_USER_NOTICE);
        }
    }

    /**
     * Maps a given value to its corresponding key from a set of class constants if the identifier exists, otherwise returns the original value.
     *
     * @param string $identifier The identifier used to locate constants within the specified class.
     * @param mixed  $value      The value to be mapped if a corresponding key exists in the constants.
     *
     * @return mixed Returns the corresponding key from the class constants if the value is mapped, or the original value if no match is found.
     */
    private function mapValueIfExists(string $identifier, mixed $value): mixed
    {
        $constantsArray = $this->getConstantsArray(ucfirst($identifier));

        if ($constantsArray === null) {
            return $value;
        }

        $key = array_search($value, $constantsArray, true);

        return $key !== false ? $key : $value;
    }

    private function getConstantsArray(string $constantName): ?array
    {
        $classConstants = (new ReflectionClass(VariableValues::class))->getConstants();

        return $classConstants[$constantName] ?? null;
    }


    private function changeDeviceMode(string $identifier, mixed $value): void
    {
        $params = json_encode([
                                  'deviceSerialNumber' => $this->ReadPropertyString(Property::SerialNumber),
                                  $identifier          => $value,
                              ], JSON_THROW_ON_ERROR);

        $result = $this->sendCloudRequest(ApiUrl::ChangeMode, $params);

        if ($result !== null) {
            $this->SendDebug(__FUNCTION__, sprintf('Result: %s', json_encode($result, JSON_THROW_ON_ERROR)), 0);
        }

        usleep(self::DEVICE_SLEEP_TIME_MICROSECONDS);
        $this->RequestState();
    }


    protected function SetStatus($Status): void
    {
        switch ($Status) {
            case IS_ACTIVE:
                if ($this->GetStatus() > IS_EBASE) {
                    $this->LogMessage($this->Translate('Reconnect successfully'), KL_MESSAGE);
                }
                break;
            case IS_INACTIVE:
                $this->LogMessage($this->Translate('disconnected'), KL_MESSAGE);
                break;
        }
        parent::SetStatus($Status);
    }

    private function InitConnection(): void
    {
        $this->SetTimerInterval(Timer::RefreshState, 0);

        // Anzeige Seriennummer in der INFO Spalte
        $this->SetSummary($this->ReadPropertyString(Property::SerialNumber));

        if (!$this->ReadPropertyString(Property::HouseId)) {
            $this->SetStatus(\Ambientika\Device\InstanceStatus::HouseIdNotFound);
            return;
        }
        if (!$this->ReadPropertyString(Property::SerialNumber)) {
            $this->SetStatus(\Ambientika\Device\InstanceStatus::SerialNumberNotFound);
            return;
        }

        $this->CreateStateVariables();
        $this->SetStatus(IS_ACTIVE);

        if (!$this->RequestState()) {
            $this->SetStatus(\Ambientika\Device\InstanceStatus::InCloudOffline);
            return;
        }

        $this->SetTimerInterval(
            Timer::RefreshState,
            $this->ReadPropertyInteger(Property::RefreshStateInterval) * 1000
        );

        $this->SendDebug(__FUNCTION__, 'Connection established', 0);
        $this->LogMessage($this->Translate('Connection established'), KL_MESSAGE);
    }

    private function CreateStateVariables(): void
    {
        //Profile anlegen
        $this->RegisterProfileEx(
            VARIABLETYPE_INTEGER,
            'Ambientika.' . Variables::OperatingMode,
            '',
            '',
            '',
            $this->createProfileAssociations(VariableValues::OperatingMode)
        );
        $this->RegisterProfileEx(
            VARIABLETYPE_INTEGER,
            'Ambientika.' . Variables::FanSpeed,
            '',
            '',
            '',
            $this->createProfileAssociations(VariableValues::FanSpeed)
        );

        $this->RegisterProfile(
            VARIABLETYPE_INTEGER,
            'Ambientika.' . Variables::Temperature,
            '',
            '',
            '°C',
            -15,
            50,
            0,
            0
        );

        //STatusvariablen anlegen
        $pos = 0;
        $this->MaintainVariable(
            Variables::OperatingMode,
            'Operation Mode',
            VARIABLETYPE_INTEGER,
            'Ambientika.' . Variables::OperatingMode,
            $pos++,
            true
        );
        $this->MaintainVariable(
            Variables::FanSpeed,
            'Fan Speed',
            VARIABLETYPE_INTEGER,
            'Ambientika.' . Variables::FanSpeed,
            $pos++,
            true
        );
        $this->MaintainVariable(Variables::HumidityLevel, 'Humidity Level', VARIABLETYPE_STRING, '', $pos++, true);
        $this->MaintainVariable(Variables::Temperature, 'Temperature', VARIABLETYPE_STRING, 'Ambientika.' . Variables::Temperature, $pos++, true);

        $this->EnableAction(Variables::OperatingMode);
        $this->EnableAction(Variables::FanSpeed);
        //todo
    }

    private function createProfileAssociations(array $variableValues): array
    {
        $associations = [];

        foreach ($variableValues as $key => $value) {
            $associations[] = [$value, $key, '', -1];
        }
        return $associations;
    }

    private function KernelReady(): void
    {
        $this->UnregisterMessage(0, IPS_KERNELSTARTED);
        $this->InitConnection();
    }

    public function RequestState(): bool
    {
        if ($this->GetStatus() !== IS_ACTIVE) {
            trigger_error($this->Translate('Instance is not active'), E_USER_NOTICE);
            return false;
        }

        $result = $this->sendCloudRequest(
            ApiUrl::GetDeviceStatus . '?deviceSerialNumber=' . $this->ReadPropertyString(Property::SerialNumber),
            ''
        );

        if (is_null($result)) {
            return false;
        }
        foreach ($result as $ident => $value) {
            if (array_key_exists(ucfirst($ident), (new ReflectionClass(VariableValues::class))->getConstants())) {
                $arr   = VariableValues::{ucfirst($ident)};
                $value = $arr[$value];
            }

            if (array_key_exists(ucfirst($ident), (new ReflectionClass(Variables::class))->getConstants())) {
                if ($this->GetValue($ident) != $value) { //Achtung: die Typen können sich unterscheiden
                    $this->SendDebug('changeVariable', sprintf('variable: %s, value: %s', $ident, $value), 0);
                }
                $this->SetValue($ident, $value);
            }
        }
        return true;
    }

    private function sendCloudRequest(string $url, string $params): ?array
    {
        // Log der Anfrage (inklusive Bedingung, ob Params leer ist)
        $this->logRequest($url, $params);

        $response = $this->SendDataToParent(ForwardData::toJson($url, $params));

        if (empty($response)) {
            return null;
        }

        $this->SendDebug(__FUNCTION__, sprintf('Response: %s', $response), 0);

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    private function logRequest(string $url, string $params): void
    {
        $message = $params !== ''
            ? sprintf('URL: %s, Params: %s', $url, $params)
            : sprintf('URL: %s', $url);

        $this->SendDebug(__FUNCTION__, $message, 0);
    }

}