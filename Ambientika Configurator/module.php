<?php

declare(strict_types=1);

use Ambientika\Cloud\ApiUrl;
use Ambientika\Cloud\ForwardData;
use Ambientika\Configurator\ConfiguratorFields;
use Ambientika\Device\Property;
use Ambientika\Guid;

eval('namespace AmbientikaConfigurator {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/AmbientikaConsts.php';
/**
 * @property int ParentID
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 */
class AmbientikaConfigurator extends IPSModule
{
    //use \AmbientikaConfigurator\DebugHelper;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent(Guid::CloudIO);
    }


    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true, 512, JSON_THROW_ON_ERROR);
        if ($this->GetStatus() === IS_CREATING) {
            return json_encode($Form, JSON_THROW_ON_ERROR);
        }

        $DeviceValues   = [];

        $InstanceIDList = $this->GetInstanceList(Guid::Device, Property::SerialNumber);
        $Devices = [];
        if (IPS_GetInstance($this->InstanceID)['ConnectionID'] > 1) { //IO ist angelegt
            $Devices = $this->GetHouseDevices();

            // Filter auf gleichen IO
            $InstanceIDList = array_filter($InstanceIDList, function ($InstanceIdDevice) {
                return IPS_GetInstance($InstanceIdDevice)['ConnectionID'] === IPS_GetInstance($this->InstanceID)['ConnectionID'];
            },                             ARRAY_FILTER_USE_KEY);
        }

        foreach ($Devices as $Device) {
            $AddDevice        = [
                ConfiguratorFields::HouseId      => $Device['houseId'],
                ConfiguratorFields::SerialNumber => $Device['serialNumber'],
                ConfiguratorFields::DeviceType   => $Device['deviceType'],
                ConfiguratorFields::CloudName    => $Device['name'],
                ConfiguratorFields::SymconName   => ''
            ];
            $InstanceIdDevice = array_search($Device['serialNumber'], $InstanceIDList, true);
            $this->SendDebug('TEST', sprintf('InstanceIdDevice: %s, serial: %s, list: %s',
                                             json_encode($InstanceIdDevice, JSON_THROW_ON_ERROR), $Device['serialNumber'],
                                             json_encode($InstanceIDList, JSON_THROW_ON_ERROR)
            ),               0);
            if ($InstanceIdDevice !== false) {
                $AddDevice[ConfiguratorFields::SymconName] = IPS_GetName($InstanceIdDevice);
                $AddDevice['instanceID'] = $InstanceIdDevice; //der Parametername ist vorgegeben
                unset($InstanceIDList[$InstanceIdDevice]);
            }

            $AddDevice['create'] = [
                'moduleID'      => Guid::Device,
                'location'      => [],
                'name'          => $Device['name'],
                'configuration' => [
                    Property::HouseId      => $Device['houseId'],
                    Property::SerialNumber => $Device['serialNumber']
                ]
            ];
            $DeviceValues[]      = $AddDevice;
        }
        foreach ($InstanceIDList as $InstanceIdDevice => $serialNumber) {
            $AddDevice      = [
                'instanceID'   => $InstanceIdDevice,
                'houseId'      => IPS_GetProperty($InstanceIdDevice, Property::HouseId),
                'serialNumber' => '',
                'deviceType'   => '',
                'symconName'         => IPS_GetName($InstanceIdDevice)
            ];
            $DeviceValues[] = $AddDevice;
        }

        $Form['actions'][0]['values'] = $DeviceValues;
        $this->SendDebug('FORM', json_encode($Form, JSON_THROW_ON_ERROR), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form, JSON_THROW_ON_ERROR);
    }

    protected function FilterInstances(int $InstanceID): bool
    {
        return IPS_GetInstance($InstanceID)['ConnectionID'] === $this->ParentID;
    }

    protected function GetConfigParam(&$item1, int $InstanceID, string $ConfigParam): void
    {
        $item1 = IPS_GetProperty($InstanceID, $ConfigParam);
    }

    private function GetHouseDevices(): array
    {
        $houseDevices = [];
        $this->SendDebug(__FUNCTION__, ApiUrl::GetHouses, 0);
        $resultHouses = $this->Request(ApiUrl::GetHouses, '');
        $this->SendDebug(__FUNCTION__, sprintf('houses: %s', $resultHouses), 0);
        if ($resultHouses) {
            foreach (json_decode($resultHouses, true, 512, JSON_THROW_ON_ERROR) as $house) {
                $resultHouseDevices = $this->Request(ApiUrl::GetHouseDevices . '?houseId=' . $house['id'], '');
                $this->SendDebug(__FUNCTION__, sprintf('houseDevices: %s', $resultHouseDevices), 0);
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
        $Result = $this->SendDataToParent(ForwardData::toJson($Uri, $Params));
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
        $this->SendDebug('Filter', json_encode($InstanceIDList, JSON_THROW_ON_ERROR), 0);
        return $InstanceIDList;
    }

}
