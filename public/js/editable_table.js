let token = null;
$(document).ready( function () {
    token   = $('meta[name="csrf-token"]').attr('content');

    $('select[name="district_id"]').on('change', function(){

      $.ajax({
        url: "/admin/get-regions-by-district-id/"+$(this).val(),
        type:"POST",
        data:{
          _token: token
        },
        success:function(response){
          if(response) {
            let selectElement = $('select[name="region_id"]');
            //Clear old options
            selectElement.empty();

            response.forEach(function (item){
              selectElement.append("<option value='"+item.id+"'>"+item.name+"</option>");
            });
            console.log(response)
          }
        },
       });
    })
});


let previous;
let deleteDate = false;

    function updateDate (id,route){
      let ids = id.split("-");

      if(previous === ids[0]) return;
      previous = ids[0];
      let ele = $('#'+id);
      
      console.log(ele);
        oldBg = ele.parent().css("background-color");
        oldWidth = ele.parent().css("border");
        ele.parent().css({'background':'white'});
        ele.parent().css({'border':'2px solid black'});
         ele.children().datepicker({
            autoclose: true,
            format: 'dd/mm/yyyy',
            startDate: '-24m'
        }).focus().on('changeDate', function(e){
            console.log(e.date);
        }).on('hide',function(){
          let ele = $('#'+id);
            previous = null;
            ele.parent().css({'backgroundColor':oldBg,'border':oldWidth});

            if(deleteDate){
              deleteDate = false;
              ele.children().val("   ");
                $.ajax({
                    url: "/admin/"+route+"-delete-date/"+ids[0]+"/"+ids[1],
                    type:"DELETE",
                    data:{
                      _token: token
                    },
                    success:function(response){
                      if(response) {
                        $.toast({
                            heading: 'Success',
                            text: 'Date has been deleted successfully',
                            showHideTransition: 'slide',
                            icon: 'success',
                            position: 'top-right'
                        })
                      }
                    },
                   });   
            }else{
                $.ajax({
                    url: "/admin/"+route+"-edit-date/"+ids[0]+"/"+ids[1],
                    type:"POST",
                    data:{
                      date: ele.children().val(),
                      _token: token
                    },
                    success:function(response){
                      if(response) {
                        $.toast({
                            heading: 'Success',
                            text: 'Date has been updated successfully',
                            showHideTransition: 'slide',
                            icon: 'success',
                            position: 'top-right'
                        })
                      }
                    },
                   });
            }
            
            ele.children().datepicker('destroy');
            ele.children().off('hide keydown');
        }).on("keydown",function(e){
            if(e.which == 8){
              deleteDate = true;
              let ele = $('#'+id);
              ele.children().datepicker('hide');
            }
        });
    }


    let previousUserPopularizationNo;
    function UpdatePopularizationNo(id, url, index) {
      
        //find the element
        if(previousUserPopularizationNo === id )return;
        previousUserPopularizationNo = id;
         let ele = $('#'+'candidatePopularizationNo_'+id+(index?"_"+index:""));
         ele.prop('readonly', false);
         oldBg = ele.parent().css("background-color");
         ele.css({'border':'0px'});
         oldWidth = ele.parent().css("border");
         ele.parent().css({'background':'white'});
         ele.parent().css({'border':'2px solid black'});

         ele.keyup(function(e) {
            if (e.which == 13) // Enter key
            {
                $(this).blur();
            }
        });

        $('#'+'candidatePopularizationNo_'+id+(index?"_"+index:"")).on('blur',function(){
            ele.parent().css({'backgroundColor':oldBg,'border':oldWidth});
            ele.prop('readonly', true);
            previousUserPopularizationNo = null;
            $.ajax({
              url: url+id,
              type:"POST",
              data:{
                popularizationNo:$('#'+'candidatePopularizationNo_'+id+(index?"_"+index:"")).val(),
                _token: token
              },
              success:function(response){
                if(response) {
                  $.toast({
                      heading: 'Success',
                      text: 'Popularization number has been updated successfully',
                      showHideTransition: 'slide',
                      icon: 'success',
                      position: 'top-right'
                  })
                }
              }
             });
             ('#'+'candidatePopularizationNo_'+id+(index?"_"+index:"")).off('blur keyup');
        });
    }
    
    let previousUserId;
    function UpdateName(id){
        //find the element
        if(previousUserId === id )return;
        previousUserId = id;
         let ele = $('#'+'condidate_'+id);
         ele.prop('readonly', false);
         oldBg = ele.parent().css("background-color");
         ele.css({'border':'0px'});
         oldWidth = ele.parent().css("border");
         ele.parent().css({'background':'white'});
         ele.parent().css({'border':'2px solid black'});

         ele.keyup(function(e) {
            if (e.which == 13) // Enter key
            {
                $(this).blur();
            }
        });

        $('#'+'condidate_'+id).on('blur',function(){
            ele.parent().css({'backgroundColor':oldBg,'border':oldWidth});
            ele.prop('readonly', true);
            previousUserId = null;
            $.ajax({
              url: "/admin/candidate-update-name/"+id,
              type:"POST",
              data:{
                name:$('#'+'condidate_'+id).val(),
                _token: token
              },

              success:function(response){
                if(response) {
                  $.toast({
                      heading: 'Success',
                      text: 'Name has been updated successfully',
                      showHideTransition: 'slide',
                      icon: 'success',
                      position: 'top-right'
                  })
                }
              },
             });
             ('#'+'condidate_'+id).off('blur keyup');
        });
        
        //-- validate data
  
        //- save changes
    }


    

    function updateState(id, url){
            $.ajax({
              url: url+id,
              type:"POST",
              data:{
                state:$('#'+'applicationState_'+id).val(),
                _token: token
              },
              success:function(response){
                if(response) {
                  $.toast({
                      heading: 'Success',
                      text: 'State has been updated successfully',
                      showHideTransition: 'slide',
                      icon: 'success',
                      position: 'top-right'
                  })
                }
              }
             });
            
    }
    