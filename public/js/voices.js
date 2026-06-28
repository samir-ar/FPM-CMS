function fetchData(ids, time){


    $.ajax({
        url: '/owner/fetch-voices',
        method: 'post',
        data: {
            'date': time,
            'ids' : ids,
        },
        success: function(data){
            console.log(data);

            var table = $.fn.dataTable.tables({api: true});
            for(var i=0; i < data.length; i++){
                table.row.add([
                    data[i].id,
                    data[i].email,
                    data[i].voice,
                    ''
                ]).draw( false );
            }
        }
    });
}

$(document).ready(function() {



    /*
    setInterval(function() {
        window.location.reload(true);
    }, 10000);
     */

    /*
        get the time from url
        ajax call to get new rows
        add them without refreshing the page
        I love this game
     */

   setInterval(function() {

        var ids = [];

        //get the date from the url
        var url_string = window.location.href;
        var url = new URL(url_string);
        var time = url.searchParams.get("time");

        //get first columns from all rows
        var elements = $('tbody tr');
        var length = elements.length;
        console.log(length);

        elements.each(function(i){
            ids.push($(this).children('td:first').text());

            if((i + 1) == length){
                fetchData(ids, time);
            }
        });




    }, 10000);


});
