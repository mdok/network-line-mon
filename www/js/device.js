$(document).ready(() => {
    const refresh = $('.refresh').attr('href');
    const redrawGraphs = $('.redraw-graphs').attr('href');
    
	$('.interval-select').on('change', function(){
        let interval = $(this).val();    
        $.nette.ajax({
            url: redrawGraphs,
            data: {'interval': interval},

        });

    });
   	poll = () =>{
		let interval = $('.interval-select').val();
		$.nette.ajax({
			url: refresh,
			data: {'interval': interval},
   
		});
        setTimeout(poll, 60000);

	}
	poll();
});
