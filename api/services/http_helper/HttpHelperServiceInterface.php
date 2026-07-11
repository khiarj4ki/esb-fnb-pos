<?php

    namespace app\services\http_helper;


    use yii\httpclient\Response;

    interface HttpHelperServiceInterface
    {
        public function get(string $url, array $headers = [], array $options = []): Response;
    }