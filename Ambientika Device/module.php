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

    private const int DEVICE_SLEEP_TIME_MICROSECONDS = 1000000; // 1000ms = 1 s


    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger(Property::HouseId, 0);
        $this->RegisterPropertyString(Property::SerialNumber, '');
        $this->RegisterPropertyInteger(Property::RefreshStateInterval, 10);

        $this->ConnectParent(Guid::CloudIO);
        $this->RegisterTimer(
            Timer::RefreshState,
            0,
            'AMBIENTIKA_RequestState(' . $this->InstanceID . ');'
        );
    }


    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Wenn Kernel nicht fertig, nichts machen und darauf warten
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }

        $this->InitConnection();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message === IPS_KERNELSTARTED) {
            $this->KernelReady();
        }
    }

    public function RequestAction($Ident, $Value): void
    {
        if (($Ident !== 'InitConnection') && ($this->GetStatus() !== IS_ACTIVE)) {
            trigger_error($this->Translate('Instance is not active'), E_USER_NOTICE);
            return;
        }

        switch ($Ident) {
            case Variables::PowerSwitch:
                if ($Value === true) {
                    $this->changeDeviceMode(Variables::OperatingMode, $this->GetValue(Variables::LastOperatingMode));
                } else {
                    $this->changeDeviceMode(Variables::OperatingMode, 'Off');
                }
                break;

            case Variables::OperatingMode:
            case Variables::FanSpeed:
            case Variables::HumidityLevel:
            case Variables::LightSensorLevel:
                $value = $this->mapValueIfExists($Ident, $Value);
                $this->changeDeviceMode($Ident, $value);
                break;

            case Variables::FilterReset:
                $this->ResetFilter();
                break;

            default:
                trigger_error($this->Translate('Invalid Ident') . ': ' . $Ident, E_USER_NOTICE);
        }
    }

    public function RequestState(): bool
    {
        if (!$this->HasActiveParent()) {
            trigger_error($this->Translate('I/O Instance is not active'), E_USER_NOTICE);
            return false;
        }

        $result = $this->sendCloudRequest(
            ApiUrl::GetDeviceStatus . '?deviceSerialNumber=' . $this->ReadPropertyString(Property::SerialNumber),
            ''
        );

        if (is_null($result)) {
            $this->SendDebug(__FUNCTION__, 'RequestState failed', 0);
            $this->SetStatus(\Ambientika\Device\InstanceStatus::RequestFailed);
            return false;
        }

        $this->SetStatus(IS_ACTIVE);

        foreach ($result as $ident => $value) {
            if (in_array($ident, ['packetType', 'deviceType', 'deviceSerialNumber'])) {
                continue;
            }
            if (array_key_exists(ucfirst($ident), (new ReflectionClass(VariableValues::class))->getConstants())) {
                $arr = VariableValues::{ucfirst($ident)};
                assert(isset($arr[$value]), sprintf('Missing key. ident: %s, value: %s', $ident, $value));

                $value = $arr[$value];
            }
            if ($this->GetValue($ident) != $value) { //Note: the types may differ
                $this->SendDebug('changeVariable', sprintf('variable: %s, value: %s', $ident, $value), 0);
            }

            $this->SetValue($ident, $value);
        }

        $this->SetValue(Variables::PowerSwitch, $this->GetValue(Variables::OperatingMode) !== VariableValues::OperatingMode['Off']);
        return true;
    }

    public function ResetFilter(): void
    {
        if (!$this->HasActiveParent()) {
            trigger_error($this->Translate('I/O Instance is not active'), E_USER_NOTICE);
            return;
        }

        $this->sendCloudRequest(
            ApiUrl::ResetFilter . '?deviceSerialNumber=' . $this->ReadPropertyString(Property::SerialNumber),
            ''
        );
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
        $this->sendDebug(__FUNCTION__, sprintf('Status: %s', $Status), 0);
        parent::SetStatus($Status);
    }

    private function InitConnection(): void
    {
        $this->SetTimerInterval(Timer::RefreshState, 0);

        // Display serial number in the INFO column
        $this->SetSummary($this->ReadPropertyString(Property::SerialNumber));

        if (!$this->ReadPropertyString(Property::SerialNumber)) {
            $this->SetStatus(\Ambientika\Device\InstanceStatus::SerialNumberNotSet);
            return;
        }

        $this->CreateStateVariables();

        $this->SetStatus(IS_ACTIVE);

        if (!$this->RequestState()) {
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
            'Ambientika.ExecuteAction',
            '',
            '',
            '',
            [
                [0, $this->Translate('Execute'), '', -1]
            ]
        );

        $this->RegisterProfileEx(
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucFirst(Variables::OperatingMode),
            '',
            '',
            '',
            $this->createProfileAssociations(VariableValues::OperatingMode)
        );

        $this->RegisterProfileEx(
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucFirst(Variables::FanSpeed),
            '',
            '',
            '',
            $this->createProfileAssociations(VariableValues::FanSpeed)
        );

        $this->RegisterProfileEx(
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucFirst(Variables::HumidityLevel),
            '',
            '',
            '',
            $this->createProfileAssociations(VariableValues::HumidityLevel)
        );

        $this->RegisterProfile(
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucFirst(Variables::Temperature),
            '',
            '',
            '°C',
            -15,
            50,
            0,
            0
        );

        $this->RegisterProfileEx(
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucFirst(Variables::AirQuality),
            '',
            '',
            '',
            $this->createProfileAssociations(VariableValues::AirQuality)
        );

        $this->RegisterProfileEx(
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucFirst(Variables::FiltersStatus),
            '',
            '',
            '',
            $this->createProfileAssociations(VariableValues::FiltersStatus)
        );

        $this->RegisterProfileEx(
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucFirst(Variables::DeviceRole),
            '',
            '',
            '',
            $this->createProfileAssociations(VariableValues::DeviceRole)
        );

        $this->RegisterProfileEx(
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucFirst(Variables::LightSensorLevel),
            '',
            '',
            '',
            $this->createProfileAssociations(VariableValues::LightSensorLevel)
        );

        $this->RegisterProfile(
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucFirst(Variables::SignalStrenght),
            '',
            '',
            '',
            -15,
            250,
            0,
            0
        );


        //Statusvariablen anlegen
        $pos = 0;
        $this->MaintainVariable(
            Variables::PowerSwitch,
            $this->translate('Power'),
            VARIABLETYPE_BOOLEAN,
            '~Switch',
            ++$pos,
            true
        );
        $this->MaintainVariable(
            Variables::OperatingMode,
            $this->translate('Operation Mode'),
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucfirst(Variables::OperatingMode),
            ++$pos,
            true
        );
        $this->MaintainVariable(
            Variables::FanSpeed,
            $this->translate('Fan Speed'),
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucfirst(Variables::FanSpeed),
            ++$pos,
            true
        );
        $this->MaintainVariable(
            Variables::Temperature,
            $this->translate('Temperature'),
            VARIABLETYPE_STRING,
            ucfirst('Ambientika.' . Variables::Temperature),
            ++$pos,
            true
        );
        $this->MaintainVariable(
            Variables::HumidityLevel,
            $this->translate('Humidity Level'),
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucfirst(Variables::HumidityLevel),
            ++$pos,
            true
        );
        $this->MaintainVariable(Variables::Humidity, $this->translate('Humidity'), VARIABLETYPE_INTEGER, '~Humidity', ++$pos, true);
        $this->MaintainVariable(
            Variables::AirQuality,
            $this->translate('Air Quality'),
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucfirst(Variables::AirQuality),
            ++$pos,
            true
        );
        $this->MaintainVariable(Variables::HumidityAlarm, $this->translate('Humidity Alarm'), VARIABLETYPE_BOOLEAN, '~Switch', ++$pos, true);
        $this->MaintainVariable(
            Variables::FiltersStatus,
            $this->translate('Filter Status'),
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucfirst(Variables::FiltersStatus),
            ++$pos,
            true
        );
        $this->MaintainVariable(
            Variables::FilterReset,
            $this->translate('Reset Filter'),
            VARIABLETYPE_INTEGER,
            'Ambientika.ExecuteAction',
            ++$pos,
            true
        );
        $this->MaintainVariable(
            Variables::LightSensorLevel,
            $this->translate('Light Sensor Level'),
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucfirst(Variables::LightSensorLevel),
            ++$pos,
            true
        );
        $this->MaintainVariable(Variables::NightAlarm, $this->translate('Night Alarm'), VARIABLETYPE_BOOLEAN, '~Switch', ++$pos, true);
        $this->MaintainVariable(
            Variables::DeviceRole,
            $this->translate('Device Role'),
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucfirst(Variables::DeviceRole),
            ++$pos,
            true
        );
        $this->MaintainVariable(
            Variables::LastOperatingMode,
            $this->translate('Last Operating Mode'),
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucfirst(Variables::OperatingMode),
            ++$pos,
            true
        );
        $this->MaintainVariable(
            Variables::SignalStrenght,
            $this->translate('Signal Strength'),
            VARIABLETYPE_INTEGER,
            'Ambientika.' . ucfirst(Variables::SignalStrenght),
            ++$pos,
            true
        );

        $this->EnableAction(Variables::PowerSwitch);
        $this->EnableAction(Variables::OperatingMode);
        $this->EnableAction(Variables::FanSpeed);
        $this->EnableAction(Variables::HumidityLevel);
        $this->EnableAction(Variables::LightSensorLevel);
        $this->EnableAction(Variables::FilterReset);
    }

    private function createProfileAssociations(array $variableValues): array
    {
        $associations = [];

        foreach ($variableValues as $key => $value) {
            $associations[] = [$value, $this->translate($key), '', -1];
        }
        return $associations;
    }

    private function KernelReady(): void
    {
        $this->UnregisterMessage(0, IPS_KERNELSTARTED);
        $this->InitConnection();
    }


    private function sendCloudRequest(string $url, string $params): ?array
    {
        // Log der Anfrage (inklusive Bedingung, ob Params leer ist)
        $this->logRequest(__FUNCTION__, $url, $params);

        $response = $this->SendDataToParent(ForwardData::toJson($url, $params));

        if (empty($response)) {
            return null;
        }

        $this->SendDebug(__FUNCTION__, sprintf('Response: %s', $response), 0);

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    private function logRequest(string $function, string $url, string $params): void
    {
        $message = $params !== '' ? sprintf('URL: %s, Params: %s', $url, $params) : sprintf('URL: %s', $url);

        $this->SendDebug($function, $message, 0);
    }

}