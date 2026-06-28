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
    Route::post('seed',"ApiController@seed");
    Route::post('get-qr-code', 'ApiController@getQRCode');
    // Route::post('google-forms', 'ApiController@getInternalProcess');
    // Route::post('single-google-form', 'ApiController@getSingleInternalProcess');


    Route::post('getFpmUsers', 'ApiController@getFpmUsers');

    Route::post('send-verification', 'RegistrationController@sendVerfication');
    Route::post('verify-pin', 'RegistrationController@verify_pin');
    Route::post('country-codes', 'ApiController@getCountryCodes');
    Route::post('app-versions', 'ApiController@appVersions');
    // Dev: routes without auth — user looked up by token header in controller
    Route::post('get-wall-feed','ApiController@getWallFeed');
    Route::post('news-like','ApiController@likeNews');
    Route::post('news-share','ApiController@shareNews');
    Route::post('memos','ApiController@getMemos');
    Route::post('current-polls','ApiController@getPolls');
    Route::post('previous-polls','ApiController@getPreviousPolls');
    Route::post('answer-poll','ApiController@answerPoll');
    Route::post('upcoming-events-paginated','ApiController@getUpcomingEventsWithPagination');
    Route::post('previous-events-paginated','ApiController@getPreviousEventsWithPagination');
    Route::post('get-my-candidates-status','ApiController@getCandidates');
    Route::post('google-forms','ApiController@getInternalProcess');
    Route::post('important-links','ApiController@getLinks');
    Route::post('dev-sync-token', function(\Illuminate\Http\Request $request) {
        $token    = $request->input('token');
        $memberId = $request->input('member_id');
        if ($token && $memberId) {
            \App\V2\AppUser::where('member_id', $memberId)
                ->update(['token' => $token, 'verified' => 1]);
        }
        return response()->json(['synced' => true]);
    });

    Route::group(['middleware' => ['my-auth-v2', 'fpm-auth']], function () {
        Route::post('my-profile', 'ApiController@myProfile');
        // Route::post('get-qr-code', 'ApiController@getQRCode');
        Route::post('google-forms', 'ApiController@getInternalProcess');
        Route::post('single-google-form', 'ApiController@getSingleInternalProcess');
        Route::post('memos', 'ApiController@getMemos');
        Route::post('members', 'ApiController@getMembers');
        Route::post('favorite-members', 'ApiController@getFavorites');
        Route::post('get-poll','ApiController@getPollById');
        Route::post('current-polls', 'ApiController@getPolls');
        Route::post('previous-polls', 'ApiController@getPreviousPolls');
        Route::post('answer-poll', 'ApiController@answerPoll');
        Route::post('previous-events', 'ApiController@getPreviousEvents');
        Route::post('upcoming-events', 'ApiController@getUpcomingEvents');

        Route::post('national-council-poll-can-i-vote', 'ApiController@canIVoteToNationalCouncilPollMessage');
        Route::post('national-council-poll-get', 'ApiController@getLatestNationalCouncilPoll');
        Route::post('national-council-poll-vote', 'ApiController@voteToNationalPoll');


        /////////////////𝕀𝕟𝕥𝕖𝕣𝕟𝕒𝕝 𝔼𝕝𝕖𝕔𝕥𝕚𝕠𝕟/////////////////////
        Route::get('get-internal-election-candidates','ApiController@getInternalElectionCandidates');
        Route::post('internal-election-vote','ApiController@internalElectionVote');
        Route::get('can-i-vote','ApiController@canIVoteEndpoint');
        //////////////𝔼𝕟𝕕 𝕀𝕟𝕥𝕖𝕣𝕟𝕒𝕝 𝔼𝕝𝕖𝕔𝕥𝕚𝕠𝕟///////////////////


        //New Endpoints

        #Events
        Route::post('upcoming-events-paginated', 'ApiController@getUpcomingEventsWithPagination');
        Route::post('previous-events-paginated', 'ApiController@getPreviousEventsWithPagination');

        #tracker
        Route::post('get-my-candidates-status','ApiController@getCandidates');
        Route::post('get-my-candidacy-status','ApiController@getCandidacy');

        //Albums
        #Get All the albums
        Route::post('get-all-albums','ApiController@getAllAlbums');
        Route::post('get-album','ApiController@getAlbum');
        //Route::post('delete-album','ApiController@deleteAlbum');
        //Route::post('delete-media','ApiController@deleteMedia');
        //Route::post('create-album','ApiController@createAlbum');
        //Route::post('add-media','ApiController@addMedia');



        //End of New Endpoints

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


        /////// Delete Account /////////////////////
        Route::post('delete-account-verification', 'ApiController@deleteAccountVerification');
        Route::post('delete-account', 'ApiController@deleteAccount');
        ////////////////////////////////////////////
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
    Route::post('biography/{id}', 'ApiController@getBiographyDetails');
    Route::post('achievements', 'ApiController@getAchievements');
    Route::post('laws', 'ApiController@getLaws');
    Route::post('law-files', 'ApiController@downloadLawFiles');
    Route::post('about-us', 'ApiController@aboutUs');
    Route::post('faq-categories', 'ApiController@faqCategories');
    Route::post('faqs', 'ApiController@faqsByCategory');

});


Route::group(['namespace' => 'Api', 'prefix' => '', 'middleware' => ['lang']], function(){

    Route::post('send-verification', 'RegistrationController@sendVerfication');
    Route::post('verify-pin', 'RegistrationController@verify_pin');
    Route::post('country-codes', 'ApiController@getCountryCodes');


    Route::group(['middleware' => ['my-auth', 'fpm-auth']], function () {
        Route::post('my-profile', 'ApiController@myProfile');
        Route::post('memos', 'ApiController@getMemos');
        Route::post('members', 'ApiController@getMembers');
        Route::post('favorite-members', 'ApiController@getFavorites');
        Route::post('current-polls', 'ApiController@getPolls');
        Route::post('previous-polls', 'ApiController@getPreviousPolls');
        Route::post('answer-poll', 'ApiController@answerPoll');
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
