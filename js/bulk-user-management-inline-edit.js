(function($) {
inlineEditUser = {

	init : function(){
		var t = this, bulkRow = $('#bulk-edit');

		t.showTable();

		// Submit bulk edit option
		$('#doaction, #doaction2').click(function(e){
			t.cancel();
			var n = $(this).attr('id').substr(2);
			if ( $('select[name="'+n+'"]').val() == 'modify' ) {
				e.preventDefault();
				t.setBulk();
			}
			if ( $('select[name="'+n+'"]').val() == 'remove' ) {
				e.preventDefault();
				t.remove();
			}
		});

		// Cancel bulk edit box
		$('.cancel').click(function(e){
			e.preventDefault();
			t.cancel();
		});
	},

	setBulk : function(){
		var te = '', c = true;

		console.log( $('#bulk-edit') );

		$('#bulk-edit td').attr('colspan', $('.widefat:first thead th:visible').length);
		$('table.widefat tbody').prepend( $('#bulk-edit') );
		$('#bulk-edit').addClass('inline-editor').show();

		$('tbody th.check-column input[type="checkbox"]').each(function(i){
			if ( $(this).prop('checked') ) {
				c = false;
				var id = $(this).val();
				var theTitle = $('#inline_'+id+' .email').text();
				te += '<div id="ttle'+id+'"><input type=hidden name=users[] value="'+id+'"><a id="_'+id+'" class="ntdelbutton">X</a>'+theTitle+'</div>';
			}
		});

		$('#bulk-titles').html(te);
		$('#bulk-titles a').click(function(){
			var id = $(this).attr('id').substr(1);

			$('table.widefat input[value="' + id + '"]').prop('checked', false);
			$('#ttle'+id).remove();
		});
		$('html, body').animate( { scrollTop: 0 }, 'fast' );
	},

	remove : function() {
		var te = '', c = true;

		$('#bulk-remove td').attr('colspan', $('.widefat:first thead th:visible').length);
		$('table.widefat tbody').prepend( $('#bulk-remove') );
		$('#bulk-remove').addClass('inline-editor').show();

		$('tbody th.check-column input[type="checkbox"]').each(function(i){
			if ( $(this).prop('checked') ) {
				c = false;
				var id = $(this).val();
				var theTitle = $('#inline_'+id+' .email').text();
				te += '<div id="ttle'+id+'"><input type=hidden name=users[] value="'+id+'"><a id="_'+id+'" class="ntdelbutton">X</a>'+theTitle+'</div>';
			}
		});

		$('#bulk-titles').html(te);
		$('#bulk-titles a').click(function(){
			var id = $(this).attr('id').substr(1);

			$('table.widefat input[value="' + id + '"]').prop('checked', false);
			$('#ttle'+id).remove();
		});
		$('html, body').animate( { scrollTop: 0 }, 'fast' );
	},

	cancel : function() {
		$(".inline-editor").hide();
	},

	showTable: function() {
		var t = this;

		var data = {
			action: 'bulk_user_management_show_form',
			paged: getParameterByName('paged'),
			s: getParameterByName('s'),
			orderby: getParameterByName('orderby'),
			order: getParameterByName('order'),
			per_page: $("#bulk_user_management_per_page").val()
		};

		$("#wpbody-content").prepend("<div id='loading'><span>Loading...</span></div>");
		$(".actions").prepend("<img class='ajax-spinner' src='" + bulk_user_management_images + "/wpspin_light.gif'>");
		$(".wp-list-table").animate({"opacity":".3"});

		$.post(ajaxurl, data, function(response) {
			$('.widefat tbody').html( $(response).find('#the-list').html() );
			
			$('.tablenav-pages').remove();
			$('.tablenav br').before( $(response).find('.tablenav-pages').first() );

			$('a.disabled').click(function() { return false; });
			$('a[href*="admin-ajax.php"]').not('.disabled').click(function(){
				var url = $(this).attr("href");
				var paged = getParameterByName( 'paged', url ) || 1;
				var orderby = getParameterByName( 'orderby', url ) || getParameterByName( 'orderby', window.location.search ) || false;
				var order = getParameterByName( 'order', url ) || getParameterByName( 'order', window.location.search ) || false;
				var search = getParameterByName( 's', url ) || false;
				var queryString = "";

				if ( paged != 1 )
					queryString += "&paged=" + paged;

				if ( orderby )
					queryString += "&orderby=" + orderby;

				if ( order )
					queryString += "&order=" + order;

				if ( search )
					queryString += "&s=" + search;

				// Update the URL without refreshing the page if possible
				if ( history && history.pushState )
					history.pushState("", "", "?page=bulk_user_management" + queryString);
				else
					window.location = "?page=bulk_user_management" + queryString;

				// Load the table with updated state
				t.init();

				return false;
			});
		}).success(function(){
			$(".ajax-spinner, #loading").hide();
			$(".wp-list-table").animate({"opacity":"1"});
			$('.widefat tbody tr').each(function(){
				var id = $(this).find('th.check-column input[type="checkbox"]').val();
			$(this).attr('id', 'inline_'+id);
		});
		});
	}
};

$(document).ready(function(){inlineEditUser.init();});
})(jQuery);

function getParameterByName(name, url) {
	url = url || window.location.search;
	name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");
	var regexS = "[\\?&]" + name + "=([^&#]*)";
	var regex = new RegExp(regexS);
	var results = regex.exec(url);
	if(results == null)
		return "";
	else
		return decodeURIComponent(results[1].replace(/\+/g, " "));
}
