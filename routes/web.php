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
    // page 8 (APP USERS). Pilot for the new Profile permission system.
    Route::get('users/import', 'UsersController@importCreate')->name('users.import.create')->middleware('page-perm:8,full');
    Route::get('users/qr-code', 'UsersController@qr_code')->name('users.qr-code.create')->middleware('page-perm:8,full');
    Route::post('users/qr-code/store', 'UsersController@qr_code_store')->name('users.qr-code.store')->middleware('page-perm:8,full');
    Route::post('users/import/store', 'UsersController@importStore')->name('users.import.store')->middleware('page-perm:8,full');
    Route::get('users/export', 'UsersController@export')->name('users.export')->middleware('page-perm:8,view');
    Route::get('users/installation-report', 'UsersController@installationReport')->name('users.installation-report')->middleware('page-perm:8,view');
    Route::get('users/{id}/toggle-scan-checkin', 'UsersController@toggleScanCheckin')->name('users.toggle-scan-checkin')->middleware('page-perm:8,full');

    Route::resource('users', 'UsersController')->only(['index'])->middleware('page-perm:8,view');
    Route::resource('users', 'UsersController')->except(['index'])->middleware('page-perm:8,full');
    //Administrators — page 4
    Route::resource('admins', 'AdminsController')->middleware('page-perm:4,full');
    Route::resource('profiles', 'ProfilesController')->middleware('page-perm:4,full');

    //Representatives Positions — child of Representatives, page 31
    Route::resource('representative-positions', 'RepresentativePositionController')->middleware('page-perm:31,full');

    //App Versions - Force Updates — page 64
    Route::get('app-versions', 'AppVersionsController@edit')->name('app-versions.edit')->middleware('page-perm:64,full');
    Route::put('app-versions/update', 'AppVersionsController@update')->name('app-versions.update')->middleware('page-perm:64,full');
    /*     Route::resource('app-versions', 'AppVersionsController')->only([
        'update', 'edit'
    ]); */

    //Faqs — pages 10 (FAQ Categories), 13 (FAQ)
    Route::resource('faqsCategories', 'FaqsCategoriesController')->middleware('page-perm:10,full');
    Route::resource('faqs', 'FaqsController')->middleware('page-perm:13,full');

    //Legislative Docs (العمل التشريعي) — page 69
    Route::get('legislative-docs', 'LegislativeDocsController@index')->name('legislative-docs.index')->middleware('page-perm:69,view');
    Route::get('legislative-docs/create', 'LegislativeDocsController@create')->name('legislative-docs.create')->middleware('page-perm:69,full');
    Route::post('legislative-docs', 'LegislativeDocsController@store')->name('legislative-docs.store')->middleware('page-perm:69,full');
    Route::get('legislative-docs/{id}/edit', 'LegislativeDocsController@edit')->name('legislative-docs.edit')->middleware('page-perm:69,full');
    Route::put('legislative-docs/{id}', 'LegislativeDocsController@update')->name('legislative-docs.update')->middleware('page-perm:69,full');
    Route::delete('legislative-docs/{id}', 'LegislativeDocsController@destroy')->name('legislative-docs.destroy')->middleware('page-perm:69,full');

    //National Plans (الخطط الوطنية المقدمة) — page 70
    Route::get('national-plans', 'NationalPlansController@index')->name('national-plans.index')->middleware('page-perm:70,view');
    Route::get('national-plans/create', 'NationalPlansController@create')->name('national-plans.create')->middleware('page-perm:70,full');
    Route::post('national-plans', 'NationalPlansController@store')->name('national-plans.store')->middleware('page-perm:70,full');
    Route::get('national-plans/{id}/edit', 'NationalPlansController@edit')->name('national-plans.edit')->middleware('page-perm:70,full');
    Route::put('national-plans/{id}', 'NationalPlansController@update')->name('national-plans.update')->middleware('page-perm:70,full');
    Route::delete('national-plans/{id}', 'NationalPlansController@destroy')->name('national-plans.destroy')->middleware('page-perm:70,full');

    //Internal Organization (التنظيم الداخلي) — page 68
    Route::get('internal-org', 'InternalOrgController@index')->name('internal-org.index')->middleware('page-perm:68,view');
    Route::post('internal-org', 'InternalOrgController@update')->name('internal-org.update')->middleware('page-perm:68,full');

    //Political Work — page 65
    Route::get('political-work', 'PoliticalWorkController@index')->name('political-work.index')->middleware('page-perm:65,view');
    Route::get('political-work-create', 'PoliticalWorkController@create')->name('political-work.create')->middleware('page-perm:65,full');
    Route::post('political-work-store', 'PoliticalWorkController@store')->name('political-work.store')->middleware('page-perm:65,full');
    Route::get('political-work-edit/{id}', 'PoliticalWorkController@edit')->name('political-work.edit')->middleware('page-perm:65,full');
    Route::put('political-work-update/{id}', 'PoliticalWorkController@update')->name('political-work.update')->middleware('page-perm:65,full');
    Route::delete('political-work-destroy/{id}', 'PoliticalWorkController@destroy')->name('political-work.destroy')->middleware('page-perm:65,full');

    //Mukhtars (مخاتير) — page 67
    Route::get('mukhtars', 'MukhtarsController@index')->name('mukhtars.index')->middleware('page-perm:67,view');
    Route::get('mukhtars/create', 'MukhtarsController@create')->name('mukhtars.create')->middleware('page-perm:67,full');
    Route::post('mukhtars', 'MukhtarsController@store')->name('mukhtars.store')->middleware('page-perm:67,full');
    Route::get('mukhtars/{id}/edit', 'MukhtarsController@edit')->name('mukhtars.edit')->middleware('page-perm:67,full');
    Route::put('mukhtars/{id}', 'MukhtarsController@update')->name('mukhtars.update')->middleware('page-perm:67,full');
    Route::delete('mukhtars/{id}', 'MukhtarsController@destroy')->name('mukhtars.destroy')->middleware('page-perm:67,full');
    Route::get('mukhtars-import', 'MukhtarsController@importForm')->name('mukhtars.import-form')->middleware('page-perm:67,full');
    Route::post('mukhtars-import', 'MukhtarsController@importStore')->name('mukhtars.import-store')->middleware('page-perm:67,full');
    Route::get('mukhtars-template', 'MukhtarsController@downloadTemplate')->name('mukhtars.template')->middleware('page-perm:67,view');

    //Municipality Members (بلديات / مخاتير) — page 66
    Route::get('municipality-members', 'MunicipalityMembersController@index')->name('municipality-members.index')->middleware('page-perm:66,view');
    Route::get('municipality-members/create', 'MunicipalityMembersController@create')->name('municipality-members.create')->middleware('page-perm:66,full');
    Route::post('municipality-members', 'MunicipalityMembersController@store')->name('municipality-members.store')->middleware('page-perm:66,full');
    Route::get('municipality-members/{id}/edit', 'MunicipalityMembersController@edit')->name('municipality-members.edit')->middleware('page-perm:66,full');
    Route::put('municipality-members/{id}', 'MunicipalityMembersController@update')->name('municipality-members.update')->middleware('page-perm:66,full');
    Route::delete('municipality-members/{id}', 'MunicipalityMembersController@destroy')->name('municipality-members.destroy')->middleware('page-perm:66,full');
    Route::get('municipality-members-import', 'MunicipalityMembersController@importForm')->name('municipality-members.import-form')->middleware('page-perm:66,full');
    Route::post('municipality-members-import', 'MunicipalityMembersController@importStore')->name('municipality-members.import-store')->middleware('page-perm:66,full');
    Route::get('municipality-members-template', 'MunicipalityMembersController@downloadTemplate')->name('municipality-members.template')->middleware('page-perm:66,view');

    //Competency Platform (منصة الكفاءات) — page 71
    Route::get('competency-vacancies', 'CompetencyVacanciesController@index')->name('competency-vacancies.index')->middleware('page-perm:71,view');
    Route::get('competency-vacancies/create', 'CompetencyVacanciesController@create')->name('competency-vacancies.create')->middleware('page-perm:71,full');
    Route::post('competency-vacancies', 'CompetencyVacanciesController@store')->name('competency-vacancies.store')->middleware('page-perm:71,full');
    Route::get('competency-vacancies/{id}/edit', 'CompetencyVacanciesController@edit')->name('competency-vacancies.edit')->middleware('page-perm:71,full');
    Route::put('competency-vacancies/{id}', 'CompetencyVacanciesController@update')->name('competency-vacancies.update')->middleware('page-perm:71,full');
    Route::delete('competency-vacancies/{id}', 'CompetencyVacanciesController@destroy')->name('competency-vacancies.destroy')->middleware('page-perm:71,full');
    Route::get('competency-vacancies/{id}/toggle-active', 'CompetencyVacanciesController@toggleActive')->name('competency-vacancies.toggle-active')->middleware('page-perm:71,full');
    Route::get('competency-vacancies/{id}/nominations', 'CompetencyVacanciesController@nominations')->name('competency-vacancies.nominations')->middleware('page-perm:71,view');
    Route::delete('competency-vacancies/{vacancyId}/nominations/{nominationId}', 'CompetencyVacanciesController@destroyNomination')->name('competency-vacancies.nominations.destroy')->middleware('page-perm:71,full');

    // page 77 (Check-In Events). Pilot for the new Profile permission system.
    Route::get('checkin-events', 'CheckinEventsController@index')->name('checkin-events.index')->middleware('page-perm:77,view');
    Route::get('checkin-events/create', 'CheckinEventsController@create')->name('checkin-events.create')->middleware('page-perm:77,full');
    Route::post('checkin-events', 'CheckinEventsController@store')->name('checkin-events.store')->middleware('page-perm:77,full');
    Route::get('checkin-events/{id}/edit', 'CheckinEventsController@edit')->name('checkin-events.edit')->middleware('page-perm:77,full');
    Route::put('checkin-events/{id}', 'CheckinEventsController@update')->name('checkin-events.update')->middleware('page-perm:77,full');
    Route::delete('checkin-events/{id}', 'CheckinEventsController@destroy')->name('checkin-events.destroy')->middleware('page-perm:77,full');
    Route::get('checkin-events/{id}/toggle-active', 'CheckinEventsController@toggleActive')->name('checkin-events.toggle-active')->middleware('page-perm:77,full');
    Route::get('checkin-events/{id}/attendance', 'CheckinEventsController@attendance')->name('checkin-events.attendance')->middleware('page-perm:77,view');
    Route::get('checkin-events/{id}/attendance/search', 'CheckinEventsController@searchMembers')->name('checkin-events.attendance.search')->middleware('page-perm:77,view');
    // These 2 are independently grantable on top of View (see Profile edit form) —
    // a View-only admin doesn't get them by default, but doesn't need Full either.
    Route::get('checkin-events/{id}/attendance/export', 'CheckinEventsController@exportAttendance')->name('checkin-events.attendance.export')->middleware('page-perm:77,action:attendance.export');
    Route::post('checkin-events/{id}/attendance', 'CheckinEventsController@storeAttendance')->name('checkin-events.attendance.store')->middleware('page-perm:77,action:attendance.store');
    // page 72
    Route::get('competency-static', 'CompetencyStaticController@index')->name('competency-static.index')->middleware('page-perm:72,view');
    Route::post('competency-static', 'CompetencyStaticController@update')->name('competency-static.update')->middleware('page-perm:72,full');

    //Community — page 76 for all 3 sub-features (Business Types/Directory Members/Community Posts are its children)
    Route::get('business-types', 'BusinessTypesController@index')->name('business-types.index')->middleware('page-perm:76,view');
    Route::get('business-types/create', 'BusinessTypesController@create')->name('business-types.create')->middleware('page-perm:76,full');
    Route::post('business-types', 'BusinessTypesController@store')->name('business-types.store')->middleware('page-perm:76,full');
    Route::get('business-types/{id}/edit', 'BusinessTypesController@edit')->name('business-types.edit')->middleware('page-perm:76,full');
    Route::put('business-types/{id}', 'BusinessTypesController@update')->name('business-types.update')->middleware('page-perm:76,full');
    Route::delete('business-types/{id}', 'BusinessTypesController@destroy')->name('business-types.destroy')->middleware('page-perm:76,full');

    Route::get('directory-members', 'DirectoryMembersController@index')->name('directory-members.index')->middleware('page-perm:76,view');
    Route::get('directory-members/create', 'DirectoryMembersController@create')->name('directory-members.create')->middleware('page-perm:76,full');
    Route::post('directory-members', 'DirectoryMembersController@store')->name('directory-members.store')->middleware('page-perm:76,full');
    Route::get('directory-members/{id}/edit', 'DirectoryMembersController@edit')->name('directory-members.edit')->middleware('page-perm:76,full');
    Route::put('directory-members/{id}', 'DirectoryMembersController@update')->name('directory-members.update')->middleware('page-perm:76,full');
    Route::delete('directory-members/{id}', 'DirectoryMembersController@destroy')->name('directory-members.destroy')->middleware('page-perm:76,full');
    Route::get('directory-members-import', 'DirectoryMembersController@importForm')->name('directory-members.import-form')->middleware('page-perm:76,full');
    Route::post('directory-members-import', 'DirectoryMembersController@importStore')->name('directory-members.import-store')->middleware('page-perm:76,full');
    Route::get('directory-members-template', 'DirectoryMembersController@downloadTemplate')->name('directory-members.template')->middleware('page-perm:76,view');

    Route::get('community-posts', 'CommunityPostsController@index')->name('community-posts.index')->middleware('page-perm:76,view');
    Route::get('community-posts/{id}/edit', 'CommunityPostsController@edit')->name('community-posts.edit')->middleware('page-perm:76,full');
    Route::put('community-posts/{id}', 'CommunityPostsController@update')->name('community-posts.update')->middleware('page-perm:76,full');
    Route::get('community-posts/{id}/approve', 'CommunityPostsController@approve')->name('community-posts.approve')->middleware('page-perm:76,full');
    Route::get('community-posts/{id}/reject', 'CommunityPostsController@reject')->name('community-posts.reject')->middleware('page-perm:76,full');
    Route::delete('community-posts/{id}', 'CommunityPostsController@destroy')->name('community-posts.destroy')->middleware('page-perm:76,full');

    //Memos — page 19
    Route::resource('memos', 'MemosController')->only(['index', 'show'])->middleware('page-perm:19,view');
    Route::resource('memos', 'MemosController')->except(['index', 'show'])->middleware('page-perm:19,full');

    //Laws — page 60
    Route::resource('laws', 'LawsController')->only(['index', 'show'])->middleware('page-perm:60,view');
    Route::resource('laws', 'LawsController')->except(['index', 'show'])->middleware('page-perm:60,full');

    //Internal Process — page 63
    Route::resource('internal-processes','InternalProcessController')->only(['index', 'show'])->middleware('page-perm:63,view');
    Route::resource('internal-processes','InternalProcessController')->except(['index', 'show'])->middleware('page-perm:63,full');

    //Achievements — page 61
    Route::resource('achievements', 'AchievementsController')->only(['index', 'show'])->middleware('page-perm:61,view');
    Route::resource('achievements', 'AchievementsController')->except(['index', 'show'])->middleware('page-perm:61,full');

    //Biographies — page 62
    Route::resource('biography', 'BiographiesController')->only(['index', 'show'])->middleware('page-perm:62,view');
    Route::resource('biography', 'BiographiesController')->except(['index', 'show'])->middleware('page-perm:62,full');

    //news — page 28
    Route::resource('news', 'NewsController')->only(['index', 'show'])->middleware('page-perm:28,view');
    Route::resource('news', 'NewsController')->except(['index', 'show'])->middleware('page-perm:28,full');

    //polls — page 16
    Route::resource('polls', 'PollsController')->only(['index', 'show'])->middleware('page-perm:16,view');
    Route::resource('polls', 'PollsController')->except(['index', 'show'])->middleware('page-perm:16,full');

    //events — page 22
    Route::resource('events', 'EventsController')->only(['index', 'show'])->middleware('page-perm:22,view');
    Route::resource('events', 'EventsController')->except(['index', 'show'])->middleware('page-perm:22,full');

    //Live Stream — page 26
    Route::resource('liveStream', 'LiveStreamController')->only(['index', 'show'])->middleware('page-perm:26,view');
    Route::resource('liveStream', 'LiveStreamController')->except(['index', 'show'])->middleware('page-perm:26,full');

    //volunteers — page 27
    Route::resource('volunteers', 'VolunteersController')->only(['index', 'show'])->middleware('page-perm:27,view');
    Route::resource('volunteers', 'VolunteersController')->except(['index', 'show'])->middleware('page-perm:27,full');

    //EventFiles — child of Events, page 22
    Route::resource('eventImages', 'EventImagesController')->only(['index', 'show'])->middleware('page-perm:22,view');
    Route::resource('eventImages', 'EventImagesController')->except(['index', 'show'])->middleware('page-perm:22,full');
    Route::post('eventFilesDestroy', 'EventImagesController@multipleDelete')->name('eventImages.multipleDelete')->middleware('page-perm:22,full');
    Route::post('eventUploadFile', 'EventImagesController@uploadFile')->name('event.upload_file')->middleware('page-perm:22,full');
    Route::post('eventRemoveFile', 'EventImagesController@remove_File')->name('event.remove_file')->middleware('page-perm:22,full');

    //Representatives — page 31, covers all its children (category/positions/text) too
    Route::get('representatives-normalize-order', 'RepresentativesController@normalizeOrder')->name('representatives.normalize-order')->middleware('page-perm:31,full');
    Route::resource('representatives', 'RepresentativesController')->only(['index', 'show'])->middleware('page-perm:31,view');
    Route::resource('representatives', 'RepresentativesController')->except(['index', 'show'])->middleware('page-perm:31,full');
    Route::get('representatives-category', 'RepresentativeCategoryController@index')->name('representatives.category.index')->middleware('page-perm:31,view');
    Route::get('representatives-category-create', 'RepresentativeCategoryController@create')->name('representatives.category.create')->middleware('page-perm:31,full');
    Route::post('representatives-category-store', 'RepresentativeCategoryController@store')->name('representatives.category.store')->middleware('page-perm:31,full');
    Route::delete('representatives-category-destroy/{id}', 'RepresentativeCategoryController@destroy')->name('representatives.category.destroy')->middleware('page-perm:31,full');
    Route::get('representatives-category-edit/{id}', 'RepresentativeCategoryController@edit')->name('representatives.category.edit')->middleware('page-perm:31,full');
    Route::put('representatives-category-update/{id}', 'RepresentativeCategoryController@update')->name('representatives.category.update')->middleware('page-perm:31,full');

    //content — Representative Text, child of Representatives, page 31
    Route::get('representatives-content', 'ContentController@representativesForm')->name('representatives.form')->middleware('page-perm:31,full');
    Route::post('representatives-content', 'ContentController@representativesUpdate')->name('representatives.update_form')->middleware('page-perm:31,full');

    //About us — child of Page Contents, page 39
    Route::get('about-us', 'ContentController@aboutUsForm')->name('aboutUs.form')->middleware('page-perm:39,full');
    Route::post('about-us', 'ContentController@aboutUsUpdate')->name('aboutUs.update')->middleware('page-perm:39,full');


    //links — page 36
    Route::resource('links', 'LinksController')->only(['index', 'show'])->middleware('page-perm:36,view');
    Route::resource('links', 'LinksController')->except(['index', 'show'])->middleware('page-perm:36,full');

    //webviews — page 40
    Route::resource('webviews', 'WebviewsController')->only(['index', 'show'])->middleware('page-perm:40,view');
    Route::resource('webviews', 'WebviewsController')->except(['index', 'show'])->middleware('page-perm:40,full');

    //placeholder — page 42
    Route::resource('placeholders', 'PlaceholdersController')->only(['index', 'show'])->middleware('page-perm:42,view');
    Route::resource('placeholders', 'PlaceholdersController')->except(['index', 'show'])->middleware('page-perm:42,full');

    //Talk to Us — page 9
    Route::resource('talkToUs', 'TalkToUsController')->only(['index', 'show'])->middleware('page-perm:9,view');
    Route::resource('talkToUs', 'TalkToUsController')->except(['index', 'show'])->middleware('page-perm:9,full');

    //groups — page 7
    Route::resource('groups', 'GroupsController')->only(['index', 'show'])->middleware('page-perm:7,view');
    Route::resource('groups', 'GroupsController')->except(['index', 'show'])->middleware('page-perm:7,full');

    //UserPolls — no corresponding sidebar page exists; not tagged
    Route::resource('userPolls', 'UserPollsController');

    //userVolunteer — no corresponding sidebar page exists; not tagged
    Route::resource('volunteerUsers', 'VolunteerUsersController');


    //Transactions — page 1
    Route::resource('transactions', 'TransactionController')->only(['index', 'show'])->middleware('page-perm:1,view');
    Route::resource('transactions', 'TransactionController')->except(['index', 'show'])->middleware('page-perm:1,full');

    //Bills Transaction — page 1 (child "Monthly Bills")
    Route::resource('bills', 'BillsTransactionsController')->only(['index', 'show'])->middleware('page-perm:1,view');
    Route::resource('bills', 'BillsTransactionsController')->except(['index', 'show'])->middleware('page-perm:1,full');

    /////Jihad Noureddine////////

    //Push Notification
    // Route::resource('pushNotification', 'NotificationController');

    // page 48 (Bulk Push Notification)
    Route::get("get-push-notification-page", "NotificationController@index")->name('bulkpushnotification.index')->middleware('page-perm:48,view');
    Route::get("get-push-notification-page-form", "NotificationController@create")->name('bulkpushnotification.create')->middleware('page-perm:48,full');
    Route::post("get-push-notification-page-publish", "NotificationController@store")->name('bulkpushnotification.store')->middleware('page-perm:48,full');
    Route::post("upload-notification-image", "NotificationController@upload")->name('admin.notificationimage.upload')->middleware('page-perm:48,full');
    Route::post("delete-notification-image", "NotificationController@deleteImage")->name('admin.notificationimage.delete')->middleware('page-perm:48,full');
    Route::get("send-birthday-wishes", "NotificationController@sendBirthdayWishes")->name('birthdaywishes.send')->middleware('page-perm:48,full');


    // News image/attachment helpers — child of News, page 28
    #Upload image
    Route::post('upload-news-image', 'NewsController@imageUpload')->name('newsimage.upload')->middleware('page-perm:28,full');

    #delete image
    Route::post('delete-news-image', 'NewsController@deleteUpload')->name('newsimage.delete')->middleware('page-perm:28,full');

    //Delte image used by the image-deleter.js
    Route::post('delete-news-image/{id}', 'NewsController@deleteNewsImage')->middleware('page-perm:28,full');

    //Attachements
    #attach file
    Route::post('upload-news-attachement', 'NewsController@uploadAttachement')->name('newsattachement.upload')->middleware('page-perm:28,full');
    #delete attachment
    Route::post('delete-news-attachement', 'NewsController@deleteAttachement')->name('newsattachement.delete')->middleware('page-perm:28,full');
    #delete News pdf used by the pdf_ajax_deleter
    Route::post("delete-news-pdf/{id}", 'NewsController@deletePdf')->middleware('page-perm:28,full');

    //Album — page 43 (Archive). Pilot for the new Profile permission system.
    Route::get('albums', 'AlbumController@index')->name('albums.index')->middleware('page-perm:43,view');

    Route::get('albums?type=videos', 'AlbumController@index')->name('albums.videos')->middleware('page-perm:43,view');
    Route::get('albums?type=pdfs', 'AlbumController@index')->name('albums.pdfs')->middleware('page-perm:43,view');
    Route::get('albums?type=images', 'AlbumController@index')->name('albums.images')->middleware('page-perm:43,view');

    Route::get('albums-create', 'AlbumController@create')->name('albums.create')->middleware('page-perm:43,full');
    Route::post('albums-store', 'AlbumController@store')->name('albums.store')->middleware('page-perm:43,full');
    Route::get('albums-edit/{id}', 'AlbumController@edit')->name('albums.edit')->middleware('page-perm:43,full');
    Route::post('albums-update/{id}', 'AlbumController@update')->name('albums.update')->middleware('page-perm:43,full');
    Route::post('albums-store', 'AlbumController@store')->name('albums.store')->middleware('page-perm:43,full');
    Route::delete('albums-destroy/{id}', 'AlbumController@destroy')->name('albums.destroy')->middleware('page-perm:43,full');

    //Media
    //Route::post('media-update','MediaController@update');
    // NOT tagged to page 43 — these 2 are a generic rich-text-editor image
    // upload/delete helper used across many admin forms, not Archive-specific.
    Route::post('delete-image', 'MediaController@deleteImage')->name('image.delete');
    Route::post('upload-image', 'MediaController@uploadImage')->name('image.upload');

    // NOT tagged — MediaController has no upload() method, this route 404s
    // regardless (pre-existing dead route, left as-is).
    Route::post('media-upload', 'MediaController@upload')->name('media.upload');

    Route::get('media-create/{type}/{id}', 'MediaController@create')->name('media.create')->middleware('page-perm:43,full');
    Route::delete('media-delete/{id}', 'MediaController@destroy')->name('media.destroy')->middleware('page-perm:43,full');
    Route::get('media-get/{album}', 'MediaController@index')->name('media.index')->middleware('page-perm:43,view');
    Route::get('media-edit/{type}/{album}/{media}', 'MediaController@edit')->name('media.edit')->middleware('page-perm:43,full');
    Route::post('media-update/{type}/{album}/{media}', 'MediaController@update')->name('media.update')->middleware('page-perm:43,full');
    Route::post('media-store/{id}', 'MediaController@store')->name('media.store')->middleware('page-perm:43,full');

    //Traking Module — page 50 covers ALL 6 sub-controllers below (its
    // children 51-56 don't get their own Profile row, same as every other
    // parent/children page in this app — one grant covers the whole group).
    Route::get('district-coordinator', 'DistrictCoordinatorController@index')->name('district-coordinator.index')->middleware('page-perm:50,view');
    Route::get('district-coordinator-create', 'DistrictCoordinatorController@create')->name('district-coordinator.create')->middleware('page-perm:50,full');
    Route::post('district-coordinator-store', 'DistrictCoordinatorController@store')->name('district-coordinator.store')->middleware('page-perm:50,full');
    Route::get('district-coordinator-edit', 'DistrictCoordinatorController@edit')->name('district-coordinator.edit')->middleware('page-perm:50,full');
    Route::delete('district-coordinator-destroy/{id}', 'DistrictCoordinatorController@destroy')->name('district-coordinator.destroy')->middleware('page-perm:50,full');
    Route::delete('district-coordinator-update/{id}', 'DistrictCoordinatorController@destroy')->name('district-coordinator.destroy')->middleware('page-perm:50,full');
    Route::post('district-coordinator-edit-date/{id}/{phaseNumber}', 'DistrictCoordinatorController@editDate')->middleware('page-perm:50,full');
    Route::delete('district-coordinator-delete-date/{id}/{phaseNumber}', 'DistrictCoordinatorController@deleteDate')->middleware('page-perm:50,full');


    //Candidate updateName CandidateController
    Route::post("candidate-update-name/{id}", "CandidateController@updateName")->middleware('page-perm:50,full');
    Route::get("get-candidate/{candidate}", "CandidateController@getCandidate")->name('candidate-get')->middleware('page-perm:50,view');

    //Update candidate popularization number
    Route::post("district-coordinator-update-popularization-number/{id}", "DistrictCoordinatorController@updatePopularizationNumber")->name('district-coordinator-update-popularization-number')->middleware('page-perm:50,full');
    Route::post("local-body-update-popularization-number/{id}", "LocalBodyController@updatePopularizationNumber")->name('local-body-coordinator-update-popularization-number')->middleware('page-perm:50,full');
    Route::post("district-body-update-popularization-number/{id}", "DistrictBodyController@updatePopularizationNumber")->name('distric-body-update-popularization-number')->middleware('page-perm:50,full');
    Route::post("central-committee-coordinator-update-popularization-number/{id}", "CentralCommitteeCoordinatorController@updatePopularizationNumber")->name('central-committee-coordinator-update-popularization-number')->middleware('page-perm:50,full');

    Route::post("central-committee-update-popularization1-number/{id}", "CentralCommitteeController@updatePopularizationNumber1")->name('central-committee-update-popularization1-number')->middleware('page-perm:50,full');
    Route::post("central-committee-update-popularization2-number/{id}", "CentralCommitteeController@updatePopularizationNumber2")->name('central-committee-update-popularization2-number')->middleware('page-perm:50,full');
    Route::post("consulting-committee-update-popularization-number/{id}", "ConsultingCommitteeController@updatePopularizationNumber")->name('consulting-committee-update-popularization-number')->middleware('page-perm:50,full');


    //Update Applications States
    Route::post("district-coordinator-update-application-state/{id}", "DistrictCoordinatorController@updateState")->name("district-coordinator-update-application-state")->middleware('page-perm:50,full');
    Route::post("district-body-update-application-state/{id}", "DistrictBodyController@updateState")->name("district-body-update-application-state")->middleware('page-perm:50,full');
    Route::post("local-body-update-application-state/{id}", "LocalBodyController@updateState")->name("local-body-update-application-state")->middleware('page-perm:50,full');
    Route::post("central-committee-coordinator-update-application-state/{id}", "CentralCommitteeCoordinatorController@updateState")->name("central-committee-coordinator-update-application-state")->middleware('page-perm:50,full');
    Route::post("central-committee-update-application-state/{id}", "CentralCommitteeController@updateState")->name("central-committee-update-application-state")->middleware('page-perm:50,full');
    Route::post("consulting-committee-update-application-state/{id}", "ConsultingCommitteeController@updateState")->name("consulting-committee-update-application-state")->middleware('page-perm:50,full');

    //District Body هيئة قضاء
    Route::resource('district-body', 'DistrictBodyController')->only(['index', 'show'])->middleware('page-perm:50,view');
    Route::resource('district-body', 'DistrictBodyController')->except(['index', 'show'])->middleware('page-perm:50,full');
    Route::delete('district-body-delete-date/{id}/{phaseNumber}', 'DistrictBodyController@deleteDate')->middleware('page-perm:50,full');
    Route::post('district-body-edit-date/{id}/{phaseNumber}', 'DistrictBodyController@editDate')->middleware('page-perm:50,full');

    //Local Body Controller هيئة محلية
    Route::resource('local-body', 'LocalBodyController')->only(['index', 'show'])->middleware('page-perm:50,view');
    Route::resource('local-body', 'LocalBodyController')->except(['index', 'show'])->middleware('page-perm:50,full');
    Route::delete('local-body-delete-date/{id}/{phaseNumber}', 'LocalBodyController@deleteDate')->middleware('page-perm:50,full');
    Route::post('local-body-edit-date/{id}/{phaseNumber}', 'LocalBodyController@editDate')->middleware('page-perm:50,full');

    //District Controller
    Route::post('get-regions-by-district-id/{id}', 'DistrictController@getRegionsByDistrictId')->middleware('page-perm:50,view');


    //Central Committee Coordinator
    Route::resource('central-committee-coordinator', 'CentralCommitteeCoordinatorController')->only(['index', 'show'])->middleware('page-perm:50,view');
    Route::resource('central-committee-coordinator', 'CentralCommitteeCoordinatorController')->except(['index', 'show'])->middleware('page-perm:50,full');
    Route::delete('central-committee-coordinator-delete-date/{id}/{phaseNumber}', 'CentralCommitteeCoordinatorController@deleteDate')->middleware('page-perm:50,full');
    Route::post('central-committee-coordinator-edit-date/{id}/{phaseNumber}', 'CentralCommitteeCoordinatorController@editDate')->middleware('page-perm:50,full');


    //Central Committee Memeber
    Route::resource('central-committee', 'CentralCommitteeController')->only(['index', 'show'])->middleware('page-perm:50,view');
    Route::resource('central-committee', 'CentralCommitteeController')->except(['index', 'show'])->middleware('page-perm:50,full');
    Route::delete('central-committee-delete-date/{id}/{phaseNumber}', 'CentralCommitteeController@deleteDate')->middleware('page-perm:50,full');
    Route::post('central-committee-edit-date/{id}/{phaseNumber}', 'CentralCommitteeController@editDate')->middleware('page-perm:50,full');


    //Consulting Committee لجنة إستشارية
    Route::resource('consulting-committee', 'ConsultingCommitteeController')->only(['index', 'show'])->middleware('page-perm:50,view');
    Route::resource('consulting-committee', 'ConsultingCommitteeController')->except(['index', 'show'])->middleware('page-perm:50,full');
    Route::delete('consulting-committee-delete-date/{id}/{phaseNumber}', 'ConsultingCommitteeController@deleteDate')->middleware('page-perm:50,full');
    Route::post('consulting-committee-edit-date/{id}/{phaseNumber}', 'ConsultingCommitteeController@editDate')->middleware('page-perm:50,full');


    //Donations — page 49
    Route::get('donation-update-form', 'DonationBannerController@changeImageForm')->name('donation.update.form')->middleware('page-perm:49,full');
    Route::post('donation-update-image', 'DonationBannerController@changeImage')->name('donation.image.update')->middleware('page-perm:49,full');


    //Internal Election — page 57, covers its Candidates sub-feature too
    Route::resource('internal-election', 'InternalElectionController')->only(['index', 'show'])->middleware('page-perm:57,view');
    Route::resource('internal-election', 'InternalElectionController')->except(['index', 'show'])->middleware('page-perm:57,full');
    Route::post('internal-election/publish/{id}', 'InternalElectionController@publish')->middleware('page-perm:57,full');
    Route::get('internal-election/export/{id}', 'InternalElectionController@export')->name('internal-election.export')->middleware('page-perm:57,view');
    Route::get('internal-election-vote/reset', 'InternalElectionController@reset')->name('internal-election-votes.reset')->middleware('page-perm:57,full');


    //Internal Election Candidates
    Route::get('internal-election-cadidates/{id}', 'InternalElectionCandidatesController@index')->name('internal-election-candidates.index')->middleware('page-perm:57,view');
    Route::post('internal-election-cadidates-store', 'InternalElectionCandidatesController@store')->name('internal-election-candidates.store')->middleware('page-perm:57,full');
    Route::get('internal-election-cadidates-create', 'InternalElectionCandidatesController@create')->name('internal-election-candidates.create')->middleware('page-perm:57,full');
    Route::delete('internal-election-cadidates-delete/{id}', 'InternalElectionCandidatesController@destroy')->name('internal-election-candidates.destroy')->middleware('page-perm:57,full');

    //National Council Poll Questions Answer
    /*Route::resource('national-council-poll.questions.answers','NationalCouncilPollQuestionAnswersController');*/

    //National Council Poll — page 58, covers its Questions sub-feature too
    Route::get('national-council-poll/{id}/clear', 'NationalCouncilPollController@clear')->name('national-council-poll.clear')->middleware('page-perm:58,full');
    Route::get('national-council-poll/{id}/results', 'NationalCouncilPollController@results')->name('national-council-poll.results')->middleware('page-perm:58,view');
    Route::resource('national-council-poll', 'NationalCouncilPollController')->only(['index', 'show'])->middleware('page-perm:58,view');
    Route::resource('national-council-poll', 'NationalCouncilPollController')->except(['index', 'show'])->middleware('page-perm:58,full');

    //National Council Poll Questions
    Route::resource('national-council-poll.questions', 'NationalCouncilPollQuestionsController')->only(['index', 'show'])->middleware('page-perm:58,view');
    Route::resource('national-council-poll.questions', 'NationalCouncilPollQuestionsController')->except(['index', 'show'])->middleware('page-perm:58,full');
});


//Route::get('/login/owner', 'Auth\OwnerLoginController@showLoginForm')->name('owner.login');
//Route::post('/login/owner', 'Auth\OwnerLoginController@login');
//Route::get('/owner/logout', 'Auth\OwnerLoginController@logout')->name('owner.logout');
Route::get('/admin/logout', 'Auth\LoginController@logout')->name('admin.logout');

Route::get('donations', 'TransactionController@create')->name('transactions.create');
Route::post('donations/store', 'TransactionController@store')->name('transactions.store');
Route::get('payment-mobile-response/{success}/{message}', 'TransactionController@responseUpdateTransaction')->name('payment-mobile-response');

Auth::routes();
