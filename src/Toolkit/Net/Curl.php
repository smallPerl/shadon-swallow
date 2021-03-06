<?php

/*
 * PHP version 5.5
 *
 * @copyright  Copyright (c) 2012-2015 EELLY Inc. (http://www.eelly.com)
 * @link       http://www.eelly.com
 * @license    衣联网版权所有
 */
namespace Swallow\Toolkit\Net;

/**
 * CURL包装类
 *
 * @author     SpiritTeam
 * @since      2015年1月13日
 * @version    1.0
 */
class Curl
{

    /**
     * 读取和写入cookie的文件
     * @var string
     */
    public $cookieFile;

    /**
     * 启用时会将服务器服务器返回的"Location: "放在header中递归的返回给服务器，使用CURLOPT_MAXREDIRS可以限定递归返回的数量
     * @var boolean
     */
    public $followRedirects = false;

    /**
     * 头部信息关联数组
     * @var array
     */
    public $headers = array();

    /**
     * CURLOPT选项的关联数组
     * @var array
     */
    public $options = array();

    /**
     * 在HTTP请求头中"Referer: "的内容
     * @var string
     */
    public $referer;

    /**
     * 在HTTP请求中包含一个"User-Agent: "头的字符串
     * @var string
     */
    public $userAgent;

    /**
     * 错误信息
     * @var string
     */
    protected $error = '';

    /**
     *  CURL request
     * @var resource
     */
    protected $request;

    /**
     * CURL response
     * @var string
     */
    protected $response;

    /**
     * CURL curl_getinfo
     * @var string
     */
    protected $curlGetInfo;

    /**
     * 初始化
     *
     */
    public function __construct()
    {
        // $this->cookieFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'curl_cookie.txt';
        $this->userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    }

    /**
     * 返回错误信息
     *
     * @return string
     */
    public function error()
    {
        return $this->error;
    }

    /**
     * GET方式发送请求
     *
     * @param string $url
     * @param array|string $vars
     * @return CurlResponse
     */
    public function get($url, array $vars = array())
    {
        if (! empty($vars)) {
            $url .= (stripos($url, '?') !== false) ? '&' : '?';
            $url .= (is_string($vars)) ? $vars : http_build_query($vars, '', '&');
        }
        return $this->request('GET', $url);
    }

    /**
     * head方式发送请求
     *
     * @param string $url
     * @param array|string $vars
     * @return CurlResponse
     */
    public function head($url, array $vars = array())
    {
        return $this->request('HEAD', $url, $vars);
    }

    /**
     * post方式发送请求
     *
     * @param string $url
     * @param array|string $vars
     * @return CurlResponse|boolean
     */
    public function post($url, $vars = array())
    {
        return $this->request('POST', $url, $vars);
    }

    /**
     * put方式发送请求
     *
     * @param string $url
     * @param array|string $vars
     * @return CurlResponse|boolean
     */
    public function put($url, array $vars = array())
    {
        return $this->request('PUT', $url, $vars);
    }

    /**
     * 获取请求响应
     *
     * @return string
     */
    public function response()
    {
        return $this->response;
    }

    /**
     * 获取连接资源句柄的信息
     *
     * @param string $key
     * @return string
     */
    public function curlGetInfo($key = '')
    {
        if (empty($key)) {
            return $this->curlGetInfo;
        }
        if (isset($this->curlGetInfo[$key])) {
            return $this->curlGetInfo[$key];
        }
        return '';
    }

    /**
     * 发送请求
     *
     * @param string $method
     * @param string $url
     * @param array|string $vars
     * @return CurlResponse|boolean
     */
    private function request($method, $url, $vars = array())
    {
        $this->error = '';
        $this->request = curl_init();
        if (is_array($vars))
            $vars = http_build_query($vars, '', '&');

        $this->set_request_method($method);
        $this->set_request_options($url, $vars);
        $this->set_request_headers();
        //测试环境先跳过SSL
        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($this->request, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($this->request, CURLOPT_SSL_VERIFYHOST, FALSE);
        }
        $response = curl_exec($this->request);
        $this->curlGetInfo = curl_getinfo($this->request);
        $headerSize = $this->curlGetInfo['header_size'];
        $this->response['code'] = $this->curlGetInfo('http_code');
        $this->response['header'] = substr($response, 0, $headerSize);
        $this->response['body'] = substr($response, $headerSize);
        $this->response['redirect_url'] = $this->curlGetInfo('redirect_url');

        if ('200' != $this->curlGetInfo('http_code')) {
            $this->error = curl_errno($this->request) . ' - ' . curl_error($this->request);
        }
        curl_close($this->request);
        return $this->response;
    }

    /**
     * 设置头部信息
     *
     * @return void
     */
    private function set_request_headers()
    {
        $headers = array();
        foreach ($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        curl_setopt($this->request, CURLOPT_HTTPHEADER, $headers);
    }

    /**
     * 设置请求method
     *
     * @param string $method
     * @return void
     */
    private function set_request_method($method)
    {
        switch (strtoupper($method)) {
            case 'HEAD':
                curl_setopt($this->request, CURLOPT_NOBODY, true);
                break;
            case 'GET':
                curl_setopt($this->request, CURLOPT_HTTPGET, true);
                break;
            case 'POST':
                curl_setopt($this->request, CURLOPT_POST, true);
                break;
            default:
                curl_setopt($this->request, CURLOPT_CUSTOMREQUEST, $method);
        }
    }

    /**
     * 设置请求配置项
     *
     * @param string $url
     * @param string $vars
     * @return void
     */
    private function set_request_options($url, $vars)
    {
        $removeBom = function($var) { return preg_replace('/\\0/', "", $var); };
        
        curl_setopt($this->request, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
        curl_setopt($this->request, CURLOPT_SSL_VERIFYHOST, false);  // 从证书中检查SSL加密算法是否存在
        curl_setopt($this->request, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($this->request, CURLOPT_URL, $removeBom($url));
        if (! empty($vars)) {
            curl_setopt($this->request, CURLOPT_POSTFIELDS, $vars);
        }
        // Set some default CURL options
        curl_setopt($this->request, CURLOPT_HEADER, true);
        curl_setopt($this->request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->request, CURLOPT_USERAGENT, $this->userAgent);
        if ($this->cookieFile) {
            curl_setopt($this->request, CURLOPT_COOKIEFILE, $this->cookieFile);
            curl_setopt($this->request, CURLOPT_COOKIEJAR, $this->cookieFile);
        }
        if ($this->followRedirects) {
            curl_setopt($this->request, CURLOPT_FOLLOWLOCATION, true);
        }
        if ($this->referer) {
            curl_setopt($this->request, CURLOPT_REFERER, $this->referer);
        }
        // Set any custom CURL options
        foreach ($this->options as $option => $value) {
            curl_setopt($this->request, constant('CURLOPT_' . str_replace('CURLOPT_', '', strtoupper($option))), $value);
        }
    }
}