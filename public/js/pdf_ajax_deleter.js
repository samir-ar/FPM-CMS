$( document ).ready(function() {
    $(".deletePdf").click(function(){
        //alert($(this).data("img-id"));
        let response = confirm("Are you sure?");
        let element= $(this);
        if(response){

            let id = element.data("img-id");
            
            $.ajax({url: window.location.origin+"/admin/delete-news-pdf/"+id,
            type: 'POST',
            data:{testing:"test"},
            contentType: 'application/json; charset=utf-8',
             success: function(result){
                 console.log(result);
                element.parent().remove();
              }}
              );
          
        }
    });
  });