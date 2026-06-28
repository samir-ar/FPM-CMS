$(document).ready(function () {

    $(".multiImageUpload").hide();
    $(".pdfUploader").hide();


    $(".changeMediaType").change(function (e) {
        $(".multiImageUpload").hide();
        $(".videoUploader").hide();
        $(".pdfUploader").hide();

        switch (e.target.value) {
            case "pdfs":
                $(".pdfUploader").show();
                break;

            case "videos":
                $(".videoUploader").show();
                break;

            case "images":
                $(".multiImageUpload").show();
                break;
        }
    });



    $(".deleteMedia").click(function () {
        let response = confirm("Aredd you sure?");
        let element= $(this);
        let id =element.data('media-id');
        let url =element.data('media-url');
       console.log(url)
        
        if (response) {

            let baseUrl = window.location.origin+"/";

            $.ajax({

                url: baseUrl+url,
                type: 'POST', 
                data: {
                    _token: token,
                    id: id,
                    name: $(this).data('media-name')
                },
            
                success: function (result) {
                    $('#media_' + id).remove();
                },

                fail: function () {
                    console.log("fail")
                }
            
            }
            );

        }
    });



})