<?php

declare(strict_types=1);

eval('namespace AmbientikaConfigurator {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/AmbientikaConsts.php';
/**
 * @property int ParentID
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 */
class AmbientikaConfigurator extends IPSModule
{
    use \AmbientikaConfigurator\DebugHelper;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RequireParent(\Ambientika\GUID::CloudIO);
    }


    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true, 512, JSON_THROW_ON_ERROR);
        if ($this->GetStatus() === IS_CREATING) {
            return json_encode($Form, JSON_THROW_ON_ERROR);
        }

        $DeviceValues   = [];

        $InstanceIDList = $this->GetInstanceList(\Ambientika\GUID::Device, \Ambientika\Device\Property::SerialNumber);
        $Devices = [];
        if (IPS_GetInstance($this->InstanceID)['ConnectionID'] > 1) { //IO ist angelegt
            $Devices = $this->GetHouseDevices();

            // Filter auf gleichen IO
            $InstanceIDList = array_filter($InstanceIDList, function ($InstanceIdDevice) {
                return IPS_GetInstance($InstanceIdDevice)['ConnectionID'] == IPS_GetInstance($this->InstanceID)['ConnectionID'];
            },                             ARRAY_FILTER_USE_KEY);
        }

        foreach ($Devices as $Device) {
            $AddDevice        = [
                'houseId'      => $Device['houseId'],
                'serialNumber' => $Device['serialNumber'],
                'deviceType'   => $Device['deviceType'],
                'cloudName'    => $Device['name'],
                'symconName'   => ''
            ];
            $InstanceIdDevice = array_search($Device['serialNumber'], $InstanceIDList);
            if ($InstanceIdDevice !== false) {
                $AddDevice['symconName'] = IPS_GetName($InstanceIdDevice);
                $AddDevice['instanceID'] = $InstanceIdDevice;
                unset($InstanceIDList[$InstanceIdDevice]);
            }

            $AddDevice['create'] = [
                'moduleID'      => \Ambientika\GUID::Device,
                'location'      => [],
                'name'          => $Device['name'],
                'configuration' => [
                    \Ambientika\Device\Property::HouseId      => $Device['houseId'],
                    \Ambientika\Device\Property::SerialNumber => $Device['serialNumber']
                ]
            ];
            $DeviceValues[]      = $AddDevice;
        }
        foreach ($InstanceIDList as $InstanceIdDevice => $serialNumber) {
            $AddDevice      = [
                'instanceID'   => $InstanceIdDevice,
                'houseId'      => IPS_GetProperty($InstanceIdDevice, \Ambientika\Device\Property::HouseId),
                'serialNumber' => '',
                'deviceType'   => '',
                'symconName'         => IPS_GetName($InstanceIdDevice)
            ];
            $DeviceValues[] = $AddDevice;
        }

        $Form['actions'][0]['values'] = $DeviceValues;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    protected function FilterInstances(int $InstanceID): bool
    {
        return IPS_GetInstance($InstanceID)['ConnectionID'] == $this->ParentID;
    }

    protected function GetConfigParam(&$item1, int $InstanceID, string $ConfigParam): void
    {
        $item1 = IPS_GetProperty($InstanceID, $ConfigParam);
    }

    private function GetHouseDevices(): array
    {
        $houseDevices = [];
        $this->SendDebug(__FUNCTION__, \Ambientika\Cloud\ApiUrl::GetHouses, 0);
        $resultHouses = $this->Request(\Ambientika\Cloud\ApiUrl::GetHouses, '');
        $this->SendDebug(__FUNCTION__, sprintf('houses: %s', $resultHouses), 0);
        if ($resultHouses) {
            foreach (json_decode($resultHouses, true, 512, JSON_THROW_ON_ERROR) as $house) {
                $resultHouseDevices = $this->Request(\Ambientika\Cloud\ApiUrl::GetHouseDevices . '?houseId=' . $house['id'], '');
                $this->SendDebug(__FUNCTION__, sprintf('houseDevicess: %s', $resultHouseDevices), 0);
                if ($resultHouseDevices) {
                    $devices = json_decode($resultHouseDevices, true, 512, JSON_THROW_ON_ERROR);

                    // "houseId" hinzufügen
                    foreach ($devices as &$device) {
                        $device['houseId'] = $house['id'];
                        $houseDevices[]    = $device;
                    }
                }
            }
        }
        return $houseDevices;
    }

    private function Request(string $Uri, string $Params): ?string
    {
        $Result = $this->SendDataToParent(\Ambientika\Cloud\ForwardData::ToJson($Uri, $Params));
        if ($Result === '') {
            return null;
        }
        return $Result;
    }

    private function GetInstanceList(string $GUID, string $ConfigParam): array
    {
        $InstanceIDList = IPS_GetInstanceListByModuleID($GUID);
        $InstanceIDList = array_flip(array_values($InstanceIDList));
        array_walk($InstanceIDList, [$this, 'GetConfigParam'], $ConfigParam);
        $this->SendDebug('Filter', $InstanceIDList, 0);
        return $InstanceIDList;
    }

}
