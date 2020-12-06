App = {};

App.checkThreshold = () =>{
    $('.over-thresh').removeClass('over-thresh');
    str = JSON.stringify(App.thresholds);
    for(let key in App.thresholds){
        if(App.thresholds[key]['over_threshold'] === true){
            $('.'+key).addClass('over-thresh');
            $('.'+key+'.sla-graph').addClass('over-thresh');
        }
    }
}

App.poll = () =>{
    let interval = $('.interval-select').val();
    $.nette.ajax({
        url: App.refresh,
        data: {'interval': interval},
        success: function(payload) {
            App.thresholds = payload.thresholds;
        },
        complete: () =>{
            App.checkThreshold();
        }
    });
    setTimeout(App.poll, 60000);

}

$(document).ready(() =>{
    App.refresh = $('.refresh').attr('href');
    App.redrawGraphs = $('.redraw-graphs').attr('href');
    App.poll();

    $('.interval-select').on('change', function(){
        let interval = $(this).val();    
        $.nette.ajax({
                url: App.redrawGraphs,
                data: {'interval': interval},
                complete: () =>{
                    App.checkThreshold();
                }

        });

    });
    $('.scroll-up').click(()=>{
        $('html, body').animate({ scrollTop: 0 }, 'slow');
        return false;
    });
    
});
