<?php
class Weixin
{
    public function __construct($appid,$callback)
    {
        $this->appid = $appid;
        $this->callback = $callback;
    }

    public function get_api_url()
    {

        $state = md5(uniqid(TRUE)); //CSRF protection
        $url = 'https://open.weixin.qq.com/connect/qrconnect?appid=' . $this->appid . '&redirect_uri=' . urlencode($this->callback) . '&response_type=code&scope=snsapi_login&state=' . $state . '#wechat_redirect';
        return $url;
    }
}