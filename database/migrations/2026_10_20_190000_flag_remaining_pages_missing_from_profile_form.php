<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Much bigger, pre-existing gap than the one fixed by the 180100 migration
// earlier today. These 34 pages already had correct, real route-level
// enforcement (their own distinct page-perm id in routes/web.php) long
// before today's work - but the Profile create/edit form has only ever
// shown top-level pages (`whereNull('parent_id')`), and at some point
// (the admin-menu-reorganization migration that introduced the current
// 14 top-level categories like "News & Updates", "Engagement",
// "Political Work") every one of these was re-parented under a new
// umbrella category, pushing it one level too deep for the form to see.
// Confirmed systematically: every page id actually referenced by a
// `page-perm:` route middleware, cross-checked against which pages the
// form currently exposes - these 34 were the gap. Enforcement itself was
// never wrong; only the Profile form's ability to grant/deny them was
// missing, meaning no profile other than the Super Admin's blanket grant
// could ever have been given access to any of them.
return new class extends Migration
{
    public function up(): void
    {
        $urls = [
            'admin.groups.index',
            'admin.users.index',
            'admin.talkToUs.index',
            'admin.faqsCategories.index',
            'admin.faqs.index',
            'admin.polls.index',
            'admin.memos.index',
            'admin.events.index',
            'admin.liveStream.index',
            'admin.volunteers.index',
            'admin.news.index',
            'admin.webviews.index',
            'admin.placeholders.index',
            'admin.bulkpushnotification.index',
            'admin.donation.update.form',
            'admin.internal-election.index',
            'admin.national-council-poll.index',
            'admin.laws.index',
            'admin.achievements.index',
            'admin.biography.index',
            'admin.internal-processes.index',
            'admin.app-versions.edit',
            'admin.political-work.index',
            'admin.municipality-members.index',
            'admin.mukhtars.index',
            'admin.internal-org.index',
            'admin.legislative-docs.index',
            'admin.national-plans.index',
            'admin.competency-vacancies.index',
            'admin.competency-static.index',
            'admin.checkin-events.index',
            'admin.social-links.index',
            'admin.content-engagement.index',
            'admin.user-activity.index',
            'admin.store-analytics.index',
        ];

        DB::table('pages')->whereIn('url', $urls)->update(['show_in_profile_form' => true]);
    }

    public function down(): void
    {
        // Deliberately a no-op: these pages' enforcement predates this
        // migration and isn't owned by it, only the form-visibility flag
        // was ever missing. Reversing would just reintroduce the gap.
    }
};
