(function($){
	$('#new-user-and-email').prepend("<a style='cursor:pointer'>+ Add more users</a>");
	$('#new-user-and-email a').click(function(i){
		$('#new-user-and-email').append(function(){
			return $('#new-user-and-email div:first').clone();
		});
	});
})(jQuery);