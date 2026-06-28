<?php

namespace App\Http\Traits;
use URL;
use Illuminate\Support\Facades\View;


trait FormTrait
{
    public function drawImage($image, $width=null, $class=null)
    {
        if($image)

            return "
            <div class='$class'>
                    <a target='_blank' href='" . asset($image) . "'><img width=" . ($width ? :'320') . " src='" .asset($image)."'></a>
            </div>";

        return null;
    }

    public function drawVideo($video, $width=null)
    {
        if($video)
            return ' <video width="320" height="240" controls>
                <source src="' . asset($video) . '" type="video/mp4">
            </video> ';
        return null;
    }

    public function drawReadOnlyImage($label, $image, $class=null, $width=null)
    {

        $text = "<div class='form-group ". ($class?:'') . "'>";

        $text .=
            "<div>" .
            "<label>". $label ."</label><br>".
            "<a href='" . $image . "'><img  class='' width=" . ($width ? : 300) ." src='" . $image . "' /></a>".
            "</div>";

        $text .= "</div>";

        return $text;
    }


    public function drawDeletableVideos($medias, $deleteLink, $thumbnail=null ,  $class=null){
        $text = "<div class='form-group ".$class."'>";

        foreach($medias as $media){

             $text .="

           <div  class='from-group col-md-2' id='media_".$media->id."' style='margin-bottom : 100px; padding:0 15px;'>

                    <div     style='margin:0px; position:relative;background-color:#f5f5f5;padding:15px;padding-top: 100%;  border:1px solid #d1d1d1; border-top: 5px solid #3c8dbc;'>
                        <div data-media-id='$media->id' class='deleteMedia' data-media-url='$deleteLink' data-media-name='$media->file_name' title='item' style='background-color:red;left:0; cursor:pointer; transform: translate(-50%,-50%); display:flex; justify-content:center;top:0;color:white; border-radius:50%; width:20px;height:20px;position:absolute; align-items:center;justify-content:center; padding:13px;'>
                            <span class='fa fa-trash'></span>
                        </div>

                        <div style='z-index:20;box-shadow:0px 0px 7px #0000001f; padding:0px;margin-top:-100%;'>
                                <a style='position:relative;' href='".(($media->file_name)?asset('media/'.$media->file_name):$media->youtube)."'>
                                <img style='width:100%;positon:relative;' src='" . asset("media/thumbnail/".$media->thumbnail)."'/>
                                <img style='    width: 50%;
                                right: 0%;
                                position: absolute;
                                transform: translate(-50%, -50%);
                                top: 50%;' src='".'https://fpm-web-files.s3.eu-central-1.amazonaws.com/fpm/storage/images/play.png'."'/>
                                </a>
                        </div>
                    </div>
            </div>";

            }

            $text .= "</div>";

        return $text;
    }

    public function drawDeletableImages($images, $class=null, $width=null)
    {
        $text = "<div class='form-group '>";

        foreach($images as $image){
             $text .=
                "<div  id='".$image->id."' style='display:flex;padding:0px; border:1px solid #3c8dbc; width:100%; margin-bottom : 20px; '>" .
                "<a href='" . "https://fpm-web-files.s3.eu-central-1.amazonaws.com/fpm/storage/images/news/".$image->name . "' style='flex:1; display:flex; justify-content:center;'><img  class='$class' width=" . ($width ? : 300) ." src='" ."https://fpm-web-files.s3.eu-central-1.amazonaws.com/fpm/storage/images/news/".$image->name  . "' /></a>".
                "<a class='deleteImage btn btn-danger' data-img-id='".$image->id."'  style='display:flex;align-items:center; border-radius:0;'><span class='fa fa-trash'></span></a>".
                "</div>";
            }
            $text .= "</div>";

        return $text;
    }



    public function drawDeletableMediaImages($medias,$class=null,$url)
    {

        $text = "<div class='form-group ".$class."'>";

        foreach($medias as $media){
             $text .="

           <div  class='from-group col-md-2' id='media_".$media->id."' style='margin-bottom : 100px; padding:0 15px;'>

                    <div     style='margin:0px; position:relative;background-color:#f5f5f5;padding:15px;padding-top: 100%;  border:1px solid #d1d1d1; border-top: 5px solid #3c8dbc;'>
                        <div data-media-id='$media->id' class='deleteMedia' data-media-url='$url' data-media-name='$media->file_name' title='item' style='background-color:red;left:0; cursor:pointer; transform: translate(-50%,-50%); display:flex; justify-content:center;top:0;color:white; border-radius:50%; width:20px;height:20px;position:absolute; align-items:center;justify-content:center; padding:13px;'>
                            <span class='fa fa-trash'></span>
                        </div>

                        <div style='z-index:20;box-shadow:0px 0px 7px #0000001f; padding:0px;margin-top:-100%;'>
                                <a href='".asset('media/'.$media->file_name)."'>
                                    <img style='width:100%;   ' src='" . asset("media/".$media->thumbnail)."'/>
                                </a>
                        </div>
                    </div>
            </div>";

            }

            $text .= "</div>";

        return $text;
    }



    public function drawDeletableItems($medias,$class=null)
    {

        $text = "<div class='form-group ".$class."'>";

        foreach($medias as $media){

            /*      <div class='modal fade' id='modal_".$media->id."' tabindex='-1' role='dialog' aria-labelledby='exampleModalLabel' aria-hidden='true'>
                    <div class='modal-dialog' role='document'>
                        <div class='modal-content'>

                            <div class='modal-header'>
                                <button type='button' class='close' data-dismiss='modal' aria-label='Close'>
                                    <span aria-hidden='true'>&times;</span>
                                </button>
                            </div>

                            <div class='modal-body'>
                                <form method='POST' action='".url('admin/media-update')."'>
                                    <input type='hidden' name='_token' value='".csrf_token()."' />
                                    <input type='hidden' name='media_id' value='".$media->id."' />

                                    <div class='row'>

                                        <div class='col-md-6'>
                                            <div class='form-group'>
                                                <label>Title in English:</label>
                                                <input type='text' class='form-control'  value='".$media->getTranslation('name', 'en' )."' name='title' />
                                            </div>
                                        </div>

                                        <div class='col-md-6'>
                                            <div class='form-group right-to-left'>
                                                <label>عنوان بالعربية:</label>
                                                <input type='text' class='form-control' value='".$media->getTranslation('name', 'ar' )."' name='title_ar' />
                                            </div>
                                        </div>

                                    </div>

                                    <div class='form-group'>
                                        <label for='description'>Descriptipn in English:</label>
                                        <input type='text' class='form-control' value='".$media->getTranslation('description', 'en')."' name='description' />
                                    </div>

                                    <div class='form-group right-to-left'>
                                        <label for='description_ar'>شرح بالعربية:</label>
                                        <input type='text' class='form-control'  value='".$media->getTranslation('description', 'ar')."' name='description_ar' />
                                    </div>

                                    <div class='modal-footer'>
                                        <button type='button' class='btn btn-secondary' data-dismiss='modal'>Close</button>
                                        <button type='submit' class='btn btn-primary'>Save changes</button>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
                </div> */
             $text .="

           <div class='from-group' id='".$media->id."' style='margin-bottom : 100px; padding:0 15px;'>

                    <div class='row' style='margin:0px; position:relative;background-color:#f5f5f5;padding:15px; border:1px solid #d1d1d1; border-top: 5px solid #3c8dbc;'>
                        <div class='deleteMedia' title='item' data-media-id='".$media->id."' style='background-color:red;left:0; cursor:pointer; transform: translate(-50%,-50%); display:flex; justify-content:center;top:0;color:white; border-radius:50%; width:20px;height:20px;position:absolute; align-items:center;justify-content:center; padding:13px;'>
                            <span class='fa fa-trash'></span>
                        </div>

                        <div data-target='#modal_".$media->id."' data-toggle='modal' title='edit item' style='background-color:blue;left:0; bottom:0; cursor:pointer; transform: translate(-50%,50%); display:flex; justify-content:center;color:white; border-radius:50%; width:20px;height:20px;position:absolute;  align-items:center;justify-content:center;  padding:13px;'>
                            <span class='fa fa-pencil'></span>
                        </div>

                        <div class='col-md-5' >
                            <input readonly  value='".$media->getTranslation('name', 'ar')."' type='text' maxlength='190' style='direction: rtl; background-color:white;margin-bottom:10px;' class='form-control right-to-left' name='text_ar' placeholder='عنوان العنصر' />
                            <input readonly value='".$media->getTranslation('description', 'ar')."' type='text' maxlength='190'  style='direction: rtl; background-color:white;' class='form-control right-to-left' name='description_ar' placeholder='وصف العنصر' />
                        </div>

                        <div class='col-md-2' style='z-index:20;box-shadow:0px 0px 7px #0006; padding:0px;'>
                                <a href='".asset('media/'.$media->file_name)."'>
                                    <img style='width:100%; position:absolute; transform:translateY(-30%);   box-shadow:  0 0 8px #00000038;' src='" . asset("media/thumbnail/".$media->thumbnail)."'/>
                                </a>
                        </div>

                        <div class='col-md-5'>
                                <input readonly value='".$media->getTranslation('name', 'en')."' type='text' maxlength='190' style='margin-bottom:10px;background-color:white;' class='form-control right-to-left' name='text_ar' placeholder='Item Title' />
                                <input  readonly value='".$media->getTranslation('description', 'en')."' type='text' maxlength='190' style='background-color:white;' class='form-control right-to-left' name='description_ar' placeholder='Item Description' />
                        </div>

                    </div>

            </div>";

            }

            $text .= "</div>";

        return $text;
    }


    public function drawAlbumDeletablePdfs($pdfs,$deleteLink=null, $url="media/"){
        $text = "<div class='form-group'>";

        foreach($pdfs as $pdf){
            $text .=
                "<div  id='media_".$pdf->id."'  class='form-group col-md-12' style='display:flex;  margin-bottom : 20px; '>" .
                    "<a href='" . asset($url.$pdf->file_name) . "' style='flex:1; border:1px solid #3c8dbc;  display:flex; justify-content:center;'><p style='margin:0; padding:8px' >".$pdf->file_name."</p></a>".
                    "<a class='deleteMedia btn btn-danger'data-media-url='$deleteLink' data-media-name='$pdf->file_name'  data-media-id='".$pdf->id."'  style='display:flex;align-items:center; justify-content:center; border-radius:0;'><span class='fa fa-trash'></span></a>".
                "</div>";
            }
            $text .= "</div>";
        return $text;
    }


    public function drawDeletablePdfs($pdfs,$url="news/attachments/"){
        $text = "<div class='form-group'>";

        foreach($pdfs as $pdf){
             $text .=
                "<div  id='".$pdf->id."' class='form-group col-md-12' style='display:flex;  margin-bottom : 20px; '>" .
                "<a href='" . url($url.$pdf->name) . "' style='flex:1; border:1px solid #3c8dbc;  display:flex; justify-content:center;'><p style='margin:0; padding:8px' >".$pdf->name."</p></a>".
                "<a class='deletePdf btn btn-danger' data-img-id='".$pdf->id."'  style='display:flex;align-items:center; justify-content:center; border-radius:0;'><span class='fa fa-trash'></span></a>".
                "</div>";
            }
            $text .= "</div>";

        return $text;
    }


    public function drawAudio($audio)
    {
        $audio = "<audio controls>".
            "<source src='". asset($audio) ."'>Your browser does not support the audio element.".
            "</audio>";

        return $audio;
    }

    public function drawTableSelect($options=null)
    {
        if(!$options)
            $options = [];

        $string = '<div class="dropdown">';
        $string .= '<button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">...</button>';
        $string .= '<ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">';

        foreach($options as $option){
            $string .= "<li>" .$option. "</li>";
        }
        $string .= '</ul>';

        return $string;
    }

    public function rawField($type, $label, $name, $default = null, $options= null, $placeholder = null, $class=null){
        $field = null;


        if($type == 'small_text'){
            $field = "<input class='form-control' type='text' name='" . $name . "' value='" . ($default ?: '') . "' placeholder='" .
                ($placeholder ?:'') . "' />";
        }
        return $field;
    }



    public function drawHtml($type, $label, $name, $default = null, $options= null, $placeholder = null, $class=null,$arabicOnly=null){
        $text = "";

        if($type == 'select-box'){

            $options  = $options ? : [];
            $text = '<div class="form-group '. ($class?:'') . '">' .
                '<label>' .$label .'</label>'.
                '<select name="'. $name .'" class="form-control select2" style="width: 100%;" aria-hidden="true">';

            foreach($options as $key=>$val){
                $text .= "<option value='" . $key . "'" . ($key == $default ? 'selected' : '') .">" . $val . "</option>";
            }

            $text .= "</select></div>";
        }

        elseif($type == 'multiple-select-box'){
            $options  = $options ? : [];
            $default = $default ? : [];

            $text = '<div class="form-group '. ($class?:'') . '">' .
                '<label>' .$label .'</label>'.
                '<select name="' . $name . '" class="form-control select2 select2-hidden-accessible" multiple="" data-placeholder="Select ' . $label . '" style="width: 100%;" tabindex="-1" aria-hidden="true">';

            foreach($options as $key=>$val){
                //dd($default);
                $text .= "<option value='" . $key . "'" . ( in_array($key, $default)  ? 'selected' : '') .">" . $val . "</option>";
            }
            $text .= "</select></div>";
        }

        else if($type == 'multiple-file-upload'){
            $text = "<div class='form-group ". ($class?:'') . " '>";
            $text .= '<input type="hidden" class="myfileuploader__url" value=" ' . $options['add'] . '"/>';
            $text .= '<input type="hidden" class="myfileDelete__url" value=" ' . $options['delete'] . '"/>';
            $text .= '<input type="hidden"  name="' . $name .'" class="myFiles" />';
            $text .= '<label>'. $label . '</label><br>'.
                '<div class="__myfileuploader">Upload</div></div>';
        }


        elseif($type == 'small_text'){

            $text = "<div class='form-group ". ($class?:'') . "'>".
                "<label>" . $label . "</label>";

            $text .= "<input class='form-control' type='text' name='" . $name . "' value='" . ($default ?: '') . "' placeholder='" .
                ($placeholder ?:'') . "' />";

            $text .= "</div>";
        }

        elseif($type == 'number'){
            $text = "<div class='form-group ". ($class?:'') . "'>".
                "<label>" . $label . "</label>";

            $text .= "<input class='form-control' type='number' name='" . $name . "' value='" . ($default ?: '') . "' placeholder='" .
                ($placeholder ?:'') . "' />";

            $text .= "</div>";
        }

        elseif($type == 'hidden'){
            $text .= "<input class='form-control' type='hidden' name='" . $name . "' value='" . ($default ?: '') . "' placeholder='" .
                ($placeholder ?:'') . "' />";
        }

        else if($type == 'date-picker'){
            $text = '<div class="form-group ' . ($class?:"") . '" >'.
                '<label>' . $label .'</label>'.

                '<div class="input-group date">'.
                '<div class="input-group-addon"><i class="fa fa-calendar"></i></div>'.
                '<input type="text" name="' . $name .'" class="form-control pull-right datepicker" value="' . ($default ?: '') . '">'.
                '</div></div>';

        }

        else if($type == 'date-time-picker'){
            $text = '<div class="form-group ' . ($class?:'') . '">'.
                '<label>'. $label . '</label><br>'.
                '<div class="input-group dateTimePickerSingleContainer">'.
                '<div class="input-group-addon">'.
                '<i class="fa fa-calendar"></i>'.
                '</div>'.
                '<input type="text" class="form-control dateTimePickerSingle" name="' . $name .'" value="' . $default . '">'.
                '</div>'.
                '</div>';
        }


        else if($type == 'file'){
            $text = "<div class='form-group ". ($class?:'') . "'>";

            $text .=
                "<div class='float-left form-group'>" .
                  "<label>". $label ."</label>".
                  "<input type='file' accept='" . ($options ? $options : '*'). "' name='" . $name . "' class='form-control-file upload-img-input-file  form-control' value='" . ($default ? $default : '') . "' />".
                "</div>";

            if($default){
                $text .= "<div class='upload-img float-right'>".
                    "<img  class='uploaded-img' src='" . ($default ? 'https://fpm-web-files.s3.eu-central-1.amazonaws.com/fpm/storage/images/pdf.jpg' : '') . "' />".
                    "</div>";
            }

            $text .= "</div>";
        }

        else if($type == 'text'){

            $text = "<div class='form-group ". ($class?:'') . "'>".
                "<label>" . $label . "</label>";
            $text .= "<textarea class='form-control text-field editor' id='". $name . "' style='' type='text' name='" . $name . "'>" . $default . "</textarea>";

            $text .= "</div>";
        }

        else if($type == 'image'){

            $text = "<div class='form-group ". ($class?:'') . "'>";

            $text .=
                "<div class='float-left'>" .
                "<label>". $label ."</label>".
                "<input type='file' accept='image/*' name='" . $name . "' class='form-control-file  form-control upload-img-input-file' value='' />".
                "</div>".
                "<div class='upload-img float-right'>".
                "<img  class='uploaded-img' src='" . ($default ? URL::to($default) : '') . "' />".
                "</div>";

            $text .= "</div>";
        }

        else if($type == 'pdf'){

            $text = "<div class='form-group ". ($class?:'') . "'>";

            $text .=
                "<div class='float-left'>" .
                "<label>". $label ."</label>".
                "<input type='file' accept='application/pdf' name='" . $name . "' class='form-control-file form-control upload-img-input-file' value='" . ($default ? $default : '') . "' />".
                "</div>";

            if($default){
                $text .= "<div class='upload-img float-right'>".
                    "<img  class='uploaded-img' src='" . ($default ? 'https://fpm-web-files.s3.eu-central-1.amazonaws.com/fpm/storage/images/pdf.jpg' : '') . "' />".
                    "</div>";
            }

            $text .= "</div>";
        }

        else if($type == 'audio'){
            $text = "<div class='form-group ". ($class?:'') . "'>";

            $text .=
                "<div class='float-left'>" .
                "<label>". $label ."</label>".
                "<input type='file' accept='audio/mp3' name='" . $name . "' class='form-control-file' value='' />".
                "</div>";

            if($default)
                $text .= "<audio controls><source src='" . $default . "' type='audio/mp4'>".
                    "Your browser does not support the audio element.</audio>";


            $text .= "</div>";

        }

        else if($type == 'date_range'){
            $text = "<div class='form-group ". ($class?:'') . " '>";

            if($default){
                $from_date = $default[0] ? : null;
                $to_date = $default[1] ? : null;
            }
            else{
                $from_date = null;
                $to_date = null;
            }

            $text .= '<label>'. $label . '</label><br>'.
                '<button type="button" class="btn btn-default daterange-btn" id="' . $name . '">'.
                '<span><i class="fa fa-calendar"></i>'.($from_date ? date('d-m-y', strtotime($from_date)).' to ' : '').($to_date ? date('d-m-y', strtotime($to_date)) : ' Date range picker').
                '</span>'.
                '<input type="hidden" value="' . ($from_date ? $from_date : "") . '" class="from_date" name="'.$name.'_from_date" />'.
                '<input type="hidden" value="' . ($to_date ? $to_date : "") . '" class="to_date" name="'.$name.'_to_date" />'.
                '<i class="fa fa-caret-down"></i></button></div>';
        }

        else if($type == 'date_time_range'){
            if($default){
                $from_date = $default[0] ? : null;
                $to_date = $default[1] ? : null;
            }
            else{
                $from_date = null;
                $to_date = null;
            }


            $text = '<div class="form-group ' . ($class?:'') . '">'.
                '<label>'. $label . '</label><br>'.
                '<div class="input-group">'.
                '<div class="input-group-addon">'.
                '<i class="fa fa-calendar"></i>'.
                '</div>'.
                '<input type="text" class="form-control pull-right dateTimePicker">'.
                '<input type="hidden" class="from_date" value="' . ($from_date ? $from_date : "") . '" name="'.$name.'_from_date" />'.
                '<input type="hidden" class="to_date" value="' . ($to_date ? $to_date : "") . '" name="'.$name.'_to_date" />'.
                '</div>'.
                '</div>';


        }

        else if($type == 'time-picker'){
            $text =
                '<div class="form-group ' . ($class?:'') . '">'.
                '<label>' . $label  . '</label>'.
                '<div class="input-group">'.
                '<input type="text" class="form-control timepicker" name="' . $name .'" value="' . $default . '">'.
                '<div class="input-group-addon">'.
                '<i class="fa fa-clock-o"></i>'.
                '</div>'.
                '</div>'.
                '</div>';
        }

        else if($type == 'checkbox'){
            $text = '<div class="form-group ' . ($class?:'') . '">'.
                '<label>' . $label . '<input  type="checkbox" name="' . $name . '" class="minimal" ' .
                ($default == true ? 'checked' : "") . ' value="' . ($options ? $options : 'on') . '"> </label>'.
                '</div>';
        }

        else if($type == 'fa_select'){
            $icons = [
                'fa-glass','fa-music','fa-search','fa-envelope-o','fa-heart','fa-star','fa-star-o','fa-user','fa-film', 'fa-th-large','fa-th','fa-th-list','fa-check','fa-times','fa-search-plus',
                'fa-search-minus','fa-power-off', 'fa-signal','fa-cog','fa-trash-o','fa-home','fa-file-o','fa-clock-o','fa-road','fa-download','fa-arrow-circle-o-down','fa-arrow-circle-o-up','fa-inbox',
                'fa-play-circle-o','fa-repeat','fa-refresh','fa-list-alt','fa-lock','fa-flag','fa-headphones','fa-volume-off','fa-volume-down','fa-volume-up','fa-qrcode','fa-barcode','fa-tag',
                'fa-tags','fa-book','fa-bookmark', 'fa-print', 'fa-camera', 'fa-font', 'fa-bold', 'fa-italic', 'fa-text-height', 'fa-text-width', 'fa-align-left', 'fa-align-center', 'fa-align-right',
                'fa-align-justify', 'fa-list', 'fa-outdent', 'fa-indent', 'fa-video-camera', 'fa-picture-o',
                'fa-pencil','fa-map-marker', 'fa-adjust', 'fa-tint', 'fa-pencil-square-o', 'fa-share-square-o', 'fa-check-square-o', 'fa-arrows', 'fa-step-backward', 'fa-fast-backward', 'fa-backward', 'fa-play', 'fa-pause', 'fa-stop', 'fa-forward', 'fa-fast-forward', 'fa-step-forward', 'fa-eject', 'fa-chevron-left', 'fa-chevron-right', 'fa-plus-circle', 'fa-minus-circle', 'fa-times-circle', 'fa-check-circle', 'fa-question-circle',
                'fa-info-circle', 'fa-crosshairs', 'fa-times-circle-o', 'fa-check-circle-o', 'fa-ban', 'fa-arrow-left', 'fa-arrow-right', 'fa-arrow-up', 'fa-arrow-down', 'fa-share', 'fa-expand', 'fa-compress', 'fa-plus', 'fa-minus', 'fa-asterisk', 'fa-exclamation-circle', 'fa-gift', 'fa-leaf', 'fa-fire', 'fa-eye', 'fa-eye-slash', 'fa-exclamation-triangle', 'fa-plane', 'fa-calendar', 'fa-random', 'fa-comment',
                'fa-magnet', 'fa-chevron-up', 'fa-chevron-down', 'fa-retweet', 'fa-shopping-cart', 'fa-folder', 'fa-folder-open', 'fa-arrows-v', 'fa-arrows-h', 'fa-bar-chart', 'fa-twitter-square', 'fa-facebook-square', 'fa-camera-retro', 'fa-key', 'fa-cogs', 'fa-comments', 'fa-thumbs-o-up', 'fa-thumbs-o-down', 'fa-star-half', 'fa-heart-o', 'fa-sign-out', 'fa-linkedin-square', 'fa-thumb-tack', 'fa-external-link', 'fa-sign-in', 'fa-trophy', 'fa-github-square', 'fa-upload', 'fa-lemon-o', 'fa-phone', 'fa-square-o', 'fa-bookmark-o', 'fa-phone-square',
                'fa-twitter', 'fa-facebook', 'fa-github', 'fa-unlock', 'fa-credit-card', 'fa-rss', 'fa-hdd-o', 'fa-bullhorn', 'fa-bell', 'fa-certificate', 'fa-hand-o-right', 'fa-hand-o-left', 'fa-hand-o-up', 'fa-hand-o-down', 'fa-arrow-circle-left',
                'fa-arrow-circle-right', 'fa-arrow-circle-up', 'fa-arrow-circle-down', 'fa-globe', 'fa-wrench', 'fa-tasks', 'fa-filter', 'fa-briefcase', 'fa-arrows-alt', 'fa-users', 'fa-link', 'fa-cloud', 'fa-flask', 'fa-scissors', 'fa-files-o', 'fa-paperclip', 'fa-floppy-o', 'fa-square', 'fa-bars', 'fa-list-ul', 'fa-list-ol', 'fa-strikethrough', 'fa-underline', 'fa-table', 'fa-magic', 'fa-truck', 'fa-pinterest', 'fa-pinterest-square', 'fa-google-plus-square', 'fa-google-plus', 'fa-money', 'fa-caret-down', 'fa-caret-up', 'fa-caret-left', 'fa-caret-right', 'fa-columns', 'fa-sort', 'fa-sort-desc', 'fa-sort-asc', 'fa-envelope', 'fa-linkedin', 'fa-undo', 'fa-gavel', 'fa-tachometer', 'fa-comment-o', 'fa-comments-o', 'fa-bolt', 'fa-sitemap', 'fa-umbrella', 'fa-clipboard', 'fa-lightbulb-o', 'fa-exchange', 'fa-cloud-download', 'fa-cloud-upload', 'fa-user-md', 'fa-stethoscope', 'fa-suitcase', 'fa-bell-o', 'fa-coffee', 'fa-cutlery', 'fa-file-text-o',
                'fa-building-o', 'fa-hospital-o', 'fa-ambulance', 'fa-medkit', 'fa-fighter-jet', 'fa-beer', 'fa-h-square', 'fa-plus-square', 'fa-angle-double-left', 'fa-angle-double-right', 'fa-angle-double-up', 'fa-angle-double-down', 'fa-angle-left', 'fa-angle-right', 'fa-angle-up', 'fa-angle-down', 'fa-desktop', 'fa-laptop', 'fa-tablet', 'fa-mobile', 'fa-circle-o', 'fa-quote-left', 'fa-quote-right', 'fa-spinner', 'fa-circle', 'fa-reply', 'fa-github-alt',
                'fa-folder-o', 'fa-folder-open-o', 'fa-smile-o', 'fa-frown-o', 'fa-meh-o', 'fa-gamepad', 'fa-keyboard-o', 'fa-flag-o', 'fa-flag-checkered', 'fa-terminal', 'fa-code', 'fa-reply-all', 'fa-star-half-o', 'fa-location-arrow', 'fa-crop', 'fa-code-fork', 'fa-chain-broken', 'fa-question', 'fa-info', 'fa-exclamation', 'fa-superscript', 'fa-subscript', 'fa-eraser', 'fa-puzzle-piece', 'fa-microphone', 'fa-microphone-slash', 'fa-shield', 'fa-calendar-o', 'fa-fire-extinguisher', 'fa-rocket', 'fa-maxcdn', 'fa-chevron-circle-left', 'fa-chevron-circle-right',
                'fa-chevron-circle-up', 'fa-chevron-circle-down', 'fa-html5', 'fa-css3', 'fa-anchor', 'fa-unlock-alt', 'fa-bullseye', 'fa-ellipsis-h', 'fa-ellipsis-v', 'fa-rss-square', 'fa-play-circle', 'fa-ticket', 'fa-minus-square', 'fa-minus-square-o', 'fa-level-up', 'fa-level-down', 'fa-check-square', 'fa-pencil-square', 'fa-external-link-square', 'fa-share-square', 'fa-compass', 'fa-caret-square-o-down', 'fa-caret-square-o-up', 'fa-caret-square-o-right', 'fa-eur', 'fa-gbp', 'fa-usd', 'fa-inr', 'fa-jpy', 'fa-rub', 'fa-krw', 'fa-btc', 'fa-file', 'fa-file-text', 'fa-sort-alpha-asc', 'fa-sort-alpha-desc', 'fa-sort-amount-asc', 'fa-sort-amount-desc', 'fa-sort-numeric-asc', 'fa-sort-numeric-desc', 'fa-thumbs-up', 'fa-thumbs-down', 'fa-youtube-square', 'fa-youtube', 'fa-xing',
                'fa-xing-square', 'fa-youtube-play', 'fa-dropbox', 'fa-stack-overflow', 'fa-instagram', 'fa-flickr', 'fa-adn', 'fa-bitbucket', 'fa-bitbucket-square', 'fa-tumblr', 'fa-tumblr-square', 'fa-long-arrow-down',
                'fa-long-arrow-up', 'fa-long-arrow-left', 'fa-long-arrow-right', 'fa-apple', 'fa-windows', 'fa-android', 'fa-linux', 'fa-dribbble', 'fa-skype', 'fa-foursquare', 'fa-trello', 'fa-female', 'fa-male', 'fa-gratipay', 'fa-sun-o', 'fa-moon-o', 'fa-archive', 'fa-bug', 'fa-vk', 'fa-weibo', 'fa-renren', 'fa-pagelines', 'fa-stack-exchange', 'fa-arrow-circle-o-right', 'fa-arrow-circle-o-left', 'fa-caret-square-o-left', 'fa-dot-circle-o', 'fa-wheelchair', 'fa-vimeo-square', 'fa-try', 'fa-plus-square-o', 'fa-space-shuttle', 'fa-slack', 'fa-envelope-square', 'fa-wordpress', 'fa-openid', 'fa-university', 'fa-graduation-cap', 'fa-yahoo', 'fa-google', 'fa-reddit', 'fa-reddit-square', 'fa-stumbleupon-circle', 'fa-stumbleupon', 'fa-delicious', 'fa-digg', 'fa-pied-piper-pp', 'fa-pied-piper-alt', 'fa-drupal', 'fa-joomla', 'fa-language', 'fa-fax', 'fa-building', 'fa-child', 'fa-paw', 'fa-spoon', 'fa-cube', 'fa-cubes', 'fa-behance',
                'fa-behance-square', 'fa-steam', 'fa-steam-square', 'fa-recycle', 'fa-car', 'fa-taxi', 'fa-tree', 'fa-spotify', 'fa-deviantart', 'fa-soundcloud', 'fa-database', 'fa-file-pdf-o', 'fa-file-word-o', 'fa-file-excel-o', 'fa-file-powerpoint-o', 'fa-file-image-o', 'fa-file-archive-o', 'fa-file-audio-o', 'fa-file-video-o', 'fa-file-code-o', 'fa-vine', 'fa-codepen', 'fa-jsfiddle', 'fa-life-ring', 'fa-circle-o-notch', 'fa-rebel', 'fa-empire', 'fa-git-square', 'fa-git', 'fa-hacker-news', 'fa-tencent-weibo', 'fa-qq', 'fa-weixin', 'fa-paper-plane', 'fa-paper-plane-o', 'fa-history', 'fa-circle-thin', 'fa-header', 'fa-paragraph', 'fa-sliders', 'fa-share-alt', 'fa-share-alt-square', 'fa-bomb',
                'fa-futbol-o','fa-tty', 'fa-binoculars', 'fa-plug','fa-slideshare', 'fa-twitch', 'fa-yelp', 'fa-newspaper-o', 'fa-wifi', 'fa-calculator', 'fa-paypal', 'fa-google-wallet', 'fa-cc-visa', 'fa-cc-mastercard', 'fa-cc-discover', 'fa-cc-amex', 'fa-cc-paypal', 'fa-cc-stripe', 'fa-bell-slash', 'fa-bell-slash-o', 'fa-trash', 'fa-copyright', 'fa-at', 'fa-eyedropper', 'fa-paint-brush', 'fa-birthday-cake', 'fa-area-chart', 'fa-pie-chart', 'fa-line-chart', 'fa-lastfm', 'fa-lastfm-square', 'fa-toggle-off', 'fa-toggle-on', 'fa-bicycle', 'fa-bus', 'fa-ioxhost', 'fa-angellist', 'fa-cc',
                'fa-ils', 'fa-meanpath','fa-buysellads', 'fa-connectdevelop', 'fa-dashcube', 'fa-forumbee', 'fa-leanpub', 'fa-sellsy', 'fa-shirtsinbulk', 'fa-simplybuilt', 'fa-skyatlas', 'fa-cart-plus', 'fa-cart-arrow-down', 'fa-diamond','fa-ship','fa-user-secret', 'fa-motorcycle', 'fa-street-view', 'fa-heartbeat', 'fa-venus', 'fa-mars', 'fa-mercury', 'fa-transgender', 'fa-transgender-alt', 'fa-venus-double', 'fa-mars-double', 'fa-venus-mars', 'fa-mars-stroke', 'fa-mars-stroke-v', 'fa-mars-stroke-h', 'fa-neuter', 'fa-genderless', 'fa-facebook-official', 'fa-pinterest-p', 'fa-whatsapp', 'fa-server', 'fa-user-plus', 'fa-user-times', 'fa-bed', 'fa-viacoin', 'fa-train', 'fa-subway', 'fa-medium', 'fa-y-combinator', 'fa-optin-monster', 'fa-opencart', 'fa-expeditedssl', 'fa-battery-full', 'fa-battery-three-quarters', 'fa-battery-half', 'fa-battery-quarter', 'fa-battery-empty', 'fa-mouse-pointer', 'fa-i-cursor', 'fa-object-group', 'fa-object-ungroup', 'fa-sticky-note', 'fa-sticky-note-o', 'fa-cc-jcb', 'fa-cc-diners-club', 'fa-clone', 'fa-balance-scale', 'fa-hourglass-o', 'fa-hourglass-start', 'fa-hourglass-half', 'fa-hourglass-end', 'fa-hourglass', 'fa-hand-rock-o', 'fa-hand-paper-o', 'fa-hand-scissors-o',
                'fa-hand-lizard-o', 'fa-hand-spock-o', 'fa-hand-pointer-o', 'fa-hand-peace-o', 'fa-trademark', 'fa-registered', 'fa-creative-commons', 'fa-gg', 'fa-gg-circle', 'fa-tripadvisor', 'fa-odnoklassniki', 'fa-odnoklassniki-square', 'fa-get-pocket', 'fa-wikipedia-w', 'fa-safari', 'fa-chrome', 'fa-firefox', 'fa-opera', 'fa-internet-explorer', 'fa-television', 'fa-contao', 'fa-500px', 'fa-amazon', 'fa-calendar-plus-o', 'fa-calendar-minus-o', 'fa-calendar-times-o', 'fa-calendar-check-o', 'fa-industry', 'fa-map-pin', 'fa-map-signs', 'fa-map-o', 'fa-map', 'fa-commenting', 'fa-commenting-o', 'fa-houzz', 'fa-vimeo', 'fa-black-tie', 'fa-fonticons', 'fa-reddit-alien', 'fa-edge', 'fa-credit-card-alt', 'fa-codiepie', 'fa-modx', 'fa-fort-awesome', 'fa-usb', 'fa-product-hunt', 'fa-mixcloud', 'fa-scribd', 'fa-pause-circle', 'fa-pause-circle-o', 'fa-stop-circle', 'fa-stop-circle-o', 'fa-shopping-bag', 'fa-shopping-basket', 'fa-hashtag', 'fa-bluetooth', 'fa-bluetooth-b', 'fa-percent', 'fa-gitlab', 'fa-wpbeginner', 'fa-wpforms', 'fa-envira', 'fa-universal-access', 'fa-wheelchair-alt', 'fa-question-circle-o', 'fa-blind', 'fa-audio-description',
                'fa-volume-control-phone', 'fa-braille', 'fa-assistive-listening-systems', 'fa-american-sign-language-interpreting', 'fa-deaf', 'fa-glide', 'fa-glide-g', 'fa-sign-language', 'fa-low-vision', 'fa-viadeo', 'fa-viadeo-square', 'fa-snapchat', 'fa-snapchat-ghost', 'fa-snapchat-square', 'fa-pied-piper', 'fa-first-order', 'fa-yoast', 'fa-themeisle', 'fa-google-plus-official', 'fa-font-awesome',
            ];

            $text = '<div class="form-group '. ($class?:'') . '">' .
                '<label>' .$label .'</label>';


            $text .= '<select class="select2 font-awesome select2-hidden-accessible" name="'. $name .'" class="form-control select2" style="width: 100%;" aria-hidden="true">';

            foreach($icons as $icon){
                $text .= "<option value='" . $icon . "'" . ($icon == $default ? 'selected' : '') ." data-icon='" . $icon . "'>" . $icon . "</option>";
            }

            $text .= "</select></div>";
        }

        else if($type == 'cloneable_input_field'){
            //only works with text field and icons
            $fields = [];
            $defaults = [];

            foreach($default as $d){

                $defaults[] = $this->drawHtml($d['type'], $d['label'], $d['name'].'[]', $d['default'], (isset($d['options']) ? $d['options'] : null),null, $d['class'] .' cloneable_input_field');
            }


            foreach($options as $option){
                $fields[] = $this->drawHtml($option['type'], $option['label'], $option['name'].'[]', null, (isset($option['options']) ? $option['options'] : null),null, $option['class'] . ' cloneable_input_field');
            }


            $text = View::make('form_layouts.cloneable_input_field')
                ->with(compact('defaults', 'fields'))
                ->render();
        }

        else if($type == 'questions_answers'){
            $text = View::make('form_layouts.question_answers_cloneable')
                ->with(compact('label', 'name', 'default', 'options', 'placeholder', 'class'))
                ->render();
        }

        else if($type == 'multi_option'){

            $view = ($arabicOnly)?'form_layouts.options_cloneable_arabic_only':'form_layouts.options_cloneable';
            $text = View::make($view)
                ->with(compact('label', 'name', 'default', 'options', 'placeholder', 'class'))
                ->render();

        }




        return $text;

    }
}
?>
