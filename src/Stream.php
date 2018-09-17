<?php
namespace luoyy\Wowza;

use Exception;

class Stream
{
    private $api_url = 'https://api.cloud.wowza.com/api/%s';

    public $disable_authentication = true;
    public $broadcast_location = 'us_west_california';
    public $version = 'v1.2';

    const STREAM_TARGETS = 'stream_targets';

    private $header = [
        'DNT' => '1',
        'Pragma' => 'no-cache',
        'Cache-Control' => 'no-cache',
        'wsc-api-key' => '',
        'wsc-access-key' => '',
        'Content-Type' => 'application/json',
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.87 Safari/537.36'
    ];
    public function __construct($config = [])
    {
        if (isset($config['disable_authentication'])) {
            $this->disable_authentication = $config['disable_authentication'];
        }
        if (isset($config['broadcast_location'])) {
            $this->broadcast_location = $config['broadcast_location'];
        }
        if (!isset($config['api_key']) || $config['api_key'] == '') {
            throw new Exception("api_key cannot be empty");
        }
        $this->header['wsc-api-key'] = $config['api_key'];
        if (!isset($config['access_key']) || $config['access_key'] == '') {
            throw new Exception("access_key cannot be empty");
        }
        $this->header['wsc-access-key'] = $config['access_key'];
        if (isset($config['debug']) && !!$config['debug']) {
            $this->api_url = 'https://api-sandbox.cloud.wowza.com/api/%s';
            $this->broadcast_location = 'us_west_california';
        }
        $this->api_url = sprintf($this->api_url, (!empty($config['version']) ? $config['version'] : $this->version));
    }
    public function request($option = [])
    {
        $option += ['url' => null, 'method' => 'GET', 'gzip' => true, 'data' => null, 'header' => []];
        $option['header'] += $this->header;
        $curl = curl_init();
        if ($option['gzip']) {
            $option['header']['accept-encoding'] = 'gzip, deflate, identity';
            curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate,identity');
        }
        if ((strtoupper($option['method']) === 'GET') && (!is_null($option['data']) && $option['data'] != '')) {
            $option['url'] = vsprintf('%s%s%s', [$option['url'], (strpos($option['url'], '?') !== false ? '&' : '?'), is_array($option['data']) ? http_build_query($option['data']) : $option['data']]);
        }
        foreach ($option['header'] as $key => &$value) {
            $value = is_int($key) ? $value : $key . ': ' . $value;
        }
        curl_setopt_array($curl, [CURLOPT_URL => $option['url'], CURLOPT_CUSTOMREQUEST => $option['method'], CURLOPT_HTTPHEADER => $option['header'], CURLOPT_AUTOREFERER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false, CURLOPT_NOBODY => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_CAINFO => __DIR__ . '/cacert.pem', CURLOPT_SSL_VERIFYHOST => 2]);
        if (in_array(strtoupper($option['method']), ['POST', 'PATCH', 'PUT']) && !is_null($option['data'])) {
            $post = is_array($option['data']) ? http_build_query($option['data']) : $option['data'];
            curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post]);
        }
        list($data, $errno, $error) = [(object) ['body' => curl_exec($curl), 'header' => curl_getinfo($curl), 'http_code' => curl_getinfo($curl, CURLINFO_HTTP_CODE)], curl_errno($curl), curl_error($curl), curl_close($curl)];
        if ($errno !== 0) {
            throw new Exception($error, $errno);
        }
        return $data;
    }

    /**
     * @param $url
     * @param array $header
     * @param $cookie
     * @return mixed
     */
    public function get($url, $header = [])
    {
        return $this->request(['url' => $url, 'method' => 'GET', 'header' => $header]);
    }
    /**
     * @param $url
     * @param $post
     * @param array $header
     * @param $cookie
     * @return mixed
     */
    public function post($url, $data = null, $header = [])
    {
        return $this->request(['url' => $url, 'method' => 'POST', 'data' => $data, 'header' => $header]);
    }

    /**
     * [create 创建流]
     * @Author    ZiShang520@gmail.com
     * @DateTime  2018-09-05T10:09:12+0800
     * @copyright (c) ZiShang520 All Rights Reserved
     * @param     [type] $name [description]
     * @param     boolean $fallback [description]
     * @return    [type] [description]
     */
    public function create($name = null, $fallback = false)
    {
        $name = is_null($name) ? md5(uniqid()) : $name;
        $response = $this->post(sprintf('%s/%s', $this->api_url, self::STREAM_TARGETS), json_encode([
            'stream_target' => ['name' => $name, "provider" => "wowza", "type" => "UltraLowLatencyStreamTarget", "location" => $this->broadcast_location, "enable_hls" => true, 'source_delivery_method' => 'push', 'enabled' => true]]));
        $body = json_decode($response->body, true);
        if ($response->http_code !== 201) {
            if (!empty($body)) {
                throw new Exception($body['meta']['message']);
            } else {
                throw new Exception('Api [create] Request Error');
            }
        }
        return $body;
    }

    /**
     * [all 获取所有数据]
     * @Author    ZiShang520@gmail.com
     * @DateTime  2018-09-05T14:13:59+0800
     * @copyright (c) ZiShang520 All Rights Reserved
     * @return    [type] [description]
     */
    public function all()
    {
        $response = $this->get(sprintf('%s/%s', $this->api_url, self::STREAM_TARGETS));
        $body = json_decode($response->body, true);
        if ($response->http_code !== 200) {
            if (!empty($body)) {
                throw new Exception($body['meta']['message']);
            } else {
                throw new Exception('Api [all] Request Error');
            }
        }
        return $body;
    }
    /**
     * [info 获取流信息]
     * @Author    ZiShang520@gmail.com
     * @DateTime  2018-09-05T10:10:07+0800
     * @copyright (c) ZiShang520 All Rights Reserved
     * @param     [type] $stream_id [description]
     * @return    [type] [description]
     */
    public function self($stream_id = null)
    {
        if (is_null($stream_id)) {
            throw new Exception("stream_id cannot be empty");
        }
        $response = $this->get(sprintf('%s/%s/%s', $this->api_url, self::STREAM_TARGETS, $stream_id));
        $body = json_decode($response->body, true);
        if ($response->http_code !== 200) {
            if (!empty($body)) {
                throw new Exception($body['meta']['message']);
            } else {
                throw new Exception('Api [self] Request Error');
            }
        }
        return $body;
    }

    /**
     * [update 更新数据]
     * @Author    ZiShang520@gmail.com
     * @DateTime  2018-09-05T13:51:52+0800
     * @copyright (c) ZiShang520 All Rights Reserved
     * @param     [type] $stream_id [description]
     * @param     array $update_data [description]
     * @return    [type] [description]
     */
    public function update($stream_id = null, $update_data = [])
    {
        if (is_null($stream_id)) {
            throw new Exception("stream_id cannot be empty");
        }
        $response = $this->request(['url' => sprintf('%s/%s/%s', $this->api_url, self::STREAM_TARGETS, $stream_id), 'method' => 'PATCH', 'data' => json_encode(['live_stream' => $update_data])]);
        $body = json_decode($response->body, true);
        if ($response->http_code !== 200) {
            if (!empty($body)) {
                throw new Exception($body['meta']['message']);
            } else {
                throw new Exception('Api [update] Request Error');
            }
        }
        return $body;
    }

    /**
     * [regenerate_connection_code 重新生成连接代码]
     * @Author    ZiShang520@gmail.com
     * @DateTime  2018-09-05T14:00:26+0800
     * @copyright (c) ZiShang520 All Rights Reserved
     * @param     [type] $stream_id [description]
     * @return    [type] [description]
     */
    public function regenerate_connection_code($stream_id = null)
    {
        if (is_null($stream_id)) {
            throw new Exception("stream_id cannot be empty");
        }
        $response = $this->request(['url' => sprintf('%s/%s/%s/%s', $this->api_url, self::STREAM_TARGETS, $stream_id, 'regenerate_connection_code'), 'method' => 'PUT']);
        $body = json_decode($response->body, true);
        if ($response->http_code !== 200) {
            if (!empty($body)) {
                throw new Exception($body['meta']['message']);
            } else {
                throw new Exception('Api [regenerate_connection_code] Request Error');
            }
        }
        return $body;
    }
    /**
     * [delete 删除流通道]
     * @Author    ZiShang520@gmail.com
     * @DateTime  2018-09-05T14:02:20+0800
     * @copyright (c) ZiShang520 All Rights Reserved
     * @param     [type] $stream_id [description]
     * @return    [type] [description]
     */
    public function delete($stream_id = null)
    {
        if (is_null($stream_id)) {
            throw new Exception("stream_id cannot be empty");
        }
        $response = $this->request(['url' => sprintf('%s/%s/%s', $this->api_url, self::STREAM_TARGETS, $stream_id), 'method' => 'DELETE']);
        if ($response->http_code !== 204) {
            if (!empty($body)) {
                throw new Exception($body['meta']['message']);
            } else {
                throw new Exception('Api [delete] Request Error');
            }
        }
        return true;
    }
}