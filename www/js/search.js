$(document).ready( () => {
	$('.cancel-search').hide();
	$('.search-input').on('input',() => {
        $('.line').hide();
        $('.device').hide();
		let search = $('.search-input');
		let searchstring = search.val().toLowerCase().trim();
        let lineChildren = $('.line > div');
        let deviceChildren = $('.device > div');
		lineChildren.filter((i,div)=>{
    		return $(div).text().toLowerCase().indexOf(searchstring) >= 0;
		}).parent().show();
		deviceChildren.filter((i,div)=>{
    		return $(div).text().toLowerCase().indexOf(searchstring) >= 0;
		}).parent().show();
		if(!searchstring){
			$('.cancel-search').hide();
		}
		else{
			$('.cancel-search').show();
		}
	});

	$('.cancel-search').click(() =>{
		$('.cancel-search').hide();
        $('.line').show();
        $('.device').show();
		$('.search-input').val('');

	});

});
