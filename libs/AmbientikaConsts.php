<?php

declare(strict_types=1);

namespace Ambientika {

    class Guid
    {
        public const string Device           = '{8CF53BBB-FDB2-7850-79D5-42A14A8649B3}';
        public const string CloudIO          = '{C10C91F4-1843-CBE1-D974-2395A4620106}';
        public const string SendToCloud      = '{7E64E639-1446-BFDC-875D-5A4E06C59E26}';
        public const string ReceiveFromCloud = '{5E7A3B89-37B7-2AD8-3DEC-7A30F0DA095F}';
    }
}

namespace Ambientika\Device {

    class Property
    {
        public const string HouseId              = 'HouseId';
        public const string SerialNumber         = 'SerialNumber';
        public const string RefreshStateInterval = 'RefreshStateInterval';

    }
    class Timer
    {
        public const string RefreshState = 'RefreshState';
    }

    class Variables
    {
        public const string OperatingMode = 'operatingMode';
        public const string FanSpeed      = 'fanSpeed';
        public const string HumidityLevel = 'humidityLevel';
        public const string Temperature   = 'temperature';
    }
    class VariableValues
    {
        public const array OperatingMode = [
            'Off'                => 0,
            'Smart'              => 1,
            'Auto'               => 2,
            //'silent' => 2, //todo
            //'sleep' => 3,  //todo
            'Night'              => 3,
            'AwayHome'           => 4,
            'ManualHeatRecovery' => 5,
            'Surveillance'       => 6,
            'TimedExpulsion'     => 7,
            'Expulsion'          => 8,
            'Intake'             => 9,
            'MasterSlaveFlow'    => 10,
            'SlaveMasterFlow'    => 11
        ];

        public const array FanSpeed = [
            'Auto'   => 0,
            'Low'    => 1,
            'Medium' => 2,
            'High'   => 3,
        ];

    }
    class InstanceStatus
    {
        public const int SerialNumberNotFound = IS_EBASE + 1;
        public const int HouseIdNotFound      = IS_EBASE + 2;
        public const int InCloudOffline       = IS_EBASE + 7;
    }
}

namespace Ambientika\Cloud {

    use Ambientika\Guid;

    class Property
    {
        public const string Username = 'Username';
        public const string Password = 'Password';
    }
    class Attribute
    {
        public const string ServiceToken = 'ServiceToken';
    }
    class Timer
    {
        public const string RefreshState = 'RefreshState';
        public const string Reconnect    = 'Reconnect';
    }

    class ApiUrl
    {
        public const string Server          = 'https://app.ambientika.eu:4521';
        public const string Login           = '/Users/authenticate';
        public const string ChangeMode      = '/device/change-mode';
        public const string GetDeviceStatus = '/Device/device-status';
        public const string GetHouseDevices = '/House/house-devices';
        public const string GetHouses       = '/House/houses';

        public static function GetApiUrl(string $Path): string
        {
            return self::Server . $Path;
        }
    }

    class ApiData
    {
        public static function getLoginPayload(string $Username, string $Password): array
        {
            return [
                'username' => $Username,
                'password' => $Password
            ];
        }
    }
    class ForwardData
    {
        public const string DataId = 'DataID';
        public const string Uri    = 'Uri';
        public const string Params = 'Params';

        public static function toJson(string $Uri, string $Params = ''): string
        {
            return json_encode([
                                   self::DataId => Guid::SendToCloud,
                                   self::Uri    => $Uri,
                                   self::Params => $Params
                               ],
                               JSON_THROW_ON_ERROR);
        }

        public static function fromJson(string $JSONString): array
        {
            $Data = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);

            if (!isset($Data[self::Uri]) || !isset($Data[self::Params])) {
                throw new \InvalidArgumentException('Invalid JSON data: Missing required keys.');
            }

            return ['uri' => $Data[self::Uri], 'params' => $Data[self::Params]];
        }
    }
}

namespace Ambientika\Configurator {

    class ConfiguratorFields
    {
        public const string HouseId      = 'houseId';
        public const string SerialNumber = 'serialNumber';
        public const string DeviceType   = 'deviceType';
        public const string CloudName    = 'cloudName';
        public const string SymconName   = 'symconName';
        public const string InstanceId   = 'instanceId';
    }
}
