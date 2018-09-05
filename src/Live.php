<?php
namespace luoyy\Wowza;

use Exception;

class Live
{
    private $api_url = 'https://api.cloud.wowza.com/api/%s';

    public $disable_authentication = true;
    public $broadcast_location = 'us_west_california';
    public $version = 'v1.2';

    const LIVE_STREAMS = 'live_streams';

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
        $option += ['url' => null, 'method' => 'GET', 'data' => null, 'header' => []];
        $option['header'] += $this->header;
        foreach ($option['header'] as $key => &$value) {
            $value = $key . ': ' . $value;
        }
        if ((strtoupper($option['method']) === 'GET') && (!is_null($option['data']) && $option['data'] != '')) {
            $option['url'] = vsprintf('%s%s%s', [$option['url'], (strpos($option['url'], '?') !== false ? '&' : '?'), is_array($option['data']) ? http_build_query($option['data']) : $option['data']]);
        }
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $option['url'], CURLOPT_CUSTOMREQUEST => $option['method'], CURLOPT_HTTPHEADER => $option['header'], CURLOPT_AUTOREFERER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false, CURLOPT_NOBODY => false, CURLOPT_ENCODING => "gzip", CURLOPT_SSL_VERIFYPEER => true, CURLOPT_CAINFO => __DIR__ . '/cacert.pem', CURLOPT_SSL_VERIFYHOST => 2]);
        if (in_array(strtoupper($option['method']), ['POST', 'PATCH', 'PUT']) && !is_null($option['data'])) {
            $post = is_array($option['data']) ? http_build_query($option['data']) : $option['data'];
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post]);
        }
        list($data, $errno, $error) = [(object) ['body' => curl_exec($ch), 'header' => curl_getinfo($ch), 'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE)], curl_errno($ch), curl_error($ch), curl_close($ch)];
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
        $response = $this->post(sprintf('%s/%s', $this->api_url, self::LIVE_STREAMS), json_encode([
            'live_stream' => ['name' => $name, "delivery_protocols" => "rtmp", "transcoder_type" => "transcoded", "billing_mode" => "pay_as_you_go", "broadcast_location" => $this->broadcast_location, "encoder" => "other_rtmp", "delivery_method" => "push", "disable_authentication" => $this->disable_authentication, "video_fallback" => $fallback, "aspect_ratio_width" => 720, "aspect_ratio_height" => 1280]]));
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
        $response = $this->get(sprintf('%s/%s', $this->api_url, self::LIVE_STREAMS));
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
        $response = $this->get(sprintf('%s/%s/%s', $this->api_url, self::LIVE_STREAMS, $stream_id));
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
        $response = $this->request(['url' => sprintf('%s/%s/%s', $this->api_url, self::LIVE_STREAMS, $stream_id), 'method' => 'PATCH', 'data' => json_encode(['live_stream' => array_intersect_key($update_data, ['aspect_ratio_height' => 1080, 'aspect_ratio_width' => 1920, 'encoder' => 'wowza_gocoder', 'name' => 'My Live Stream', 'closed_caption_type' => 'cea', 'delivery_method' => 'push', 'delivery_protocols' => ['rtmp', 'rtsp', 'wowz'], 'disable_authentication' => false, 'hosted_page_description' => 'My Hosted Page Description', 'hosted_page_logo_image' => '[Base64-encoded string representation of a GIF, JPEG, or PNG file]', 'hosted_page_sharing_icons' => true, 'hosted_page_title' => 'My Hosted Page', 'password' => '68332313', 'player_countdown' => true, 'player_countdown_at' => '2017-12-29T19:00:00.000Z', 'player_logo_image' => '[Base64-encoded string representation of a GIF, JPEG, or PNG file]', 'player_logo_position' => 'top-right', 'player_responsive' => false, 'player_type' => 'wowza_player', 'player_video_poster_image' => '[Base64-encoded string representation of a GIF, JPEG, or PNG file]', 'player_width' => 640, 'recording' => true, 'remove_hosted_page_logo_image' => true, 'remove_player_logo_image' => true, 'remove_player_video_poster_image' => true, 'source_url' => 'xyz.streamlock.net/vod/mp4:Movie.mov', 'target_delivery_protocol' => 'hls-https', 'use_stream_source' => false, 'username' => 'client2', 'video_fallback' => false])])]);
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
     * [state 获取状态]
     * @Author    ZiShang520@gmail.com
     * @DateTime  2018-09-05T13:54:29+0800
     * @copyright (c) ZiShang520 All Rights Reserved
     * @param     [type] $stream_id [description]
     * @return    [type] [description]
     */
    public function state($stream_id = null)
    {
        if (is_null($stream_id)) {
            throw new Exception("stream_id cannot be empty");
        }
        $response = $this->get(sprintf('%s/%s/%s/%s', $this->api_url, self::LIVE_STREAMS, $stream_id, 'state'));
        $body = json_decode($response->body, true);
        if ($response->http_code !== 200) {
            if (!empty($body)) {
                throw new Exception($body['meta']['message']);
            } else {
                throw new Exception('Api [state] Request Error');
            }
        }
        return $body;
    }
    /**
     * [stats 获取状态]
     * @Author    ZiShang520@gmail.com
     * @DateTime  2018-09-05T14:12:33+0800
     * @copyright (c) ZiShang520 All Rights Reserved
     * @param     [type] $stream_id [description]
     * @return    [type] [description]
     */
    public function stats($stream_id = null)
    {
        if (is_null($stream_id)) {
            throw new Exception("stream_id cannot be empty");
        }
        $response = $this->get(sprintf('%s/%s/%s/%s', $this->api_url, self::LIVE_STREAMS, $stream_id, 'stats'));
        $body = json_decode($response->body, true);
        if ($response->http_code !== 200) {
            if (!empty($body)) {
                throw new Exception($body['meta']['message']);
            } else {
                throw new Exception('Api [stats] Request Error');
            }
        }
        return $body;
    }
    /**
     * [thumbnail_url 获取缓存图片]
     * @Author    ZiShang520@gmail.com
     * @DateTime  2018-09-05T13:55:50+0800
     * @copyright (c) ZiShang520 All Rights Reserved
     * @param     [type] $stream_id [description]
     * @return    [type] [description]
     */
    public function thumbnail_url($stream_id = null)
    {
        if (is_null($stream_id)) {
            throw new Exception("stream_id cannot be empty");
        }
        $response = $this->get(sprintf('%s/%s/%s/%s', $this->api_url, self::LIVE_STREAMS, $stream_id, 'thumbnail_url'));
        $body = json_decode($response->body, true);
        if ($response->http_code !== 200) {
            if (!empty($body)) {
                throw new Exception($body['meta']['message']);
            } else {
                throw new Exception('Api [thumbnail_url] Request Error');
            }
        }
        return $body;
    }

    /**
     * [start 启动流]
     * @Author    ZiShang520@gmail.com
     * @DateTime  2018-09-05T13:58:04+0800
     * @copyright (c) ZiShang520 All Rights Reserved
     * @param     [type] $stream_id [description]
     * @return    [type] [description]
     */
    public function start($stream_id = null)
    {
        if (is_null($stream_id)) {
            throw new Exception("stream_id cannot be empty");
        }
        $response = $this->request(['url' => sprintf('%s/%s/%s/%s', $this->api_url, self::LIVE_STREAMS, $stream_id, 'start'), 'method' => 'PUT']);
        $body = json_decode($response->body, true);
        if ($response->http_code !== 200) {
            if (!empty($body)) {
                throw new Exception($body['meta']['message']);
            } else {
                throw new Exception('Api [start] Request Error');
            }
        }
        return $body;
    }
    /**
     * [reset 重置流]
     * @Author    ZiShang520@gmail.com
     * @DateTime  2018-09-05T13:59:27+0800
     * @copyright (c) ZiShang520 All Rights Reserved
     * @param     [type] $stream_id [description]
     * @return    [type] [description]
     */
    public function reset($stream_id = null)
    {
        if (is_null($stream_id)) {
            throw new Exception("stream_id cannot be empty");
        }
        $response = $this->request(['url' => sprintf('%s/%s/%s/%s', $this->api_url, self::LIVE_STREAMS, $stream_id, 'reset'), 'method' => 'PUT']);
        $body = json_decode($response->body, true);
        if ($response->http_code !== 200) {
            if (!empty($body)) {
                throw new Exception($body['meta']['message']);
            } else {
                throw new Exception('Api [reset] Request Error');
            }
        }
        return $body;
    }
    /**
     * [stop 停止流]
     * @Author    ZiShang520@gmail.com
     * @DateTime  2018-09-05T13:59:36+0800
     * @copyright (c) ZiShang520 All Rights Reserved
     * @param     [type] $stream_id [description]
     * @return    [type] [description]
     */
    public function stop($stream_id = null)
    {
        if (is_null($stream_id)) {
            throw new Exception("stream_id cannot be empty");
        }
        $response = $this->request(['url' => sprintf('%s/%s/%s/%s', $this->api_url, self::LIVE_STREAMS, $stream_id, 'stop'), 'method' => 'PUT']);
        $body = json_decode($response->body, true);
        if ($response->http_code !== 200) {
            if (!empty($body)) {
                throw new Exception($body['meta']['message']);
            } else {
                throw new Exception('Api [stop] Request Error');
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
        $response = $this->request(['url' => sprintf('%s/%s/%s/%s', $this->api_url, self::LIVE_STREAMS, $stream_id, 'regenerate_connection_code'), 'method' => 'PUT']);
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
        $response = $this->request(['url' => sprintf('%s/%s/%s', $this->api_url, self::LIVE_STREAMS, $stream_id), 'method' => 'DELETE']);
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
