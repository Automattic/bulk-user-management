(function($) {
inlineEditUser = {

	init : function(){
		var t = this, bulkRow = $('#bulk-edit');

		$('.widefat tbody tr').each(function(){
			var id = $(this).find('th.check-column input[type="checkbox"]').val();
			$(this).attr('id', 'inline_'+id);
		});

		$('#doaction, #doaction2').click(function(e){
			var n = $(this).attr('id').substr(2);
			if ( $('select[name="'+n+'"]').val() == 'modify' ) {
				e.preventDefault();
				t.setBulk();
			}
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

		//TODO: revert

		$('#bulk-titles').html(te);
		$('#bulk-titles a').click(function(){
			var id = $(this).attr('id').substr(1);

			$('table.widefat input[value="' + id + '"]').prop('checked', false);
			$('#ttle'+id).remove();
		});
		$('html, body').animate( { scrollTop: 0 }, 'fast' );
	}
};

$(document).ready(function(){inlineEditUser.init();});
})(jQuery);

