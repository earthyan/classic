<?php
$api_lang = array(
	'name'	=>	'新浪api登录接口',
	'app_key'	=>	'新浪API应用APP_KEY',
	'app_secret'	=>	'新浪API应用APP_SECRET',
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
class Weibo_api implements api_login {
	
	private $api;
	
	public function __construct()
	{
		require_once ABSPATH.'library/api_login/weibo/config.php';
		$api['config']['app_key'] = WB_AKEY;
		$api['config']['app_secret'] = WB_SKEY;
		$api['config']['app_url'] = WB_CALLBACK_URL;
		$api['class_name'] = CLASS_NAME;
		$api['field'] = 'sina_id';
		$api['token_field'] = 'sina_token';
		$this->api = $api;
	}
	
	public function get_api_url()
	{
		require_once ABSPATH.'library/api_login/weibo/saetv2.ex.class.php';
		$o = new SaeTOAuthV2($this->api['config']['app_key'],$this->api['config']['app_secret']);
		if($this->api['config']['app_url']=="")
		{
			$app_url = get_domain().APP_ROOT."/api_callback.php?c=Sina";
		}
		else
		{
			$app_url = $this->api['config']['app_url'];
		}
		$aurl = $o->getAuthorizeURL($app_url);
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
		require_once ABSPATH.'library/api_login/weibo/saetv2.ex.class.php';
		//$sina_keys = es_session::get("sina_keys");
		$o = new SaeTOAuthV2($this->api['config']['app_key'],$this->api['config']['app_secret']);
		if (isset($_REQUEST['code'])) {
			$keys = array();
			$keys['code'] = $_REQUEST['code'];
			if($this->api['config']['app_url']=="")
			{
				$app_url = home_url()."/api_callback.php?c=Sina";
			}
			else
			{
				$app_url = $this->api['config']['app_url'];
			}
			$keys['redirect_uri'] = $app_url;
			try {
				$token = $o->getAccessToken( 'code', $keys ) ;
			} catch (OAuthException $e) {
				echo '<script>history.go(-1);</script>';
			}
		}

		if(!empty($token['access_token'])){
			file_put_contents(ABSPATH.'weibo_access_token_recently.txt', $token['access_token']);
		}
		$c = new SaeTClientV2($this->api['config']['app_key'],$this->api['config']['app_secret'] ,$token['access_token'] );
		
		$uid = $token['uid'];
		$msg = $c->show_user_by_id( $uid);//根据ID获取用户等基本信息
		
		//name,url,province,city,avatar,token,field,token_field(授权的字段),sex,secret_field(授权密码的字段),scret,url_field(微博地址的字段)
		$api_data['id'] = $uid;
		$api_data['name'] = $msg['name'];
		$api_data['url'] = "http://weibo.com/".$msg['profile_url'];
		$location = $msg['location'];
		$location = explode(" ",$location);
		$api_data['province'] = $location[0];
		$api_data['city'] = $location[1];
		$api_data['avatar'] = $msg['profile_image_url'];
		$api_data['field'] = $this->api['field'];
		$api_data['token'] = $token['access_token'];
		$api_data['token_field'] = $this->api['token_field'];;
		$api_data['secret'] = "";
		$api_data['secret_field'] = "sina_secret";
		$api_data['url_field'] = "sina_url";
		if($msg['gender']=='m'){
			$api_data['sex'] = 1;
		}else if($msg['gender']=='f'){
			$api_data['sex'] = 0;
		}else{
			$api_data['sex'] = -1;
		}
		
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
				$connect_user_login = 'wb_'.$api_data['id'];
				$connect_user_email = $connect_user_login.'@sina.cn';
				
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
				$simple_local_avatar_meta_value['full'] = $api_data['avatar'];
				update_user_meta( $connect_user_id, 'simple_local_avatar', $simple_local_avatar_meta_value );
				update_user_meta( $connect_user_id, 'simple_local_avatar_rating', 'G' );
			}
			wp_safe_redirect( $redirect_to );
		}
		
	}
	
	public function get_title()
	{
		return '新浪api登录接口，需要php_curl扩展的支持(V2)';
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
	
}
?>