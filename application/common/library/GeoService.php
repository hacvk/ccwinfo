<?php

namespace app\common\library;

use fast\Http;
use think\Cache;

/**
 * 电话/IP归属地解析服务
 * 策略：本地优先，在线兜底，结果缓存
 */
class GeoService
{
    const UNKNOWN_REGION = '未知归属地';
    const SUCCESS_TTL = 2592000; // 30天
    const FAIL_TTL = 600; // 10分钟

    private static $mobileSegments = null;
    private static $ipSegments = null;

    public static function resolvePhoneRegion(string $phone): array
    {
        $phone = trim($phone);
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'invalid'];
        }

        $prefix = substr($phone, 0, 7);
        $cacheKey = 'geo:phone:' . $prefix;
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['region'])) {
            return $cached;
        }

        $local = self::resolvePhoneRegionFromLocal($prefix);
        if (!empty($local['ok'])) {
            Cache::set($cacheKey, $local, self::SUCCESS_TTL);
            return $local;
        }

        $online = self::resolvePhoneRegionOnline($phone);
        Cache::set($cacheKey, $online, !empty($online['ok']) ? self::SUCCESS_TTL : self::FAIL_TTL);
        return $online;
    }

    public static function resolveIpRegion(string $ip): array
    {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'invalid'];
        }

        $cacheKey = 'geo:ip:' . md5($ip);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['region'])) {
            return $cached;
        }

        $local = self::resolveIpRegionFromLocal($ip);
        if (!empty($local['ok'])) {
            Cache::set($cacheKey, $local, self::SUCCESS_TTL);
            return $local;
        }

        $online = self::resolveIpRegionOnline($ip);
        Cache::set($cacheKey, $online, !empty($online['ok']) ? self::SUCCESS_TTL : self::FAIL_TTL);
        return $online;
    }

    public static function maskIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return '-';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return $parts[0] . '.' . $parts[1] . '.*.*';
            }
            return '-';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ip);
            if ($packed === false || strlen($packed) !== 16) {
                return '-';
            }
            $hex = bin2hex($packed);
            $groups = str_split($hex, 4);
            return strtolower(implode(':', array_slice($groups, 0, 4)) . ':*:*:*:*');
        }

        return '-';
    }

    private static function resolvePhoneRegionFromLocal(string $prefix): array
    {
        try {
            $segments = self::loadMobileSegments();
            if (!isset($segments[$prefix])) {
                return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'local:miss'];
            }

            $region = self::normalizeRegionEntry($segments[$prefix]);
            if ($region === '') {
                return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'local:invalid'];
            }

            return ['ok' => true, 'region' => $region, 'source' => 'local:mobile_segments'];
        } catch (\Throwable $e) {
            SentryService::warning('手机号归属地本地解析异常', [
                'phone_prefix' => $prefix,
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'local:error'];
        }
    }

    private static function resolvePhoneRegionOnline(string $phone): array
    {
        $url = 'https://cx.shouji.360.cn/phonearea.php';
        $request = Http::sendRequest($url, ['number' => $phone], 'GET', [
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 2,
        ]);

        if (empty($request['ret'])) {
            SentryService::warning('手机号归属地在线兜底失败', [
                'phone' => self::maskPhone($phone),
                'error' => (string)($request['msg'] ?? 'request_failed'),
            ]);
            return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'online:fail'];
        }

        $payload = self::extractJsonPayload((string)($request['msg'] ?? ''));
        $data = self::decodeJsonPayload($payload);
        if (!is_array($data)) {
            SentryService::warning('手机号归属地在线兜底返回异常', [
                'phone' => self::maskPhone($phone),
                'reason' => 'invalid_json',
            ]);
            return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'online:invalid_json'];
        }

        $item = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
        $region = self::joinRegionParts([
            (string)($item['province'] ?? ''),
            (string)($item['city'] ?? ''),
            (string)($item['sp'] ?? ''),
        ]);

        if ($region === '') {
            SentryService::warning('手机号归属地在线兜底未命中', [
                'phone' => self::maskPhone($phone),
                'reason' => 'empty_region',
            ]);
            return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'online:empty'];
        }

        return ['ok' => true, 'region' => $region, 'source' => 'online:360'];
    }

    private static function resolveIpRegionFromLocal(string $ip): array
    {
        $result = self::resolveIpRegionFromXdb($ip);
        if (!empty($result['ok'])) {
            return $result;
        }

        try {
            $segments = self::loadIpSegments();
            foreach ($segments as $cidr => $regionText) {
                if (self::ipInCidr($ip, (string)$cidr)) {
                    $region = self::normalizeRegionEntry($regionText);
                    if ($region !== '') {
                        return ['ok' => true, 'region' => $region, 'source' => 'local:ip_segments'];
                    }
                }
            }
        } catch (\Throwable $e) {
            SentryService::warning('IP归属地本地段匹配异常', [
                'ip' => self::maskIp($ip),
                'error' => $e->getMessage(),
            ]);
        }

        return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'local:miss'];
    }

    private static function resolveIpRegionFromXdb(string $ip): array
    {
        $xdbPath = APP_PATH . 'extra' . DS . 'geo' . DS . 'ip2region.xdb';
        if (!is_file($xdbPath) || !class_exists('\\Ip2Region\\XdbSearcher')) {
            return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'local:xdb_missing'];
        }

        try {
            $searcherClass = '\\Ip2Region\\XdbSearcher';
            if (!method_exists($searcherClass, 'newWithFileOnly')) {
                return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'local:xdb_unavailable'];
            }
            $searcher = $searcherClass::newWithFileOnly($xdbPath);
            if (!is_object($searcher) || !method_exists($searcher, 'search')) {
                return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'local:xdb_invalid'];
            }

            $raw = (string)$searcher->search($ip);
            $region = self::normalizeIp2RegionText($raw);
            if ($region === '') {
                return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'local:xdb_empty'];
            }

            return ['ok' => true, 'region' => $region, 'source' => 'local:ip2region_xdb'];
        } catch (\Throwable $e) {
            SentryService::warning('IP归属地XDB解析异常', [
                'ip' => self::maskIp($ip),
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'local:xdb_error'];
        }
    }

    private static function resolveIpRegionOnline(string $ip): array
    {
        $url = 'https://whois.pconline.com.cn/ipJson.jsp';
        $request = Http::sendRequest($url, ['ip' => $ip, 'json' => 'true'], 'GET', [
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 2,
        ]);

        if (empty($request['ret'])) {
            SentryService::warning('IP归属地在线兜底失败', [
                'ip' => self::maskIp($ip),
                'error' => (string)($request['msg'] ?? 'request_failed'),
            ]);
            return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'online:fail'];
        }

        $payload = self::extractJsonPayload((string)($request['msg'] ?? ''));
        $data = self::decodeJsonPayload($payload, true);
        if (!is_array($data)) {
            SentryService::warning('IP归属地在线兜底返回异常', [
                'ip' => self::maskIp($ip),
                'reason' => 'invalid_json',
            ]);
            return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'online:invalid_json'];
        }

        $region = self::joinRegionParts([
            (string)($data['pro'] ?? ''),
            (string)($data['city'] ?? ''),
        ]);
        if ($region === '') {
            $region = trim((string)($data['addr'] ?? ''));
        }

        if ($region === '') {
            SentryService::warning('IP归属地在线兜底未命中', [
                'ip' => self::maskIp($ip),
                'reason' => 'empty_region',
            ]);
            return ['ok' => false, 'region' => self::UNKNOWN_REGION, 'source' => 'online:empty'];
        }

        return ['ok' => true, 'region' => $region, 'source' => 'online:pconline'];
    }

    private static function normalizeRegionEntry($entry): string
    {
        if (is_string($entry)) {
            return trim($entry);
        }
        if (!is_array($entry)) {
            return '';
        }
        if (!empty($entry['region'])) {
            return trim((string)$entry['region']);
        }
        return self::joinRegionParts([
            (string)($entry['province'] ?? ''),
            (string)($entry['city'] ?? ''),
            (string)($entry['isp'] ?? ''),
        ]);
    }

    private static function normalizeIp2RegionText(string $raw): string
    {
        if ($raw === '') {
            return '';
        }
        $parts = explode('|', $raw);
        $filtered = [];
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part === '' || $part === '0') {
                continue;
            }
            $filtered[] = $part;
        }
        return self::joinRegionParts($filtered);
    }

    private static function joinRegionParts(array $parts): string
    {
        $result = [];
        foreach ($parts as $part) {
            $text = trim((string)$part);
            if ($text === '') {
                continue;
            }
            if (!in_array($text, $result, true)) {
                $result[] = $text;
            }
        }
        return implode(' ', $result);
    }

    private static function extractJsonPayload(string $payload): string
    {
        $payload = trim($payload);
        if ($payload === '') {
            return '';
        }
        $start = strpos($payload, '{');
        $end = strrpos($payload, '}');
        if ($start === false || $end === false || $end < $start) {
            return '';
        }
        return substr($payload, $start, $end - $start + 1);
    }

    private static function decodeJsonPayload(string $payload, bool $tryGbk = false): ?array
    {
        if ($payload === '') {
            return null;
        }

        $payload = ltrim($payload, "\xEF\xBB\xBF");
        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (!$tryGbk) {
            return null;
        }

        $converted = @iconv('GBK', 'UTF-8//IGNORE', $payload);
        if (!is_string($converted) || $converted === '') {
            return null;
        }
        $decoded = json_decode($converted, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function loadMobileSegments(): array
    {
        if (is_array(self::$mobileSegments)) {
            return self::$mobileSegments;
        }
        self::$mobileSegments = [];

        $path = APP_PATH . 'extra' . DS . 'geo' . DS . 'mobile_segments.php';
        if (!is_file($path)) {
            return self::$mobileSegments;
        }

        $data = include $path;
        if (is_array($data)) {
            self::$mobileSegments = $data;
        }

        return self::$mobileSegments;
    }

    private static function loadIpSegments(): array
    {
        if (is_array(self::$ipSegments)) {
            return self::$ipSegments;
        }
        self::$ipSegments = [];

        $path = APP_PATH . 'extra' . DS . 'geo' . DS . 'ip_segments.php';
        if (!is_file($path)) {
            return self::$ipSegments;
        }

        $data = include $path;
        if (is_array($data)) {
            self::$ipSegments = $data;
        }

        return self::$ipSegments;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $cidr = trim($cidr);
        if ($cidr === '') {
            return false;
        }

        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }

        list($subnet, $maskBits) = explode('/', $cidr, 2);
        if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $maskBits = (int)$maskBits;
        if ($maskBits < 0 || $maskBits > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = $maskBits === 0 ? 0 : (-1 << (32 - $maskBits));
        $mask = $mask & 0xFFFFFFFF;

        return (($ipLong & $mask) === ($subnetLong & $mask));
    }

    private static function maskPhone(string $phone): string
    {
        $phone = trim($phone);
        if (strlen($phone) >= 11) {
            return substr($phone, 0, 3) . '****' . substr($phone, -4);
        }
        if (strlen($phone) >= 7) {
            return substr($phone, 0, 3) . '****';
        }
        return $phone === '' ? '-' : $phone;
    }
}
