<?php


//use App\Http\Controllers\Admin\TransactionController;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get("/support", function () {
    return view('support');
});

Route::get('/privacy', function () {
    return view('privacy');
});

Route::get('/', function () {
    return view('welcome');
});

Route::group(['middleware' => [], 'prefix' => 'webview', 'as' => 'webview.'], function () {
    Route::get('faqs', 'HomeController@faqs');
});

Route::group(['middleware' => ['my-auth', 'fpm-auth']], function () {
    Route::get('tracker', 'TrackingModuleController@getCondidates');
});

Route::group(['middleware' => 'auth:admin', 'namespace' => 'Admin', 'prefix' => 'admin', 'as' => 'admin.'], function () {
    Route::get('/', function () {
        return view('cms.index');
    })->name('dashboard');

    //Route::get('/', 'ProfileController@editForm')->name('dashboard');
    Route::resource('profile', 'ProfileController', ['only' => ['edit', 'update']]);
    Route::get('change-password', 'ProfileController@changePasswordForm')->name('change_password_form');
    Route::post('change-password', 'ProfileController@changePassword')->name('change_password');
    Route::post('reset-password', 'ProfileController@resetPassword')->name('reset_password');
    Route::get('add-user', 'ProfileController@addUserForm')->name('add_user_form');
    Route::post('add-user', 'ProfileController@addUser')->name('add_user');

    //App users
    Route::get('users/import', 'UsersController@importCreate')->name('users.import.create');
    Route::get('users/qr-code', 'UsersController@qr_code')->name('users.qr-code.create');
    Route::post('users/qr-code/store', 'UsersController@qr_code_store')->name('users.qr-code.store');
    Route::post('users/import/store', 'UsersController@importStore')->name('users.import.store');
    Route::get('users/export', 'UsersController@export')->name('users.export');

    Route::resource('users', 'UsersController');
    //Administrators
    Route::resource('admins', 'AdminsController');

    //Representatives Positions
    Route::resource('representative-positions', 'RepresentativePositionController');

    //App Versions - Force Updates
    Route::get('app-versions', 'AppVersionsController@edit')->name('app-versions.edit');
    Route::put('app-versions/update', 'AppVersionsController@update')->name('app-versions.update');
    /*     Route::resource('app-versions', 'AppVersionsController')->only([
        'update', 'edit'
    ]); */

    //Faqs
    Route::resource('faqsCategories', 'FaqsCategoriesController');
    Route::resource('faqs', 'FaqsController');

    //Memos
    Route::resource('memos', 'MemosController');

    //Laws
    Route::resource('laws', 'LawsController');
    Route::resource('internal-processes','InternalProcessController');

    //Achievements
    Route::resource('achievements', 'AchievementsController');

    //Biographies
    Route::resource('biography', 'BiographiesController');

    //news
    Route::resource('news', 'NewsController');

    //polls
    Route::resource('polls', 'PollsController');

    //events
    Route::resource('events', 'EventsController');

    //Live Stream
    Route::resource('liveStream', 'LiveStreamController');

    //volunteers
    Route::resource('volunteers', 'VolunteersController');

    //EventFiles
    Route::resource('eventImages', 'EventImagesController');
    Route::post('eventFilesDestroy', 'EventImagesController@multipleDelete')->name('eventImages.multipleDelete');
    Route::post('eventUploadFile', 'EventImagesController@uploadFile')->name('event.upload_file');
    Route::post('eventRemoveFile', 'EventImagesController@remove_File')->name('event.remove_file');

    //Representatives
    Route::resource('representatives', 'RepresentativesController');
    Route::get('representatives-category', 'RepresentativeCategoryController@index')->name('representatives.category.index');
    Route::get('representatives-category-create', 'RepresentativeCategoryController@create')->name('representatives.category.create');
    Route::post('representatives-category-store', 'RepresentativeCategoryController@store')->name('representatives.category.store');
    Route::delete('representatives-category-destroy/{id}', 'RepresentativeCategoryController@destroy')->name('representatives.category.destroy');
    Route::get('representatives-category-edit/{id}', 'RepresentativeCategoryController@edit')->name('representatives.category.edit');
    Route::put('representatives-category-update/{id}', 'RepresentativeCategoryController@update')->name('representatives.category.update');

    //content
    Route::get('representatives-content', 'ContentController@representativesForm')->name('representatives.form');
    Route::post('representatives-content', 'ContentController@representativesUpdate')->name('representatives.update_form');

    //About us
    Route::get('about-us', 'ContentController@aboutUsForm')->name('aboutUs.form');
    Route::post('about-us', 'ContentController@aboutUsUpdate')->name('aboutUs.update');


    //links
    Route::resource('links', 'LinksController');

    //webviews
    Route::resource('webviews', 'WebviewsController');

    //placeholder
    Route::resource('placeholders', 'PlaceholdersController');

    //Talk to Us
    Route::resource('talkToUs', 'TalkToUsController');

    //groups
    Route::resource('groups', 'GroupsController');

    //UserPolls
    Route::resource('userPolls', 'UserPollsController');

    //userVolunteer
    Route::resource('volunteerUsers', 'VolunteerUsersController');


    //Transactions
    Route::resource('transactions', 'TransactionController');

    //Bills Transaction
    Route::resource('bills', 'BillsTransactionsController');

    /////Jihad Noureddine////////

    //Push Notification
    // Route::resource('pushNotification', 'NotificationController');

    Route::get("get-push-notification-page", "NotificationController@index")->name('bulkpushnotification.index');
    Route::get("get-push-notification-page-form", "NotificationController@create")->name('bulkpushnotification.create');
    Route::post("get-push-notification-page-publish", "NotificationController@store")->name('bulkpushnotification.store');
    Route::post("upload-notification-image", "NotificationController@upload")->name('admin.notificationimage.upload');
    Route::post("delete-notification-image", "NotificationController@deleteImage")->name('admin.notificationimage.delete');


    #Upload image
    Route::post('upload-news-image', 'NewsController@imageUpload')->name('newsimage.upload');

    #delete image
    Route::post('delete-news-image', 'NewsController@deleteUpload')->name('newsimage.delete');

    //Delte image used by the image-deleter.js
    Route::post('delete-news-image/{id}', 'NewsController@deleteNewsImage');

    //Attachements
    #attach file
    Route::post('upload-news-attachement', 'NewsController@uploadAttachement')->name('newsattachement.upload');
    #delete attachment
    Route::post('delete-news-attachement', 'NewsController@deleteAttachement')->name('newsattachement.delete');
    #delete News pdf used by the pdf_ajax_deleter
    Route::post("delete-news-pdf/{id}", 'NewsController@deletePdf');

    //Album
    Route::get('albums', 'AlbumController@index')->name('albums.index');

    Route::get('albums?type=videos', 'AlbumController@index')->name('albums.videos');
    Route::get('albums?type=pdfs', 'AlbumController@index')->name('albums.pdfs');
    Route::get('albums?type=images', 'AlbumController@index')->name('albums.images');

    Route::get('albums-create', 'AlbumController@create')->name('albums.create');
    Route::post('albums-store', 'AlbumController@store')->name('albums.store');
    Route::get('albums-edit/{id}', 'AlbumController@edit')->name('albums.edit');
    Route::post('albums-update/{id}', 'AlbumController@update')->name('albums.update');
    Route::post('albums-store', 'AlbumController@store')->name('albums.store');
    Route::delete('albums-destroy/{id}', 'AlbumController@destroy')->name('albums.destroy');

    //Media
    //Route::post('media-update','MediaController@update');
    Route::post('delete-image', 'MediaController@deleteImage')->name('image.delete');
    Route::post('upload-image', 'MediaController@uploadImage')->name('image.upload');

    Route::post('media-upload', 'MediaController@upload')->name('media.upload');

    Route::get('media-create/{type}/{id}', 'MediaController@create')->name('media.create');
    Route::delete('media-delete/{id}', 'MediaController@destroy')->name('media.destroy');
    Route::get('media-get/{album}', 'MediaController@index')->name('media.index');
    Route::get('media-edit/{type}/{album}/{media}', 'MediaController@edit')->name('media.edit');
    Route::post('media-update/{type}/{album}/{media}', 'MediaController@update')->name('media.update');
    Route::post('media-store/{id}', 'MediaController@store')->name('media.store');

    //Traking Module
    Route::get('district-coordinator', 'DistrictCoordinatorController@index')->name('district-coordinator.index');
    Route::get('district-coordinator-create', 'DistrictCoordinatorController@create')->name('district-coordinator.create');
    Route::post('district-coordinator-store', 'DistrictCoordinatorController@store')->name('district-coordinator.store');
    Route::get('district-coordinator-edit', 'DistrictCoordinatorController@edit')->name('district-coordinator.edit');
    Route::delete('district-coordinator-destroy/{id}', 'DistrictCoordinatorController@destroy')->name('district-coordinator.destroy');
    Route::delete('district-coordinator-update/{id}', 'DistrictCoordinatorController@destroy')->name('district-coordinator.destroy');
    Route::post('district-coordinator-edit-date/{id}/{phaseNumber}', 'DistrictCoordinatorController@editDate');
    Route::delete('district-coordinator-delete-date/{id}/{phaseNumber}', 'DistrictCoordinatorController@deleteDate');


    //Candidate updateName CandidateController
    Route::post("candidate-update-name/{id}", "CandidateController@updateName");
    Route::get("get-candidate/{candidate}", "CandidateController@getCandidate")->name('candidate-get');

    //Update candidate popularization number
    Route::post("district-coordinator-update-popularization-number/{id}", "DistrictCoordinatorController@updatePopularizationNumber")->name('district-coordinator-update-popularization-number');
    Route::post("local-body-update-popularization-number/{id}", "LocalBodyController@updatePopularizationNumber")->name('local-body-coordinator-update-popularization-number');
    Route::post("district-body-update-popularization-number/{id}", "DistrictBodyController@updatePopularizationNumber")->name('distric-body-update-popularization-number');
    Route::post("central-committee-coordinator-update-popularization-number/{id}", "CentralCommitteeCoordinatorController@updatePopularizationNumber")->name('central-committee-coordinator-update-popularization-number');

    Route::post("central-committee-update-popularization1-number/{id}", "CentralCommitteeController@updatePopularizationNumber1")->name('central-committee-update-popularization1-number');
    Route::post("central-committee-update-popularization2-number/{id}", "CentralCommitteeController@updatePopularizationNumber2")->name('central-committee-update-popularization2-number');
    Route::post("consulting-committee-update-popularization-number/{id}", "ConsultingCommitteeController@updatePopularizationNumber")->name('consulting-committee-update-popularization-number');


    //Update Applications States
    Route::post("district-coordinator-update-application-state/{id}", "DistrictCoordinatorController@updateState")->name("district-coordinator-update-application-state");
    Route::post("district-body-update-application-state/{id}", "DistrictBodyController@updateState")->name("district-body-update-application-state");
    Route::post("local-body-update-application-state/{id}", "LocalBodyController@updateState")->name("local-body-update-application-state");
    Route::post("central-committee-coordinator-update-application-state/{id}", "CentralCommitteeCoordinatorController@updateState")->name("central-committee-coordinator-update-application-state");
    Route::post("central-committee-update-application-state/{id}", "CentralCommitteeController@updateState")->name("central-committee-update-application-state");
    Route::post("consulting-committee-update-application-state/{id}", "ConsultingCommitteeController@updateState")->name("consulting-committee-update-application-state");

    //District Body هيئة قضاء
    Route::resource('district-body', 'DistrictBodyController');
    Route::delete('district-body-delete-date/{id}/{phaseNumber}', 'DistrictBodyController@deleteDate');
    Route::post('district-body-edit-date/{id}/{phaseNumber}', 'DistrictBodyController@editDate');

    //Local Body Controller هيئة محلية
    Route::resource('local-body', 'LocalBodyController');
    Route::delete('local-body-delete-date/{id}/{phaseNumber}', 'LocalBodyController@deleteDate');
    Route::post('local-body-edit-date/{id}/{phaseNumber}', 'LocalBodyController@editDate');

    //District Controller
    Route::post('get-regions-by-district-id/{id}', 'DistrictController@getRegionsByDistrictId');


    //Central Committee Coordinator
    Route::resource('central-committee-coordinator', 'CentralCommitteeCoordinatorController');
    Route::delete('central-committee-coordinator-delete-date/{id}/{phaseNumber}', 'CentralCommitteeCoordinatorController@deleteDate');
    Route::post('central-committee-coordinator-edit-date/{id}/{phaseNumber}', 'CentralCommitteeCoordinatorController@editDate');


    //Central Committee Memeber
    Route::resource('central-committee', 'CentralCommitteeController');
    Route::delete('central-committee-delete-date/{id}/{phaseNumber}', 'CentralCommitteeController@deleteDate');
    Route::post('central-committee-edit-date/{id}/{phaseNumber}', 'CentralCommitteeController@editDate');


    //Consulting Committee لجنة إستشارية
    Route::resource('consulting-committee', 'ConsultingCommitteeController');
    Route::delete('consulting-committee-delete-date/{id}/{phaseNumber}', 'ConsultingCommitteeController@deleteDate');
    Route::post('consulting-committee-edit-date/{id}/{phaseNumber}', 'ConsultingCommitteeController@editDate');


    //Donations
    Route::get('donation-update-form', 'DonationBannerController@changeImageForm')->name('donation.update.form');
    Route::post('donation-update-image', 'DonationBannerController@changeImage')->name('donation.image.update');


    //Internal Election
    Route::resource('internal-election', 'InternalElectionController');
    Route::post('internal-election/publish/{id}', 'InternalElectionController@publish');
    Route::get('internal-election/export/{id}', 'InternalElectionController@export')->name('internal-election.export');
    Route::get('internal-election-vote/reset', 'InternalElectionController@reset')->name('internal-election-votes.reset');


    //Internal Election Candidates
    Route::get('internal-election-cadidates/{id}', 'InternalElectionCandidatesController@index')->name('internal-election-candidates.index');
    Route::post('internal-election-cadidates-store', 'InternalElectionCandidatesController@store')->name('internal-election-candidates.store');
    Route::get('internal-election-cadidates-create', 'InternalElectionCandidatesController@create')->name('internal-election-candidates.create');
    Route::delete('internal-election-cadidates-delete/{id}', 'InternalElectionCandidatesController@destroy')->name('internal-election-candidates.destroy');

    //National Council Poll Questions Answer
    /*Route::resource('national-council-poll.questions.answers','NationalCouncilPollQuestionAnswersController');*/

    //National Council Poll
    Route::get('national-council-poll/{id}/clear', 'NationalCouncilPollController@clear')->name('national-council-poll.clear');
    Route::get('national-council-poll/{id}/results', 'NationalCouncilPollController@results')->name('national-council-poll.results');
    Route::resource('national-council-poll', 'NationalCouncilPollController');

    //National Council Poll Questions
    Route::resource('national-council-poll.questions', 'NationalCouncilPollQuestionsController');
});


//Route::get('/login/owner', 'Auth\OwnerLoginController@showLoginForm')->name('owner.login');
//Route::post('/login/owner', 'Auth\OwnerLoginController@login');
//Route::get('/owner/logout', 'Auth\OwnerLoginController@logout')->name('owner.logout');
Route::get('/admin/logout', 'Auth\LoginController@logout')->name('admin.logout');

Route::get('donations', 'TransactionController@create')->name('transactions.create');
Route::post('donations/store', 'TransactionController@store')->name('transactions.store');
Route::get('payment-mobile-response/{success}/{message}', 'TransactionController@responseUpdateTransaction')->name('payment-mobile-response');

Auth::routes();
