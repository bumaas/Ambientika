<?php

declare(strict_types=1);

use Ambientika\Cloud\ApiUrl;
use Ambientika\Cloud\ForwardData;
use Ambientika\Device\Property;
use Ambientika\Device\Timer;
use Ambientika\Device\Variables;
use Ambientika\Device\VariableValues;
use Ambientika\GUID;

eval('namespace AmbientikaDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');

require_once dirname(__DIR__) . '/libs/AmbientikaConsts.php';

/**
 * @method void RegisterProfileEx(int $VarTyp, string $Name, string $Icon, string $Prefix, string $Suffix, int|array $Associations, float $MaxValue = -1, float $StepSize = 0, int $Digits = 0)
 * @method void RegisterProfile(int $VarTyp, string $Name, string $Icon, string $Prefix, string $Suffix, float $MinValue, float $MaxValue, float $StepSize, int $Digits = 0)
 */
class AmbientikaDevice extends IPSModule
{
    use \AmbientikaDevice\VariableProfileHelper;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString(Property::HouseId, '');
        $this->RegisterPropertyString(Property::SerialNumber, '');
        $this->RegisterPropertyInteger(Property::RefreshStateInterval, 60);

        $this->RequireParent(GUID::CloudIO);

        $this->RegisterTimer(
            Timer::RefreshState,
            0,
            'IPS_RequestAction(' . $this->InstanceID . ',"' . Timer::RefreshState . '",true);'
        );
    }


    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Wenn Kernel nicht fertig, dann nichts machen und darauf warten
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
        if ($this->GetStatus() !== IS_ACTIVE) {
            trigger_error($this->Translate('Instance is not active'), E_USER_NOTICE);
            return;
        }

        switch ($Ident) {
            case Timer::RefreshState:
                $this->RequestState();
                return;
            case Variables::OperatingMode:
            case Variables::FanSpeed:
                //                $this->SendDebug(__FUNCTION__, sprintf('%s, consts: %s', $Ident, json_encode((new ReflectionClass(\Ambientika\Device\VariableValues::class))->getConstants())), 0);

                if (array_key_exists(ucfirst($Ident), (new ReflectionClass(VariableValues::class))->getConstants())) {
                    $arr = VariableValues::{ucfirst($Ident)};
                    $key = array_search($Value, $arr, true);
                    if ($key !== false) {
                        $Value = $key;
//                        $this->SendDebug(__FUNCTION__, sprintf('%s, key: %s, consts: %s', $Ident, $key, json_encode($arr)), 0);
                    }
                }
                $Params = [
                    'deviceSerialNumber' => $this->ReadPropertyString(Property::SerialNumber),
                    $Ident               => $Value
                ];
                $params = json_encode($Params);

                $result = $this->SendCloud(\Ambientika\Cloud\ApiUrl::ChangeMode, $params);
                if ($result !== null){
                    $this->SendDebug(__FUNCTION__, sprintf('result: %s', json_encode($result)), 0);
                }

                usleep(500000); // the device needs some time
                $this->RequestState();
                break;

            default:
                trigger_error($this->Translate('Invalid Ident') . ': ' . $Ident, E_USER_NOTICE);
        }
    }

    private function getParameterByValue(string $ident, string $value): string
    {
        $arr = VariableValues::{ucfirst($ident)};
        return $arr[$value];
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

        // Anzeige IP in der INFO Spalte
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

        $result = $this->SendCloud(
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
                    $this->SendDebug('changeVariable', sprintf('variable: %s, value: %s',$ident, $value), 0);
                }
                $this->SetValue($ident, $value);
            }
        }
        return true;
    }

    private function SendCloud(string $Uri, string $Params): ?array
    {
        if ($Params !== '') {
            $this->SendDebug(__FUNCTION__, sprintf('uri: %s, params: %s', $Uri, $Params), 0);
        } else {
            $this->SendDebug(__FUNCTION__, sprintf('uri: %s', $Uri), 0);
        }
        $Response = $this->SendDataToParent(ForwardData::ToJson($Uri, $Params));
        if (($Response === false) || ($Response === '')) {
            return null;
        }
        $this->SendDebug(__FUNCTION__, sprintf('response: %s', $Response), 0);

        return json_decode($Response, true, 512, JSON_THROW_ON_ERROR);
    }

}