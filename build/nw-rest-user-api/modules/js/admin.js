jQuery(document).ready(function($){


$('body').on( 'click', '.activation_checkbox', function( e ){
  
		// verify email

		var data = {
			user_id  : $(this).attr('data-id'),
			is_checked  : $(this).is(':checked'),
			security  : nrua_local_data.nonce,
			action : 'make_active_user'
		}
		jQuery.ajax({url: nrua_local_data.ajaxurl,
				type: 'POST',
				data: data,            
				beforeSend: function(msg){
						jQuery('body').append('<div class="big_loader"></div>');
					},
					success: function(msg){
						
						
						console.log( msg );
						
						jQuery('.big_loader').replaceWith('');
						
						var obj = jQuery.parseJSON( msg );
 
						if( obj.result == 'success' ){
			 
						 
						}else{
						 
						}
						 
					} , 
					error:  function(msg) {
									
					}          
			});
 
})

 
	
});