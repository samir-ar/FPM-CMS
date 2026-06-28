<div class='modal fade' id='modal_".$media->id."' tabindex='-1' role='dialog' aria-labelledby='exampleModalLabel' aria-hidden='true'>
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
                                            <input type='text' class='form-control'  value='".$media->getTranslation('name', 'en' )."'
                                                name='title' />
                                        </div>
                                    </div>
                                    <div class='col-md-6'>
                                        <div class='form-group right-to-left'>
                                            <label>عنوان بالعربية:</label>
                                            <input type='text' class='form-control' value='".$media->getTranslation('name', 'ar' )."' name='title_ar'/>
                                        </div>
                                    </div>
                                </div>
                                <div class='form-group'>
                                    <label for='description'>Descriptipn in English:</label>
                                    <input type='text' class='form-control' value='".$media->getTranslation('description', 'en')."' name='description'/>
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
            </div>
            <div class='from-group' id='".$media->id."' style='margin-bottom : 100px; padding:0 15px;'>
                    <div class='row' style='margin:0px; position:relative;background-color:#f5f5f5;padding:15px; border:1px solid #d1d1d1; border-top: 5px solid #3c8dbc;'>
                        <div class='deleteMedia' title='item' data-media-id='".$media->id."' style='background-color:red;left:0; cursor:pointer; transform: translate(-50%,-50%); display:flex; justify-content:center;top:0;color:white; border-radius:50%; width:20px;height:20px;position:absolute; align-items:center;justify-content:center; padding:13px;'><span class='fa fa-trash'></div>
                        <div data-target='#modal_".$media->id."' data-toggle='modal' title='edit item' style='background-color:blue;left:0; bottom:0; cursor:pointer; transform: translate(-50%,50%); display:flex; justify-content:center;color:white; border-radius:50%; width:20px;height:20px;position:absolute;  align-items:center;justify-content:center;  padding:13px;'><span class='fa fa-pencil'></span></div>
                        <div class='col-md-5' >
                            <input readonly  value='".$media->getTranslation('name', 'ar')."' type='text' maxlength='190' style='direction: rtl; background-color:white;margin-bottom:10px;' class='form-control right-to-left' name='text_ar' placeholder='عنوان العنصر' />
                            <input readonly value='".$media->getTranslation('description', 'ar')."' type='text' maxlength='190'  style='direction: rtl; background-color:white;' class='form-control right-to-left' name='description_ar' placeholder='وصف العنصر' />                        
                        </div>
                        <div class='col-md-2' style='z-index:20;box-shadow:0px 0px 7px #0006; padding:0px;'>
                            <a href='".url('media/'.$media->file_name)."'>
                                <img style='width:100%; position:absolute; transform:translateY(-30%);   box-shadow:  0 0 8px #00000038;' src='" . url("media/thumbnail/".$media->thumbnail) . "'/>
                            </a>
                        </div>

                        <div class='col-md-5'>
                            <input readonly value='".$media->getTranslation('name', 'en')."' type='text' maxlength='190' style='margin-bottom:10px;background-color:white;' class='form-control right-to-left' name='text_ar' placeholder='Item Title' />
                            <input  readonly value='".$media->getTranslation('description', 'en')."' type='text' maxlength='190' style='background-color:white;' class='form-control right-to-left' name='description_ar' placeholder='Item Description' />                        
                        </div>
                    </div>    
                </div>
                    
            } 
        
        </div>