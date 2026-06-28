<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class ApplicationState extends Model
{
    public static $APPROVED = "APPROVED";
    public static $DENIED = "DENIED";
    public static $WAITING = "WAITING";

    public static $STATES = array(
        "waiting"  => "WAITING",
        "approved" => "APPROVED", 
        "denied" => "DENIED" 
    );
}
