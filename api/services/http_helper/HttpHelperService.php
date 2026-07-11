<?php

namespace app\services\http_helper;

use yii\httpclient\Exception;
use yii\httpclient\Response;
use yii\httpclient\Client;

class HttpHelperService implements HttpHelperServiceInterface
{
    private $client;
    private $timeOut;
    private $headers;

    public function __construct()
    {
        $this->client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
        $this->timeOut = 10;
        $this->headers = [
            'Accept' => '*/*',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
    }

    /**
     * @throws Exception
     */
    public function get(string $url, array $headers = [], array $options = []): Response
    {
        try {

            if (!empty($headers)) {
                $keys = array_keys($headers);
                foreach ($keys as $key) {
                    $this->headers[$key] = $headers[$key];
                }
            }

            if (isset($options['timeOut'])) {
                $this->timeOut = $options['timeOut'];
            }

            $options = [
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_CONNECTTIMEOUT => $this->timeOut,
                CURLOPT_TIMEOUT => $this->timeOut
            ];

            return $this->client->get($url)
                ->addHeaders($this->headers)
                ->setOptions($options)
                ->send();
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    public function post(string $url, array $headers = [], array $data = [], array $options = []): Response
    {
        try {

            if (!empty($headers)) {
                $keys = array_keys($headers);
                foreach ($keys as $key) {
                    $this->headers[$key] = $headers[$key];
                }
            }

            if (isset($options['timeOut'])) {
                $this->timeOut = $options['timeOut'];
            }

            $options = [
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_CONNECTTIMEOUT => $this->timeOut,
                CURLOPT_TIMEOUT => $this->timeOut
            ];

            return $this->client->post($url)
                ->addHeaders($this->headers)
                ->setOptions($options)
                ->setData($data)
                ->send();

        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }
}
