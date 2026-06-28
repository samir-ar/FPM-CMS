$(document).ready(function(){
    $('body').on('click', '.publish', function() {
        $ele =  $(this);
        $ele.toggleClass('btn-success');


        $.ajax({
            url: "/admin/internal-election/publish/"+$ele.attr('id'),
            data: JSON.stringify({ 
                '_token':  $('meta[name=csrf-token]').attr('content')
             }),
             method: 'POST'
          });
    });
});