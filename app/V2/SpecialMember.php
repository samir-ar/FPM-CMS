<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

// A person who needs to log in / be counted but has no official TWH member
// id. Their row in fpm_users is created and (if the TWH sync ever removes it)
// restored from this table - see SpecialMembersController.
class SpecialMember extends Model
{
    protected $fillable = ['member_id', 'full_name', 'phone', 'sector', 'district', 'email', 'note', 'created_by_admin_id'];
}
