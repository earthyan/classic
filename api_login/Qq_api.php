<?php
$api_lang = array(
	'name'	=>	'QQv2登录插件',
	'app_key'	=>	'QQAPI应用appid',
	'app_secret'	=>	'QQAPI应用appkey',
);

$config = array(
	'app_key'	=>	array(
		'INPUT_TYPE'	=>	'0',
	), //腾讯API应用的KEY值
	'app_secret'	=>	array(
		'INPUT_TYPE'	=>	'0'
	), //腾讯API应用的密码值
);
// QQ的api登录接口
require_once(ABSPATH.'library/api_login/api_login.php');
class Qq_api implements api_login {
	
	private $api;
	
	public function __construct($api)
	{
		require_once ABSPATH.'library/api_login/qqv2/comm/config.php';
		$api['config']['app_key'] = $_SESSION["appid"];
		$api['config']['app_secret'] = $_SESSION["appkey"];
		$api['config']['app_url'] = $_SESSION["callback"];
		$api['class_name'] = 'Qq';
		$api['field'] = 'qq_open_id';
		$api['token_field'] = 'qq_token';
		$this->api = $api;
	}
	
	public function get_api_url()
	{
		$callback = $this->api['config']['app_url'];
		$scope="get_user_info,add_share,list_album,add_album,upload_pic,add_topic,add_one_blog,add_weibo,check_page_fans,add_t,add_pic_t,del_t,get_repost_list,get_info,get_other_info,get_fanslist,get_idolist,add_idol,del_idol,get_tenpay_addr";
		$inc['appid']=$this->api['config']['app_key'];
		$inc['appkey']=$this->api['config']['app_secret'];
		$inc['callback']=$callback;
		$_SESSION['state'] = md5(uniqid(rand(), TRUE)); //CSRF protection
	    $login_url = "https://graph.qq.com/oauth2.0/authorize?response_type=code&client_id=" 
	        . $inc['appid'] . "&redirect_uri=" . urlencode($inc['callback'])
	        . "&state=" . $_SESSION['state']
	        . "&scope=".$scope;
	    return $login_url;
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
		
	function callback()
	{
	    if(!empty($_SESSION['state']) && $_REQUEST['state'] == $_SESSION['state']) //csrf
	    {
	        $token_url = "https://graph.qq.com/oauth2.0/token?grant_type=authorization_code&"
	            . "client_id=" . $_SESSION["appid"]. "&redirect_uri=" . urlencode($_SESSION["callback"])
	            . "&client_secret=" . $_SESSION["appkey"]. "&code=" . $_REQUEST["code"];
	
	        $response = file_get_contents($token_url);
	        if (strpos($response, "callback") !== false)
	        {
	            $lpos = strpos($response, "(");
	            $rpos = strrpos($response, ")");
	            $response  = substr($response, $lpos + 1, $rpos - $lpos -1);
	            $msg = json_decode($response);
	            if (isset($msg->error))
	            {
	                echo "<h3>error:</h3>" . $msg->error;
	                echo "<h3>msg  :</h3>" . $msg->error_description;
	                exit;
	            }
	        }
	        
	        $params = array();
	        parse_str($response, $params);
	
	        //debug
	        //print_r($params);
	
	        //set access token to session
	        $_SESSION["access_token"] = $params["access_token"];
	        $this->api['config']['access_token'] = $params["access_token"];
	        $this->get_openid();
	        $connect_open_id = $this->api['config']['open_id'];
	        
	        $api_data = array();
	        $api_data['id'] = $connect_open_id;
	        $api_data['field'] = $this->api['field'];
	        $api_data['token_field'] = $this->api['token_field'];
	        $api_data['token'] = $this->api['config']['access_token'];
	        
	        $qq_user_info = $this->get_user_info();
	        $api_data['avatar'] = $qq_user_info['figureurl_qq_2'];
	        $api_data['name'] = $qq_user_info['nickname'];
	        
		    if(!empty($connect_open_id)){
				$connect_user_id = get_user_by_meta_value_new($api_data['field'], $api_data['id'], true);
			}
			
			//登录已存在用户或者添加用户后自动登录
			if(!empty($connect_user_id)){
				$redirect_to = apply_filters( 'registration_redirect', !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : home_url() );
				wp_set_auth_cookie($connect_user_id);
				$user = get_user_by('id', $connect_user_id);
				do_action('wp_login', $user->user_login, $user);
				wp_safe_redirect( $redirect_to );
			}elseif(!empty($api_data['id'])){
				$redirect_to = apply_filters( 'registration_redirect', !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : home_url() );
				//$connect_user_login = 'qq_'.$api_data['id'];
				
				global $current_user;
				get_currentuserinfo();
				if(!empty($current_user->ID)){
					$redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : home_url().'/wp-admin/profile.php?page=api_login_bind';
					//第三方登录绑定
					$connect_user_id = $current_user->ID;
				}else{
					$connect_user_login = 'qq_'.substr(time(), 2,7).mt_rand(100,999);
					$connect_user_email = $connect_user_login.'@qq.com';
					
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
				    //保存qq头像到本地
				    $image_content = $this->get_content_timeout($api_data['avatar']);
				    if(!empty($image_content)){
				        $user_avatar_local_filename = ABSPATH.'/avatar/qq_user_'.$connect_user_id.'.jpg';
				        $user_avatar_local_url = home_url().'/avatar/qq_user_'.$connect_user_id.'.jpg';
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
	    else 
	    {
	        echo("The state does not match. You may be a victim of CSRF.");
	    }
	}
	
	function get_openid()
	{
	    $graph_url = "https://graph.qq.com/oauth2.0/me?access_token=" 
	        . $_SESSION['access_token'];
	
	    $str  = file_get_contents($graph_url);
	    if (strpos($str, "callback") !== false)
	    {
	        $lpos = strpos($str, "(");
	        $rpos = strrpos($str, ")");
	        $str  = substr($str, $lpos + 1, $rpos - $lpos -1);
	    }
	
	    $user = json_decode($str);
	    if (isset($user->error))
	    {
	        echo "<h3>error:</h3>" . $user->error;
	        echo "<h3>msg  :</h3>" . $user->error_description;
	        exit;
	    }
	
	    //debug
	    //echo("Hello " . $user->openid);
	
	    //set openid to session
	    $_SESSION["openid"] = $user->openid;
	    $this->api['config']['open_id'] = $user->openid;
	}
	
	function get_user_info()
	{
	    $get_user_info = "https://graph.qq.com/user/get_user_info?"
	        . "access_token=" . $this->api['config']['access_token']
	        . "&oauth_consumer_key=" . $this->api['config']['app_key']
	        . "&openid=" . $this->api['config']['open_id']
	        . "&format=json";
	
	    $info = file_get_contents($get_user_info);
	    $arr = json_decode($info, true);
	
	    return $arr;
	}
	
	public function get_title()
	{
		return 'QQv2登录接口，需要php_curl扩展的支持';
	}
		
	//解除API 绑定
	public function unset_api(){
	    if($GLOBALS['user_info']){
	       $GLOBALS['db']->query("update ".DB_PREFIX."user set qq_id= '', qq_token ='' where id =".$GLOBALS['user_info']['id']);
	    }
	}    
	
	//同步微博信息
	public function send_message($data){
	    
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