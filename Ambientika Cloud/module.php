<?php

declare(strict_types=1);

use Ambientika\Cloud\ApiData;
use Ambientika\Cloud\ApiUrl;
use Ambientika\Cloud\ForwardData;
use Ambientika\Cloud\Property;
use Ambientika\Cloud\Timer;

require_once dirname(__DIR__) . '/libs/AmbientikaConsts.php';

class AmbientikaCloud extends IPSModule
{
    private const array CurlErrorCodes = [
        0  => 'UNKNOWN ERROR',
        1  => 'CURLE_UNSUPPORTED_PROTOCOL',
        2  => 'CURLE_FAILED_INIT',
        3  => 'CURLE_URL_MALFORMAT',
        4  => 'CURLE_URL_MALFORMAT_USER',
        5  => 'CURLE_COULDNT_RESOLVE_PROXY',
        6  => 'CURLE_COULDNT_RESOLVE_HOST',
        7  => 'CURLE_COULDNT_CONNECT',
        8  => 'CURLE_FTP_WEIRD_SERVER_REPLY',
        9  => 'CURLE_REMOTE_ACCESS_DENIED',
        11 => 'CURLE_FTP_WEIRD_PASS_REPLY',
        13 => 'CURLE_FTP_WEIRD_PASV_REPLY',
        14 => 'CURLE_FTP_WEIRD_227_FORMAT',
        15 => 'CURLE_FTP_CANT_GET_HOST',
        17 => 'CURLE_FTP_COULDNT_SET_TYPE',
        18 => 'CURLE_PARTIAL_FILE',
        19 => 'CURLE_FTP_COULDNT_RETR_FILE',
        21 => 'CURLE_QUOTE_ERROR',
        22 => 'CURLE_HTTP_RETURNED_ERROR',
        23 => 'CURLE_WRITE_ERROR',
        25 => 'CURLE_UPLOAD_FAILED',
        26 => 'CURLE_READ_ERROR',
        27 => 'CURLE_OUT_OF_MEMORY',
        28 => 'CURLE_OPERATION_TIMEDOUT',
        30 => 'CURLE_FTP_PORT_FAILED',
        31 => 'CURLE_FTP_COULDNT_USE_REST',
        33 => 'CURLE_RANGE_ERROR',
        34 => 'CURLE_HTTP_POST_ERROR',
        35 => 'CURLE_SSL_CONNECT_ERROR',
        36 => 'CURLE_BAD_DOWNLOAD_RESUME',
        37 => 'CURLE_FILE_COULDNT_READ_FILE',
        38 => 'CURLE_LDAP_CANNOT_BIND',
        39 => 'CURLE_LDAP_SEARCH_FAILED',
        41 => 'CURLE_FUNCTION_NOT_FOUND',
        42 => 'CURLE_ABORTED_BY_CALLBACK',
        43 => 'CURLE_BAD_FUNCTION_ARGUMENT',
        45 => 'CURLE_INTERFACE_FAILED',
        47 => 'CURLE_TOO_MANY_REDIRECTS',
        48 => 'CURLE_UNKNOWN_TELNET_OPTION',
        49 => 'CURLE_TELNET_OPTION_SYNTAX',
        51 => 'CURLE_PEER_FAILED_VERIFICATION',
        52 => 'CURLE_GOT_NOTHING',
        53 => 'CURLE_SSL_ENGINE_NOTFOUND',
        54 => 'CURLE_SSL_ENGINE_SETFAILED',
        55 => 'CURLE_SEND_ERROR',
        56 => 'CURLE_RECV_ERROR',
        58 => 'CURLE_SSL_CERTPROBLEM',
        59 => 'CURLE_SSL_CIPHER',
        60 => 'CURLE_SSL_CACERT',
        61 => 'CURLE_BAD_CONTENT_ENCODING',
        62 => 'CURLE_LDAP_INVALID_URL',
        63 => 'CURLE_FILESIZE_EXCEEDED',
        64 => 'CURLE_USE_SSL_FAILED',
        65 => 'CURLE_SEND_FAIL_REWIND',
        66 => 'CURLE_SSL_ENGINE_INITFAILED',
        67 => 'CURLE_LOGIN_DENIED',
        68 => 'CURLE_TFTP_NOTFOUND',
        69 => 'CURLE_TFTP_PERM',
        70 => 'CURLE_REMOTE_DISK_FULL',
        71 => 'CURLE_TFTP_ILLEGAL',
        72 => 'CURLE_TFTP_UNKNOWNID',
        73 => 'CURLE_REMOTE_FILE_EXISTS',
        74 => 'CURLE_TFTP_NOSUCHUSER',
        75 => 'CURLE_CONV_FAILED',
        76 => 'CURLE_CONV_REQD',
        77 => 'CURLE_SSL_CACERT_BADFILE',
        78 => 'CURLE_REMOTE_FILE_NOT_FOUND',
        79 => 'CURLE_SSH',
        80 => 'CURLE_SSL_SHUTDOWN_FAILED',
        81 => 'CURLE_AGAIN',
        82 => 'CURLE_SSL_CRL_BADFILE',
        83 => 'CURLE_SSL_ISSUER_ERROR',
        84 => 'CURLE_FTP_PRET_FAILED',
        85 => 'CURLE_RTSP_CSEQ_ERROR',
        86 => 'CURLE_RTSP_SESSION_ERROR',
        87 => 'CURLE_FTP_BAD_FILE_LIST',
        88 => 'CURLE_CHUNK_FAILED'
    ];

    private const array HttpError = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        500 => 'Server error'
    ];

    private const int CURL_TIMEOUT_MS = 5000;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString(Property::Username, '');
        $this->RegisterPropertyString(Property::Password, '');

        $this->RegisterAttributeString(\Ambientika\Cloud\Attribute::ServiceToken, '');

        $this->RegisterTimer(
            Timer::Reconnect,
            0,
            'IPS_RequestAction(' . $this->InstanceID . ',"' . Timer::Reconnect . '",true);'
        );

    }


    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->SetSummary($this->ReadPropertyString(Property::Username));
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }

        if (($this->ReadPropertyString(Property::Username) !== '') && ($this->ReadPropertyString(Property::Password) !== '' )){
            $this->updateServiceToken();
        } else {
            $this->SetStatus(IS_INACTIVE);
        }

        $this->SetTimerInterval(Timer::Reconnect, 1000 * 60 * 60 * 24);

    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message === IPS_KERNELSTARTED){
                $this->KernelReady();
        }
    }

    public function RequestAction($Ident, $Value): void
    {
        if ($Ident === Timer::Reconnect) {
            $this->updateServiceToken();
        }
    }

    private function KernelReady(): void
    {
        $this->UnregisterMessage(0, IPS_KERNELSTARTED);
        $this->updateServiceToken();
    }

    public function ForwardData($JSONString): string
    {
        ['uri' => $uri, 'params' => $params] = ForwardData::fromJson($JSONString);
        $result = $this->sendRequest($uri, $params);
        return is_null($result) ? '' : $result;
    }

    public function sendRequest(string $path, string $paramsString): ?string
    {
        $url = ApiUrl::GetApiUrl($path);
        if ($paramsString !== '') {
            $this->SendDebug('Request Url', sprintf('url: %s, params: %s', $url, $paramsString), 0);
        } else {
            $this->SendDebug('Request Url', sprintf('url: %s', $url), 0);
        }

        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->ReadAttributeString(\Ambientika\Cloud\Attribute::ServiceToken)],
            CURLOPT_TIMEOUT_MS => self::CURL_TIMEOUT_MS
        ];

        if ($paramsString !== '') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $paramsString;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch);
        curl_close($ch);
        $this->SendDebug('Response (' . $httpCode . ')', $response, 0);

        if ($httpCode === 0) {
            $this->SendDebug('CURL Error', self::CurlErrorCodes[$curlError] ?? 'Unknown CURL Error', 0);
            return null;
        }

        if (isset(self::HttpError[$httpCode])) {
            $this->SendDebug('HTTP Error', sprintf('Code: %d, Message: %s', $httpCode, self::HttpError[$httpCode]), 0);
            return null;
        }

        return $response;
    }

    private function updateServiceToken(): void
    {
        $serviceToken = $this->login();
        $this->WriteAttributeString(\Ambientika\Cloud\Attribute::ServiceToken, $serviceToken);
        $this->SendDebug(__FUNCTION__, 'ServiceToken: ' . $serviceToken, 0);
        if ($serviceToken === '') {
            $this->SendDebug('ERROR Cloud', 'could not fetch token', 0);
            $this->SetStatus(IS_EBASE + 1);
            return;
        }
        $this->SetStatus(IS_ACTIVE);
    }

    private function login(): string
    {
        $postFields = ApiData::getLoginPayload(
            $this->ReadPropertyString(Property::Username),
            $this->ReadPropertyString(Property::Password)
        );

        $url = ApiUrl::GetApiUrl(ApiUrl::Login);
        $this->SendDebug('Cloud Request', $url, 0);

        $ch = $this->initializeCurl($url, $postFields);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $this->SendDebug('Cloud Response (' . $httpCode . ')', $response, 0);

        if ($httpCode !== 200 || !$response) {
            return '';
        }

        $responseParts = explode("\r\n\r\n", $response);
        array_shift($responseParts);
        $responseBody = implode("\r\n\r\n", $responseParts);

        $this->SendDebug('Cloud Body (' . $httpCode . ')', $responseBody, 0);

        $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

        return $data['jwtToken'];
    }

    private function initializeCurl(string $url, array $postFields): CurlHandle|false
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json', // JSON-Header spezifizieren
            'Accept: application/json'        // Angabe, dass JSON erwartet wird
        ]);
        curl_setopt($ch, CURLOPT_HEADER, ["Content-Type: application/json"]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields, JSON_THROW_ON_ERROR));
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, self::CURL_TIMEOUT_MS);

        return $ch;
    }

}