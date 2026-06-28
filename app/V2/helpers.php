<?php


if (! function_exists('currency_iso')) {

    function currency_iso($currency){
        //840 for USD, 422 for LBP

        if($currency == 'USD')
            return 840;
        else if($currency == 'LBP')
            return 422;

        return 0;
    }
}

if (! function_exists('remove_special_characters')) {

    function remove_special_characters($string){
        //840 for USD, 422 for LBP

        /*
        $string = htmlentities($string, null, 'utf-8');
        $content = str_replace("&nbsp;", "", $string);
        $content = html_entity_decode($content);
        */

        $my_content = strip_tags($string);

        $my_content = preg_replace("/\s|&nbsp;/",' ',$my_content);

        return $my_content;


        /*
        $string = preg_replace("/&#?[a-z0-9]+;/i","",$string);
        return $string;
        */

    }
}

if (! function_exists('iso_to_string')) {

    function iso_to_string($iso)
    {
        if($iso == '840')
            return 'USD';
        else if($iso == '422')
            return 'LBP';
    }
}


?>