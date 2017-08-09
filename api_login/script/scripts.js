jQuery(document).ready(function($) {
    $(".cancel_bind_btn").on("click",function(){
    	var _this = $(this);
    	var cancel_btn_id = _this.attr('id');
    	jQuery.post('/api_login_callback.php',{c: cancel_btn_id, cancel_bind: 'cancel_'+cancel_btn_id}, 
			function(result){
				if(result=='success'){
					location.reload();
				}else{
					
				}
			});
	});
});