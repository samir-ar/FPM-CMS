<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/




Route::group(['namespace' => 'Api\V2', 'prefix' => 'v2/', 'middleware' => ['lang']], function(){
    // Route::post('google-forms', 'ApiController@getInternalProcess');
    // Route::post('single-google-form', 'ApiController@getSingleInternalProcess');

    Route::post('send-verification', 'RegistrationController@sendVerfication');
    Route::post('verify-pin', 'RegistrationController@verify_pin');
    Route::post('country-codes', 'ApiController@getCountryCodes');
    Route::post('app-versions', 'ApiController@appVersions');
    Route::post('business-type', 'CommunityController@getBusinessTypes');
    Route::post('social-links', 'CommunityController@getSocialLinks');
    Route::post('get-community', 'CommunityController@getCommunityPosts');
    Route::post('directory-members', 'CommunityController@getDirectoryMembers');
    Route::post('community-districts', 'CommunityController@getDistricts');
    Route::get('image-proxy', 'CommunityController@imageProxy');
    Route::get('member-photo/{memberId}', 'ApiController@getMemberPhoto');

    Route::group(['middleware' => ['my-auth-v2', 'fpm-auth']], function () {
        Route::post('my-profile', 'ApiController@myProfile');
        Route::post('refresh-permissions', 'ApiController@refreshPermissions');
        Route::post('update-profile-info', 'ApiController@updateProfileInfo');
        Route::post('my-directory-listing-status', 'CommunityController@getMyDirectoryListingStatus');
        Route::post('toggle-directory-listing', 'CommunityController@toggleMyDirectoryListing');
        // Route::post('get-qr-code', 'ApiController@getQRCode');
        Route::post('google-forms', 'ApiController@getInternalProcess');
        Route::post('single-google-form', 'ApiController@getSingleInternalProcess');
        Route::post('memos', 'ApiController@getMemos');
        Route::post('members', 'ApiController@getMembers');
        Route::post('favorite-members', 'ApiController@getFavorites');

        // Signed (app-signature) on top of the normal login, same as the
        // Internal Election: proves the request came from the real app, not
        // a script reusing a captured token. The app attaches the signature
        // headers to every request, so no client change is needed to be
        // accepted here (the vote body's nested answers list is excluded
        // from signing on both sides - see app_signature_interceptor.dart).
        Route::group(['middleware' => ['app-signature']], function () {
            Route::post('national-council-poll-can-i-vote', 'ApiController@canIVoteToNationalCouncilPollMessage');
            Route::post('national-council-polls', 'ApiController@listNationalCouncilPolls');
            Route::post('national-council-poll-get', 'ApiController@getLatestNationalCouncilPoll');
            Route::post('national-council-poll-vote', 'ApiController@voteToNationalPoll');
        });


        /////////////////𝕀𝕟𝕥𝕖𝕣𝕟𝕒𝕝 𝔼𝕝𝕖𝕔𝕥𝕚𝕠𝕟/////////////////////
        // Signed (app-signature) on top of the normal auth — these hand
        // back real member PII (candidate photos tied to member_id, full
        // district voter lists with phone numbers), so beyond "is this a
        // valid login" we also require proof the request came from the
        // real app, not a script reusing a captured token.
        Route::group(['middleware' => ['app-signature']], function () {
            Route::get('get-internal-election-candidates','ApiController@getInternalElectionCandidates');
            Route::post('internal-election-selfie-upload-url','ApiController@internalElectionSelfieUploadUrl');
            Route::post('internal-election-vote','ApiController@internalElectionVote');
            Route::get('can-i-vote','ApiController@canIVoteEndpoint');
            Route::get('my-district-voters','ApiController@getMyDistrictVoters');
        });
        //////////////𝔼𝕟𝕕 𝕀𝕟𝕥𝕖𝕣𝕟𝕒𝕝 𝔼𝕝𝕖𝕔𝕥𝕚𝕠𝕟///////////////////

        // بلديات و مخاتير (names + phone numbers of every municipality member / mukhtar) and التنظيم الداخلي
        // (the party's internal rules). Both screens exist only for LOGGED-IN members in the app, yet these
        // endpoints used to answer anyone (بلديات/مخاتير needed only the request signature, التنظيم الداخلي
        // nothing at all). Now, like the voters list: a valid member login AND the signed-request check, plus a
        // per-member limit and an audit log of every pull (see guardDirectoryPull in ApiController). The app
        // already signs every request and sends the member's token, so no app change is needed.
        Route::group(['middleware' => ['app-signature']], function () {
            Route::post('internal-org', 'ApiController@getInternalOrg');
            Route::post('municipalities', 'ApiController@getMunicipalities');
            Route::post('municipality-members', 'ApiController@getMunicipalityMembers');
            Route::post('mukhtar-villages', 'ApiController@getMukhtarVillages');
            Route::post('mukhtars', 'ApiController@getMukhtars');
        });


        //New Endpoints

        #tracker
        Route::post('get-my-candidates-status','ApiController@getCandidates');
        Route::post('get-my-candidacy-status','ApiController@getCandidacy');

        //Albums
        //Route::post('delete-album','ApiController@deleteAlbum');
        //Route::post('delete-media','ApiController@deleteMedia');
        //Route::post('create-album','ApiController@createAlbum');
        //Route::post('add-media','ApiController@addMedia');



        //End of New Endpoints

        Route::post('become-a-volunteer', 'ApiController@becomeVolunteer');
        Route::post('volunteers', 'ApiController@volunteers');
        Route::post('news-like', 'ApiController@likeNews');
        Route::post('news-share', 'ApiController@shareNews');
        Route::post('event-like', 'ApiController@likeEvent');
        Route::post('event-share', 'ApiController@shareEvent');
        Route::post('upload-profile-photo', 'ApiController@uploadProfilePhoto')->middleware('throttle:12,1');
        Route::post('remove-profile-photo', 'ApiController@removeProfilePhoto')->middleware('throttle:12,1');
        Route::post('make-favorite', 'ApiController@favoriteMember');
        Route::post('talk-to-us', 'ApiController@userTalk');
        Route::post('logout', 'ApiController@logout');
        Route::post('messages', 'ApiController@getMessages');
        Route::post('getGroups', 'ApiController@getGroups');
        Route::post('single-news', 'ApiController@getNewsById');
        Route::post('live-stream', 'ApiController@getLiveStream');

        Route::post('event-checkin', 'ApiController@checkInMember');
        Route::post('checkin-events', 'ApiController@getCheckinEvents');
        Route::post('council-meeting-checkin', 'ApiController@councilMeetingCheckIn');

        Route::post('get-my-community', 'CommunityController@getMyCommunityPosts');
        Route::post('create-community', 'CommunityController@createCommunityPost');
        Route::post('update-community/{id}', 'CommunityController@updateCommunityPost');


        /////// Delete Account /////////////////////
        Route::post('delete-account-verification', 'ApiController@deleteAccountVerification');
        Route::post('delete-account', 'ApiController@deleteAccount');
        ////////////////////////////////////////////

        Route::post('competency-vacancies', 'ApiController@getCompetencyVacancies');
        Route::post('competency-profile', 'ApiController@getCompetencyProfile');
        Route::post('competency-nominations', 'ApiController@submitCompetencyNomination');
    });


    Route::group(['middleware' => ['not-required-token', 'fpm-auth']], function(){
        Route::post('news', 'ApiController@getNews');
        Route::post('paginated-news', 'ApiController@getNewsWithPagination')->name('api.news.paginated');
        Route::post('important-links', 'ApiController@getLinks');
        Route::post('webviews', 'ApiController@getWebviews');
        Route::post('i-pay', 'ApiController@pay');
        Route::post('e-bill', 'ApiController@bill');
        Route::post('get-wall-feed', 'ApiController@getWallFeed');

        // Guest-visible per admin's "Show For Guest" flag (see feature/guest-access-rules)
        Route::post('previous-events', 'ApiController@getPreviousEvents');
        Route::post('upcoming-events', 'ApiController@getUpcomingEvents');
        Route::post('upcoming-events-paginated', 'ApiController@getUpcomingEventsWithPagination');
        Route::post('previous-events-paginated', 'ApiController@getPreviousEventsWithPagination');
        Route::post('single-event', 'ApiController@getEventById');

        // Archive is fully open to guests — no per-item filtering
        Route::post('get-all-albums', 'ApiController@getAllAlbums');
        Route::post('get-album', 'ApiController@getAlbum');

        // Polls are visible to every guest — each poll's own "Show For Guest"
        // flag decides whether they can actually vote (can_participate),
        // not whether the poll shows up at all.
        Route::post('get-poll', 'ApiController@getPollById');
        Route::post('current-polls', 'ApiController@getPolls');
        Route::post('previous-polls', 'ApiController@getPreviousPolls');
        Route::post('answer-poll', 'ApiController@answerPoll');
    });


    Route::group(['middleware' => ['my-auth']], function(){
        Route::post('memo-files', 'ApiController@downloadMemoFiles');
        Route::post('login', 'RegistrationController@login');
    });


    Route::post('get-payment-amounts', 'ApiController@getPaymentAmounts');
    Route::post('representatives', 'ApiController@getRepresentatives');
    Route::post('biography/{id}', 'ApiController@getBiographyDetails');
    Route::post('achievements', 'ApiController@getAchievements');
    Route::post('laws', 'ApiController@getLaws');
    Route::post('law-files', 'ApiController@downloadLawFiles');
    Route::post('about-us', 'ApiController@aboutUs');
    Route::post('faq-categories', 'ApiController@faqCategories');
    Route::post('faqs', 'ApiController@faqsByCategory');
    Route::post('political-work', 'ApiController@getPoliticalWork');
    Route::post('legislative-docs', 'ApiController@getLegislativeDocs');
    Route::post('legislative-categories', 'ApiController@getLegislativeCategories');
    Route::post('legislative-subcategories', 'ApiController@getLegislativeSubcategories');
    Route::post('national-plans', 'ApiController@getNationalPlans');
    Route::post('electoral-districts', 'ApiController@getElectoralDistricts');
    Route::post('towns', 'ApiController@getTowns');
    Route::post('competency-static-page', 'ApiController@getCompetencyStaticPage');

});


Route::group(['namespace' => 'Api', 'prefix' => '', 'middleware' => ['lang']], function(){

    Route::post('send-verification', 'V2\RegistrationController@sendVerfication');
    Route::post('verify-pin', 'V2\RegistrationController@verify_pin');
    Route::post('country-codes', 'ApiController@getCountryCodes');


    Route::group(['middleware' => ['my-auth', 'fpm-auth']], function () {
        Route::post('my-profile', 'ApiController@myProfile');
        Route::post('memos', 'ApiController@getMemos');
        Route::post('members', 'ApiController@getMembers');
        Route::post('favorite-members', 'ApiController@getFavorites');
        Route::post('current-polls', 'ApiController@getPolls');
        Route::post('previous-polls', 'ApiController@getPreviousPolls');
        Route::post('answer-poll', 'V2\ApiController@answerPoll');
        Route::post('previous-events', 'ApiController@getPreviousEvents');
        Route::post('upcoming-events', 'ApiController@getUpcomingEvents');
        Route::post('become-a-volunteer', 'ApiController@becomeVolunteer');
        Route::post('volunteers', 'ApiController@volunteers');
        Route::post('news-like', 'ApiController@likeNews');
        Route::post('news-share', 'ApiController@shareNews');
        Route::post('make-favorite', 'ApiController@favoriteMember');
        Route::post('talk-to-us', 'ApiController@userTalk');
        Route::post('logout', 'ApiController@logout');
        Route::post('messages', 'ApiController@getMessages');
        Route::post('getGroups', 'ApiController@getGroups');
        Route::post('single-event', 'ApiController@getEventById');
        Route::post('single-news', 'ApiController@getNewsById');
        Route::post('live-stream', 'ApiController@getLiveStream');
});


    Route::group(['middleware' => ['not-required-token', 'fpm-auth']], function(){
        Route::post('news', 'ApiController@getNews');
        Route::post('paginated-news', 'ApiController@getNewsWithPagination')->name('api.news.paginated');
        Route::post('important-links', 'ApiController@getLinks');
        Route::post('webviews', 'ApiController@getWebviews');
        Route::post('i-pay', 'ApiController@pay');
        Route::post('e-bill', 'ApiController@bill');
    });



    Route::group(['middleware' => ['my-auth']], function(){
        Route::post('memo-files', 'ApiController@downloadMemoFiles');
        Route::post('login', 'RegistrationController@login');
    });


    Route::post('get-payment-amounts', 'ApiController@getPaymentAmounts');
    Route::post('representatives', 'ApiController@getRepresentatives');
    Route::post('about-us', 'ApiController@aboutUs');
    Route::post('faq-categories', 'ApiController@faqCategories');
    Route::post('faqs', 'ApiController@faqsByCategory');

});


Route::middleware('auth:api')->get('/user', function (Request $request) {

    return $request->user();
});
