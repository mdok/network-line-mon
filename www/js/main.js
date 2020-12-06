$(function () {
	$.nette.init();

	$('form').submit( () => {
		$('.spinner-container').show();
		$.nette.ajax({}).done( () => {
			$('.spinner-container').hide();
		});
		
	});
	

});
