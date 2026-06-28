<?php

use App\User;
use App\Page;
use Illuminate\Database\Seeder;

class PagesSeeder extends Seeder
{

    public $tables = ['pages'];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach($this->tables as $table){
            DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $pages = [];

        $parent = new Page;
        $parent->name = 'Transactions';
        $parent->logo = 'fa fa-usd';
        $parent->url = '';
        $parent->is_parent = true;
        $parent->parent_id = null;
        $parent->save();
        $pages[] = $parent->id;

        $page = new Page;
        $page->name = 'Instant';
        $page->logo = 'fa fa-wrench';
        $page->url = 'admin.transactions.index';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $page = new Page;
        $page->name = 'Monthly Bills';
        $page->logo = 'fa fa-wrench';
        $page->url = 'admin.bills.index';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $parent = new Page;
        $parent->name = 'Administrators';
        $parent->logo = 'fa fa-wrench';
        $parent->url = '';
        $parent->is_parent = true;
        $parent->parent_id = null;
        $parent->save();
        $pages[] = $parent->id;

        $page = new Page;
        $page->name = 'All Administrators';
        $page->logo = 'fa fa-wrench';
        $page->url = 'admin.admins.index';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $page = new Page;
        $page->name = 'Add Account';
        $page->logo = 'fa fa-wrench';
        $page->url = 'admin.add_user_form';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $page = new Page;
        $page->name = 'Groups';
        $page->logo = 'fa fa-gitlab';
        $page->url = 'admin.groups.index';
        $page->is_parent = false;
        $page->parent_id = null;
        $page->save();
        $pages[] = $page->id;

        $page = new Page;
        $page->name = 'Members';
        $page->logo = 'fa fa-users';
        $page->url = 'admin.users.index';
        $page->is_parent = false;
        $page->parent_id = null;
        $page->save();
        $pages[] = $page->id;

        $page = new Page;
        $page->name = 'Talk To Us';
        $page->logo = 'fa fa-address-book';
        $page->url = 'admin.talkToUs.index';
        $page->is_parent = false;
        $page->parent_id = null;
        $page->save();
        $pages[] = $page->id;

        $parent = new Page;
        $parent->name = 'FAQ Categories';
        $parent->logo = 'fa fa-th-list';
        $parent->url = 'admin.faqsCategories.index';
        $parent->is_parent = false;
        $parent->parent_id = null;
        $parent->save();
        $pages[] = $parent->id;


        $page = new Page;
        $page->name = 'Add Category';
        $page->logo = null;
        $page->url = 'admin.faqsCategories.create';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $page = new Page;
        $page->name = 'Edit category';
        $page->logo = null;
        $page->url = 'admin.faqsCategories.edit';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();


        $parent = new Page;
        $parent->name = 'FAQ';
        $parent->logo = 'fa fa-th-list';
        $parent->url = 'admin.faqs.index';
        $parent->is_parent = false;
        $parent->parent_id = null;
        $parent->save();
        $pages[] = $parent->id;

        $page = new Page;
        $page->name = 'Add Faq';
        $page->logo = null;
        $page->url = 'admin.faqs.create';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $page = new Page;
        $page->name = 'Edit Faq';
        $page->logo = null;
        $page->url = 'admin.faqs.edit';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $parent = new Page;
        $parent->name = 'Polls';
        $parent->logo = 'fa fa-check';
        $parent->url = 'admin.polls.index';
        $parent->underline = 'admin.polls.create admin.polls.edit admin.userPolls.index';
        $parent->is_parent = false;
        $parent->parent_id = null;
        $parent->save();
        $pages[] = $parent->id;

        $page = new Page;
        $page->name = 'Add Poll';
        $page->logo = null;
        $page->url = 'admin.polls.create';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $page = new Page;
        $page->name = 'Edit Poll';
        $page->logo = null;
        $page->url = 'admin.polls.edit';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $parent = new Page;
        $parent->name = 'Memos';
        $parent->logo = 'fa fa-bullhorn';
        $parent->url = 'admin.memos.index';
        $parent->is_parent = false;
        $parent->parent_id = null;
        $parent->save();
        $pages[] = $parent->id;

        $page = new Page;
        $page->name = 'Add Memo';
        $page->logo = null;
        $page->url = 'admin.memos.create';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $page = new Page;
        $page->name = 'Edit Memo';
        $page->logo = null;
        $page->url = 'admin.memos.edit';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $parent = new Page;
        $parent->name = 'Events';
        $parent->logo = 'fa fa-calendar-o';
        $parent->url = 'admin.events.index';
        $parent->is_parent = false;
        $parent->parent_id = null;
        $parent->save();
        $pages[] = $parent->id;

        $page = new Page;
        $page->name = 'Add Event';
        $page->logo = null;
        $page->url = 'admin.events.create';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $page = new Page;
        $page->name = 'Edit Event';
        $page->logo = null;
        $page->url = 'admin.events.edit';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $page = new Page;
        $page->name = 'Event Images';
        $page->logo = null;
        $page->url = 'admin.eventImages.index';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();


        //LiveStream
        $page = new Page;
        $page->name = 'Live Stream';
        $page->logo = 'fa fa-mixcloud';
        $page->url = 'admin.liveStream.index';
        $page->is_parent = false;
        $page->parent_id = null;
        $page->save();
        $pages[] = $page->id;

        //volunteers
        $parent = new Page;
        $parent->name = 'Volunteers';
        $parent->logo = 'fa fa-hand-o-up';
        $parent->url = 'admin.volunteers.index';
        $parent->underline = 'admin.volunteers.create admin.volunteers.edit admin.volunteerUsers.index';
        $parent->is_parent = false;
        $parent->parent_id = null;
        $parent->save();
        $pages[] = $parent->id;

        //news
        $parent = new Page;
        $parent->name = 'News';
        $parent->logo = 'fa fa-book';
        $parent->url = 'admin.news.index';
        $parent->is_parent = false;
        $parent->parent_id = null;
        $parent->save();
        $pages[] = $parent->id;

        $page = new Page;
        $page->name = 'Add News';
        $page->logo = null;
        $page->url = 'admin.news.create';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $page = new Page;
        $page->name = 'Edit News';
        $page->logo = null;
        $page->url = 'admin.news.edit';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        //representatives
        $parent = new Page;
        $parent->name = 'Representatives';
        $parent->logo = 'fa fa-flag';
        $parent->url = '';
        $parent->is_parent = true;
        $parent->parent_id = null;
        $parent->save();
        $pages[] = $parent->id;

        $parent1 = new Page;
        $parent1->name = 'All Representatives';
        $parent1->logo = 'fa fa-flag';
        $parent1->url = 'admin.representatives.index';
        $parent1->is_parent = false;
        $parent1->parent_id = $parent->id;
        $parent1->save();
        $pages[] = $parent1->id;

        $page = new Page;
        $page->name = 'Add Representative';
        $page->logo = null;
        $page->url = 'admin.representatives.create';
        $page->is_parent = false;
        $page->parent_id = $parent1->id;
        $page->save();

        $page = new Page;
        $page->name = 'Edit Representative';
        $page->logo = null;
        $page->url = 'admin.representatives.edit';
        $page->is_parent = false;
        $page->parent_id = $parent1->id;
        $page->save();

        $page = new Page;
        $page->name = 'Representative Text';
        $page->logo = null;
        $page->url = 'admin.representatives.form';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        //Links
        $parent = new Page;
        $parent->name = 'Important Links';
        $parent->logo = 'fa fa-chain-broken';
        $parent->url = 'admin.links.index';
        $parent->is_parent = false;
        $parent->parent_id = null;
        $parent->save();
        $pages[] = $parent->id;

        $page = new Page;
        $page->name = 'Add Important Links';
        $page->logo = null;
        $page->url = 'admin.links.create';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $page = new Page;
        $page->name = 'Edit Important Links';
        $page->logo = null;
        $page->url = 'admin.links.edit';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        //pages content
        $parent = new Page;
        $parent->name = 'Page Contents';
        $parent->logo = 'fa fa-file';
        $parent->url = null;
        $parent->is_parent = true;
        $parent->parent_id = null;
        $parent->save();
        $pages[] = $parent->id;

        $page = new Page;
        $page->name = 'Webviews';
        $page->logo = 'fa fa-anchor';
        $page->url = 'admin.webviews.index';
        $page->underline = 'admin.webviews.create admin.webviews.edit';
        $page->is_parent = false;
        $page->parent_id = null;
        $page->save();
        $pages[] = $page->id;

        $page = new Page;
        $page->name = 'About Us';
        $page->logo = null;
        $page->url = 'admin.aboutUs.form';
        $page->is_parent = false;
        $page->parent_id = $parent->id;
        $page->save();

        $page = new Page;
        $page->name = 'Placeholders';
        $page->logo = 'fa fa-camera';
        $page->url = 'admin.placeholders.index';
        $page->underline = 'admin.placeholders.create admin.placeholders.edit';
        $page->is_parent = false;
        $page->parent_id = null;
        $page->save();
        $pages[] = $page->id;


        //sync all pages to first user
        User::first()->pages()->sync($pages);
    }
}
