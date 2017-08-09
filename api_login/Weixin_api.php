<?php
$api_lang = array(
	'name'	=>	'微信登录接口',
	'app_key'	=>	'微信API应用APP_KEY',
	'app_secret'	=>	'微信API应用APP_SECRET',
	'app_url'	=>	'回调地址',
);

$config = array(
	'app_key'	=>	array(
		'INPUT_TYPE'	=>	'0',
	), //新浪API应用的KEY值
	'app_secret'	=>	array(
		'INPUT_TYPE'	=>	'0'
	), //新浪API应用的密码值
	'app_url'	=>	array(
		'INPUT_TYPE'	=>	'0'
	),
);

// 新浪的api登录接口
require_once(ABSPATH.'library/api_login/api_login.php');
class Weixin_api implements api_login {
	
	private $api;
	
	public function __construct()
	{
		require_once ABSPATH.'library/api_login/weixin/config.php';
		$api['config']['app_key'] = WX_AKEY;
		$api['config']['app_secret'] = WX_SKEY;
		$api['config']['app_url'] = WX_CALLBACK_URL;
		$api['class_name'] = WX_CLASS_NAME;
		$api['field'] = 'wx_open_id';
		$api['token_field'] = 'wx_access_token';
		$this->api = $api;
	}
	
	public function get_api_url()
	{
		if($this->api['config']['app_url']=="")
		{
			$app_url = get_domain().APP_ROOT."/api_callback.php?c=Sina";
		}
		else
		{
			$app_url = $this->api['config']['app_url'];
		}
		$state = md5(uniqid(rand(), TRUE)); //CSRF protection
		$aurl = 'https://open.weixin.qq.com/connect/qrconnect?appid='.$this->api['config']['app_key'].'&redirect_uri='.urlencode($app_url).'&response_type=code&scope=snsapi_login&state='.$state.'#wechat_redirect';
		return $aurl;
	}
	
	public function cancel_bind()
	{
		global $current_user;
		get_currentuserinfo();
		if(empty($current_user)) return 'unlogin';
		$user_ID=$current_user->ID;
		
		if(empty($current_user->user_pass)){
			return 'empty_user_pass';
		}
		
		if(!empty($user_ID)){
			delete_user_meta($user_ID, $this->api['field']);
			delete_user_meta($user_ID, $this->api['token_field']);
			return 'success';
		}
	}
	
	public function callback()
	{
		$redirect_to = apply_filters( 'registration_redirect', !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : home_url() );
		if(empty($_REQUEST['code']) || empty($_REQUEST['state'])) wp_safe_redirect( $redirect_to );
		$return_code = addslashes($_REQUEST['code']);
		
		//获取access_token
		$get_token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$this->api['config']['app_key'].'&secret='.$this->api['config']['app_secret'].'&code='.$return_code.'&grant_type=authorization_code';
		$json_data = file_get_contents($get_token_url);
		if(empty($json_data)) wp_safe_redirect( $redirect_to );
		$access_token_data = json_decode($json_data);
		if(empty($access_token_data->access_token) || empty($access_token_data->openid)){
			wp_safe_redirect( $redirect_to );
		}
		$this->api['config']['access_token'] = $access_token_data->access_token;
		$this->api['config']['open_id'] = $access_token_data->openid;
		
		//获取用户个人信息
		$connect_user_info = $this->get_user_info();
		
		$uid = $this->api['config']['open_id'];
		$api_data['id'] = $uid;
		$api_data['name'] = $connect_user_info['nickname'];
		$api_data['url'] = "http://www.qq.com/";
		$api_data['province'] = $connect_user_info['province'];
		$api_data['city'] = $connect_user_info['city'];
		$api_data['avatar'] = $connect_user_info['headimgurl'];
		$api_data['field'] = $this->api['field'];
		$api_data['token'] = $this->api['config']['access_token'];
		$api_data['token_field'] = $this->api['token_field'];
		$api_data['sex'] = $connect_user_info['sex'];
		
		if($api_data['id']!=""){
			$connect_user_id = get_user_by_meta_value_new($api_data['field'], $api_data['id'], true);
		}
		
		if(!empty($connect_user_id)){
			$redirect_to = apply_filters( 'registration_redirect', !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : home_url() );
			wp_set_auth_cookie($connect_user_id);
			$user = get_user_by('id', $connect_user_id);
			do_action('wp_login', $user->user_login, $user);
			wp_safe_redirect( $redirect_to );
		}elseif(!empty($api_data['id'])){
			$redirect_to = apply_filters( 'registration_redirect', !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : home_url() );
			
			global $current_user;
			get_currentuserinfo();
			if(!empty($current_user->ID)){
				//第三方登录绑定
				$redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : home_url().'/wp-admin/profile.php?page=api_login_bind';
				$connect_user_id = $current_user->ID;
			}else{
				$connect_user_login = 'wx_'.substr(time(), 2,7).mt_rand(100,999);
				$connect_user_email = $connect_user_login.'@weixin.qq.com';
				
				$user_login = esc_sql( $connect_user_login );
				$user_email = esc_sql( $connect_user_email );
				$display_name = esc_sql( $api_data['name'] );
			
				$userdata = compact('user_login', 'user_email', 'display_name');
				$connect_user_id = wp_insert_user($userdata);
			}
			
			if ( empty($connect_user_id) ) {
				$redirect_to = !empty( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : 'wp-login.php?checkemail=registered';
				wp_safe_redirect( $redirect_to );
				exit();
			}
			
			//自动登录
			wp_set_auth_cookie($connect_user_id);
			$user = get_user_by('id', $connect_user_id);
			do_action('wp_login', $user->user_login, $user);
			
			update_user_meta( $connect_user_id, $api_data['field'], $api_data['id'] );
			update_user_meta( $connect_user_id, $api_data['token_field'], $api_data['token'] );
			$user_simple_local_avatar = get_user_meta($connect_user_id, 'simple_local_avatar', true);
			if(!empty($api_data['avatar']) && empty($user_simple_local_avatar)){
			    //保存微信头像到本地
			    $image_content = $this->get_content_timeout($api_data['avatar']);
			    if(!empty($image_content)){
			        $user_avatar_local_filename = ABSPATH.'/avatar/wx_user_'.$connect_user_id.'.jpg';
			        $user_avatar_local_url = home_url().'/avatar/wx_user_'.$connect_user_id.'.jpg';
			        @file_put_contents($user_avatar_local_filename, $image_content);
			        
			        if(is_file($user_avatar_local_filename)){
			            $simple_local_avatar_meta_value['full'] = $user_avatar_local_url;
			            update_user_meta( $connect_user_id, 'simple_local_avatar', $simple_local_avatar_meta_value );
			            update_user_meta( $connect_user_id, 'simple_local_avatar_rating', 'G' );
			        }
			    }
			}
			wp_safe_redirect( $redirect_to );
		}
		
	}
	
	public function get_title()
	{
		return '新浪api登录接口，需要php_curl扩展的支持(V2)';
	}
	
	/**
	 * 
	 * 获取用户个人信息
	 */
	function get_user_info()
	{
	    $get_user_info = "https://api.weixin.qq.com/sns/userinfo?"
	        . "access_token=" . $this->api['config']['access_token']
	        . "&openid=" . $this->api['config']['open_id'];
	
	    $info = file_get_contents($get_user_info);
	    $arr = json_decode($info, true);
	
	    return $arr;
	}
	
	
	//同步发表到新浪微博
	public function send_message($data)
	{
		static $client = NULL;
		if($client === NULL)
		{
			require_once ABSPATH.'library/api_login/weibo/saetv2.ex.class.php';
			$uid = intval($GLOBALS['user_info']['id']);
			$udata = $GLOBALS['db']->getRow("select sina_token from ".DB_PREFIX."user where id = ".$uid);
			$client = new SaeTClientV2($this->api['config']['app_key'],$this->api['config']['app_secret'],$udata['sina_token']);
		}
		try
		{
			if(empty($data['img']))
				$msg = $client->update($data['content']);
			else
				$msg = $client->upload($data['content'],$data['img']);

			if($msg['error'])
			{
				$result['status'] = false;
				$result['msg'] = "新浪微博同步失败，请偿试重新通过腾讯微博登录或得新授权。";
				return $result;
			}
			else
			{
				$result['status'] = true;
				$result['msg'] = "success";
				return $result;
			}

		}
		catch(Exception $e)
		{

		}
	}
	
    /**
     * 获取内容设置超时时间
     * @param unknown $url
     * @param number $time
     * @return boolean|unknown
     */
    function get_content_timeout($url, $time = 2){
    	if(empty($url)) return false;
    	$context = stream_context_create(array(
    						'http' => array(
    							'timeout' => $time //超时时间，单位为秒
            				) 
            		));  
    	// Fetch the URL's contents 
    	$data = @file_get_contents($url, 0, $context);
    	if(empty($data)) return false;
    	return $data;
    }
	
}
?>