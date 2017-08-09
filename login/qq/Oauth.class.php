<?php
/* PHP SDK
 * @version 2.0.0
 * @author connect@qq.com
 * @copyright © 2013, Tencent Corporation. All rights reserved.
 */



class QC{
    public function __construct($appid,$appkey,$callback){
        $this->appid = $appid;
        $this->appkey = $appkey;
        $this->callback = $callback;
    }


    public function get_api_url()
    {
        $scope="get_user_info,add_share,list_album,add_album,upload_pic,add_topic,add_one_blog,add_weibo,check_page_fans,add_t,add_pic_t,del_t,get_repost_list,get_info,get_other_info,get_fanslist,get_idolist,add_idol,del_idol,get_tenpay_addr";
        $inc['appid']=$this->appid;
        $inc['appkey']=$this->appkey;
        $inc['callback']=$this->callback;
        $_SESSION['state'] = md5(uniqid(rand(), TRUE)); //CSRF protection
        $login_url = "https://graph.qq.com/oauth2.0/authorize?response_type=code&client_id="
            . $inc['appid'] . "&redirect_uri=" . urlencode($inc['callback'])
            . "&state=" . $_SESSION['state']
            . "&scope=".$scope;
        return $login_url;
    }
}
