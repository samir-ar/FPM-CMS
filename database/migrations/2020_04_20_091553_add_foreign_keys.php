<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddForeignKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::table('app_users', function (Blueprint $table) {
            $table->index('member_id');
            $table->index('token');
            $table->index('verified');
            $table->index('verification_nb');

        });

        Schema::table('contents', function (Blueprint $table) {
            $table->index('category');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index('public');
            $table->index('to_date');
            $table->index('from_date');
        });

        Schema::table('faqs', function (Blueprint $table) {
            $table->index('order');
        });

        Schema::table('faqs_categories', function (Blueprint $table) {
            $table->index('order');
        });

        Schema::table('important_links', function (Blueprint $table) {
            $table->index('order');
            $table->index('public');
        });

        Schema::table('memos', function (Blueprint $table) {
            $table->index('order');
        });

        Schema::table('news', function (Blueprint $table) {
            $table->index('highlighted');
        });

        Schema::table('placeholders', function (Blueprint $table) {
            $table->index('type');
        });

        Schema::table('polls', function (Blueprint $table) {
            $table->index('show');
        });

        Schema::table('representatives', function (Blueprint $table) {
            $table->string('category')->change();
            $table->index('category');
            $table->index('order');
        });

        Schema::table('users_notifications', function (Blueprint $table) {
            $table->index('viewed');
        });

        Schema::table('user_favorites', function (Blueprint $table) {
            $table->index('favorite');
        });

        Schema::table('webviews', function (Blueprint $table) {
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        /*
        Schema::table('app_users', function (Blueprint $table) {
            $table->dropIndex('app_users_member_id_index');
            $table->dropIndex('app_users_token_index');
            $table->dropIndex('app_users_verified_index');
            $table->dropIndex('app_users_verification_nb_index');

        });

        Schema::table('contents', function (Blueprint $table) {
            $table->dropIndex('contents_category_index');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_public_index');
            $table->dropIndex('events_to_date_index');
            $table->dropIndex('events_from_date_index');
        });

        Schema::table('faqs', function (Blueprint $table) {
            $table->dropIndex('faqs_order_index');
        });

        Schema::table('faqs_categories', function (Blueprint $table) {
            $table->dropIndex('faqs_categories_order_index');
        });

        Schema::table('important_links', function (Blueprint $table) {
            $table->dropIndex('important_links_order_index');
            $table->dropIndex('important_links_public_index');
        });

        Schema::table('memos', function (Blueprint $table) {
            $table->dropIndex('memos_order_index');
        });

        Schema::table('news', function (Blueprint $table) {
            $table->dropIndex('news_highlighted_index');
        });

        Schema::table('placeholders', function (Blueprint $table) {
            $table->dropIndex('placeholders_type_index');
        });

        Schema::table('polls', function (Blueprint $table) {
            $table->dropIndex('polls_show_index');
        });

        Schema::table('representatives', function (Blueprint $table) {
            $table->dropIndex('representatives_category_index');
            $table->dropIndex('representatives_order_index');
        });

        Schema::table('users_notifications', function (Blueprint $table) {
            $table->dropIndex('users_notifications_viewed_index');
        });

        Schema::table('user_favorites', function (Blueprint $table) {
            $table->dropIndex('user_favorites_favorite_index');
        });
        */
        Schema::table('webviews', function (Blueprint $table) {
            $table->dropIndex('webviews_slug_index');
        });
    }
}
