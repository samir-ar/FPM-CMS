<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// news.show_for_guest existed but nothing set or used it (every row was 0).
// It now controls whether guests (and anyone opening a shared link on the web)
// can read a news item, via a "Public" tick in the news form. Guests could
// already read news today, so every existing item is ticked once to keep that
// exactly as it is; from now on an admin unticks the ones that should be
// members-only.
return new class extends Migration
{
    public function up()
    {
        DB::table('news')->update(['show_for_guest' => 1]);
    }

    public function down()
    {
        // not reversible: the old values were all 0 and carried no meaning
    }
};
