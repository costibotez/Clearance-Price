;(function($){
	$(document).ready(function(){
		$('input[name="clearance_price_cb"]').on('click', function(){
			if($(this).is(":checked")) {
				$(this).closest('table').next().fadeIn('slow').next().fadeIn('slow').next().fadeIn('slow').next().fadeIn('slow');
			}
			else {
				$(this).closest('table').next().fadeOut('slow').next().fadeOut('slow').next().fadeOut('slow').next().fadeOut('slow');
			}
		});
	});
})(jQuery);