(function($){
	$('#new-user-and-email').prepend("<p style='width:95%; text-align:right;'><a class='button add-new'>+ Add more users</a></p>");
	$('#new-user-and-email .add-new').click(function(i){
		$('#new-user-and-email').append(function(){
			return $('#new-user-and-email .row:first').clone().show();
		});
	});
})(jQuery);