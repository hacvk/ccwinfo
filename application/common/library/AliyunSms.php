<?php

namespace app\common\library;

use app\common\model\SmsLog;
use think\Config;

/**
 * 阿里云短信服务类
 */
class AliyunSms
{
    protected static $config = null;

    protected static function getConfig($key = null, $default = '')
    {
        if (self::$config === null) {
            self::$config = [
                'access_key_id' => Config::get('site.aliyun_sms_access_key_id') ?: '',
                'access_key_secret' => Config::get('site.aliyun_sms_access_key_secret') ?: '',
                'sign_name' => Config::get('site.aliyun_sms_sign_name') ?: '',
                'region_id' => Config::get('site.aliyun_sms_region_id') ?: 'cn-hangzhou',
                'endpoint' => Config::get('site.aliyun_sms_endpoint') ?: 'dysmsapi.aliyuncs.com',
            ];
        }
        if ($key === null) {
            return self::$config;
        }
        return self::$config[$key] ?? $default;
    }

    public static function isConfigured()
    {
        return self::getConfig('access_key_id') !== '' && self::getConfig('access_key_secret') !== '';
    }

    public static function send($phone, $templateCode, $params = [], $scene = '')
    {
        $config = self::getConfig();
        if (!self::isConfigured()) {
            return ['success' => false, 'error' => '阿里云短信未配置'];
        }

        $signName = $config['sign_name'];
        if (empty($signName)) {
            return ['success' => false, 'error' => '短信签名未配置'];
        }

        $requestPayload = json_encode([
            'PhoneNumbers' => $phone,
            'SignName' => $signName,
            'TemplateCode' => $templateCode,
            'TemplateParam' => $params,
        ], JSON_UNESCAPED_UNICODE);

        try {
            $result = self::doSend($phone, $signName, $templateCode, $params, $config);
            $success = isset($result['Code']) && $result['Code'] === 'OK';
            $bizId = $result['BizId'] ?? '';
            $error = $success ? '' : ($result['Message'] ?? '未知错误');

            self::logSms($phone, $templateCode, $scene, 'aliyun', $success, $bizId, $requestPayload, $error);

            return ['success' => $success, 'error' => $error, 'biz_id' => $bizId];
        } catch (\Exception $e) {
            $error = $e->getMessage();
            self::logSms($phone, $templateCode, $scene, 'aliyun', false, '', $requestPayload, $error);
            return ['success' => false, 'error' => $error];
        }
    }

    protected static function doSend($phone, $signName, $templateCode, $params, $config)
    {
        $accessKeyId = $config['access_key_id'];
        $accessKeySecret = $config['access_key_secret'];
        $endpoint = $config['endpoint'];

        $apiParams = [
            'PhoneNumbers' => $phone,
            'SignName' => $signName,
            'TemplateCode' => $templateCode,
            'TemplateParam' => json_encode($params),
            'Action' => 'SendSms',
            'Version' => '2017-05-25',
            'Format' => 'JSON',
            'RegionId' => $config['region_id'],
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureVersion' => '1.0',
            'SignatureNonce' => uniqid(mt_rand(0, 999999999) . ''),
            'AccessKeyId' => $accessKeyId,
        ];

        ksort($apiParams);

        $canonicalizedQueryString = '';
        foreach ($apiParams as $key => $value) {
            $canonicalizedQueryString .= '&' . self::percentEncode($key) . '=' . self::percentEncode($value);
        }

        $stringToSign = 'GET&%2F&' . self::percentEncode(substr($canonicalizedQueryString, 1));
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret . '&', true));
        $signature = self::percentEncode($signature);

        $url = 'https://' . $endpoint . '/?Signature=' . $signature . $canonicalizedQueryString;

        $response = self::httpGet($url);
        return json_decode($response, true) ?: [];
    }

    protected static function percentEncode($str)
    {
        $res = urlencode($str);
        $res = preg_replace('/\+/', '%20', $res);
        $res = preg_replace('/\*/', '%2A', $res);
        $res = preg_replace('/%7E/', '~', $res);
        return $res;
    }

    protected static function httpGet($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    protected static function logSms($phone, $templateCode, $scene, $channel, $success, $bizId, $payload, $error)
    {
        try {
            SmsLog::create([
                'phone' => $phone,
                'template_code' => $templateCode,
                'scene' => $scene,
                'channel' => $channel,
                'success' => $success ? 1 : 0,
                'provider_biz_id' => $bizId,
                'request_payload' => $payload,
                'error' => $error,
                'createtime' => time(),
            ]);
        } catch (\Exception $e) {
        }
    }

    public static function maskPhone($phone)
    {
        $phone = trim((string)$phone);
        if (strlen($phone) >= 11) {
            return substr($phone, 0, 3) . '****' . substr($phone, -4);
        }
        if (strlen($phone) >= 7) {
            return substr($phone, 0, 3) . '****';
        }
        return $phone ?: '-';
    }

    public static function maskCode($code)
    {
        $code = trim((string)$code);
        if (strlen($code) <= 2) {
            return $code;
        }
        return substr($code, 0, 1) . str_repeat('*', strlen($code) - 2) . substr($code, -1);
    }
}
