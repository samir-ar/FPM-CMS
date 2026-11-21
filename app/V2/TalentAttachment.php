<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

// A CV / supporting document. The file lives on the PRIVATE local disk (never a public or CDN url); only the CMS page
// "Talent CV Files" can stream it.
class TalentAttachment extends Model
{
    protected $table = 'talent_attachments';
    protected $guarded = ['id'];
}
