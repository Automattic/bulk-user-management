(function($) {
inlineEditUser = {

	init : function(){
		var t = this, bulkRow = $('#bulk-edit');

		$('.widefat tbody tr').each(function(){
			var id = $(this).find('th.check-column input[type="checkbox"]').val();
			$(this).attr('id', 'inline_'+id);
		});

		// Submit bulk edit option
		$('#doaction, #doaction2').click(function(e){
			t.cancel();
			var n = $(this).attr('id').substr(2);
			if ( $('select[name="'+n+'"]').val() == 'modify' ) {
				e.preventDefault();
				t.setBulk();
			}
			if ( $('select[name="'+n+'"]').val() == 'remove' ) {
				console.log('removed');
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
	}
};

$(document).ready(function(){inlineEditUser.init();});
})(jQuery);

