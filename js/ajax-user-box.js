(function($){
	$('#col-left h3').append("<a class='button add-new'>Add more users</a>");
	$('.add-new.button').click(function(i){
		$('#new-user-and-email').append(function(){
			return $('#new-user-and-email .row:first').clone().show();
		});
	});
})(jQuery);