App = {};

App.loadSlas = (id) =>{
    let activeSlas = App.allActiveSlas[id];
    let lineSlas = App.allSlas[id];

    for(let key in lineSlas){
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
        App.allActiveSlas = Object.values(App.allSlas);
    }
    for(id in App.allActiveSlas){
        App.loadSlas(id);
    }
    
}
App.checkThreshold = () =>{
    $('.over-thresh').removeClass('over-thresh');
    $('.threshold').children().hide();

   for(let line_id in App.thresholds ){
       let line_thresholds = App.thresholds[line_id];
       for(let key in line_thresholds){
           if(line_thresholds[key]['over_threshold']===true){
               $('.threshold.'+line_id+'.'+key).parents('.data-point.data').last().addClass('over-thresh');
               $('.sla.'+line_id+'.'+key).addClass('over-thresh-sla');
               $('.threshold.'+line_id+'.'+key).addClass('over-thresh-sla');
               $('.threshold.'+line_id+'.'+key+'.over-thresh-sla').children().show();
           }
       }
   }
}
App.poll = () =>{
   $.nette.ajax({
       url: App.refresh,
       success: function(payload){
           App.thresholds = payload.thresholds;
           App.aliases = payload.aliases;
           App.allActiveSlas = JSON.parse(payload.activeSlasMatrix);
           App.allSlas = payload.slas;
       },
       complete: function(){
           App.checkThreshold();
           App.reloadSlas();
       }

   });
   setTimeout(App.poll, 60000);

}

App.init = () =>{
    App.modal = $('.modal-edit-form');
    App.refresh = $('.refresh').attr('href');
    App.saveMatrix = $('.save-matrix').attr('href');
    
}

$(document).ready(() =>{
    App.init();
    App.poll();

    $(document).on('click','.edit-sla',()=>{
        $('.modal-content').remove();
        let id = event.target.id;
        let activeSlas = App.allActiveSlas[id];
        
        const modalContent = $(`<div class="modal-content"></div>`);
        const modalCancel =  $(`<button class="cancel-edit" id="${ id }">&times;</button>`);
        const modalUncheckAll = $(`<button class="uncheck-all" id="${ id }">uncheck all</button>`);
        const modalCheckAll = $(`<button class="check-all" id="${ id }">check all</button>`);
        const modalSave =  $(`<button class="save-edit" id="${ id }">save</button>`);
        const modalForm =   $(`<form></form>`);
        
        let labels = '';
        for(let key in App.allSlas[id]){
            if(App.aliases[key]){
                labels += `<div class="input-pair"><label>${ App.aliases[key] }</label><input class="modal ${ id } ${ key }" type="checkbox"></div>`;
            }
        }
        modalForm.append(labels);


        modalCheckAll.click(()=>{
            let lineSlas = App.allSlas[id];
            for(let key in lineSlas){
                $('.modal.'+id+'.'+key).prop('checked',true);
            }
        });
        modalUncheckAll.click(()=>{
            let lineSlas = App.allSlas[id];
            for(let key in lineSlas){
                $('.modal.'+id+'.'+key).prop('checked',false);
            }
        });
        modalSave.click(()=>{
            let active = [];
            let lineSlas = App.allSlas[id];
            for(let key in lineSlas){
                if($('.modal.'+id+'.'+key).is(':checked') === true){
                    active.push(key);
                }
            }
            App.allActiveSlas[id] = active;
            App.modal.hide();
            App.loadSlas(id);

            $.nette.ajax({
                type: 'POST', 
                url: App.saveMatrix,
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

    
});
