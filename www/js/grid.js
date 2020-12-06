App = {};

App.loadSlas = (id) =>{
    let activeSlas = App.allActiveSlas[id];
    let linesToSlas = App.allLinesToSlas[id];

    for(let key in linesToSlas){
        if($.inArray(key,activeSlas) > -1){
            $('.sla.'+id+'.'+key).show();
            $('.threshold.'+id+'.'+key).show();
        }
        else{
            $('.sla.'+id+'.'+key).hide();
            if ($('.threshold.'+id+'.'+key).hasClass('echo')){
                $('.threshold.'+id+'.'+key+'.echo').show();
            }
            else{
                $('.threshold.'+id+'.'+key).hide(); 

            }
        }   
    }
}
App.reloadSlas = () =>{
    if(App.allActiveSlas === null){
        App.allActiveSlas = Object.values(App.allLinesToSlas);
    }
    for(id in App.allActiveSlas){
        App.loadSlas(id);
    }
}
App.reaplyFilters = () =>{
    if(App.filter===1){
        $('.type-2').hide();
    }
    if (App.filter===2){
        $('.type-1').hide();  
    }
}
App.checkThreshold = () =>{
    $('.over-thresh').removeClass('over-thresh');
    $('.threshold').children().hide();
    for(let line_id in App.thresholds ){
        let line_thresholds = App.thresholds[line_id];
        for(let key in line_thresholds){
            if(line_thresholds[key]['over_threshold']===true){
                $('.data-point.'+line_id).addClass('over-thresh');
                $('.sla.'+line_id+'.'+key).addClass('over-thresh-sla');
                $('.threshold.'+line_id+'.'+key).addClass('over-thresh-sla');
                $('.threshold.'+line_id+'.'+key+'.over-thresh-sla').children().show();

            }
        }
    }
   
}

App.poll = () => {
    $.nette.ajax({
        url: App.refresh,
        success: function(payload) { 
            App.thresholds = payload.thresholds;
            App.aliases = payload.aliases;
            App.allActiveSlas = JSON.parse(payload.activeSlasGrid);
            App.allLinesToSlas = payload.linesToSlas;
        },
        complete: () =>{
            App.reloadSlas();
            App.reaplyFilters();
            App.checkThreshold();
        }
        
    });
    setTimeout(App.poll, 60000);
}
App.init = () =>{
    App.modal = $('.modal-edit-form');
    App.refresh = $('.refresh').attr('href');
    App.saveGrid = $('.save-grid').attr('href');
    App.filter = 0;
    
}
$(document).ready( () => {
    App.init();
    App.poll();

    $(document).on('click','.edit-sla',()=>{
        $('.modal-content').remove();
        let id = event.target.id;
        let activeSlas = App.allActiveSlas[id];
        
        const modalContent = $(`<div class="modal-content"></div>`);
        const modalUncheckAll = $(`<button class="uncheck-all" id="${ id }">uncheck all</button>`);
        const modalCheckAll = $(`<button class="check-all" id="${ id }">check all</button>`);
        const modalSave =  $(`<button class="save-edit" id="${ id }">save</button>`);
        const modalCancel =  $(`<button class="cancel-edit" id="${ id }">&times;</button>`);
        const modalForm =   $(`<form></form>`);

        let labels = '';
        for(let key in App.allLinesToSlas[id]){
            labels +=`<div><label>${ App.aliases[key] }</label><input class="modal ${ id } ${ key }" type="checkbox"></div>`;
        }
        modalForm.append(labels);

        modalCheckAll.click(()=>{
            let linesToSlas = App.allLinesToSlas[id];
            for(let key in linesToSlas){
                $('.modal.'+id+'.'+key).prop('checked',true);
            }
        });
        modalUncheckAll.click(()=>{
            let linesToSlas = App.allLinesToSlas[id];
            for(let key in linesToSlas){
                $('.modal.'+id+'.'+key).prop('checked',false);
            }
        });
        modalSave.click(()=>{
            let active = [];
            let linesToSlas = App.allLinesToSlas[id];
            for(let key in linesToSlas){
                if($('.modal.'+id+'.'+key).is(':checked') === true){
                    active.push(key);
                }
            }
            App.allActiveSlas[id] = active;
            App.modal.hide();
            App.loadSlas(id);

            $.nette.ajax({
                type: 'POST', 
                url: App.saveGrid, 
                data: {'data': JSON.stringify(App.allActiveSlas)},    
            });
        });
        modalCancel.click(()=>{
            $('.modal-content').remove();
            App.modal.hide();
        });

        modalContent.append(modalUncheckAll);
        modalContent.append(modalCheckAll);
        modalContent.append(modalCancel);
        modalContent.append(modalForm);
        modalContent.append(modalSave);
        App.modal.append(modalContent);

        for(let key in activeSlas){
            $('.modal.'+id+'.'+activeSlas[key]).prop('checked',true);
        }
        App.modal.show();
    
    });

    
    
    $('.filter-echo').click(() =>{
        if($('.filter-echo').hasClass('echo-active')){
            App.filter=0;
            $('.filter-echo').removeClass('echo-active');
            $('.type-2').show(); 
        }

        else{
            App.filter=1;
            $('.filter-echo').addClass('echo-active');
            $('.type-2').hide();
            $('.filter-jitter').removeClass('jitter-active');
            $('.type-1').show(); 
        }
    });

    $('.filter-jitter').click(() =>{
        if($('.filter-jitter').hasClass('jitter-active')){
            App.filter=0;
            $('.filter-jitter').removeClass('jitter-active');
            $('.type-1').show(); 
        }
        else{
            App.filter=2;
            $('.filter-jitter').addClass('jitter-active');
            $('.type-1').hide();  
       
            $('.filter-echo').removeClass('echo-active');
            $('.type-2').show(); 
        }
    });  
});
