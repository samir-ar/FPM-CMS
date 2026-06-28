<?php

namespace App\Http\Controllers\Api\V2;

use App;
use Validator;
use Storage;
use Carbon\Carbon;
use App\V2\Faq;
use App\V2\News;
use App\V2\InternalProcess;
use App\V2\Poll;
use App\V2\Event;
use App\V2\Media;
use App\V2\Album;
use App\V2\Setting;
use App\V2\Person;
use App\V2\Webview;
use App\V2\Content;
use App\V2\Volunteer;
use App\V2\LiveStream;
use App\V2\FaqCategory;
use App\V2\Placeholder;
use App\V2\Achievement;
use App\V2\Law;
use App\V2\AppUser;
use App\V2\Transaction;
use App\V2\Biography;
use App\V2\BillTransaction;
use Illuminate\Http\Request;
use App\V2\CouncilNationalPoll;
use App\V2\CouncilNationalPollVote;
use App\V2\DynamicRepresentative;
use App\Http\Traits\ResponseTrait;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\Paginator;
use App\Http\Repositories\V2\ApiRepository;
use App\Http\Repositories\V2\FpmApisRepository;
use App\Http\Repositories\V2\LiveStreamsRepository;
use App\Http\Traits\TokenTrait;
use App\V2\FpmUser;
use App\V2\FpmUsersAction;
use App\V2\InternalElection;
use App\V2\InternalElectionCandidate;

use GuzzleHttp\Client;


use Illuminate\Pagination\LengthAwarePaginator;


class ApiController extends Controller
{

    public function __construct()
    {
        ini_set('memory_limit', '-1');
    }

    use ResponseTrait;
    use TokenTrait;

    // this function will replace getToken function in FpmApisRepository
    public function getFpmUsers(Request $request){
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|exists:fpm_users,MemberId',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $user = FpmUser::where('MemberId', $request->member_id)->with('fpmUsersActions')->first();

        return response()->json($user);
    }
    // this function will replace getToken function in FpmApisRepository

    public function myProfile(Request $request)
    {
        $token = $request->user->token;

        return redirect('https://mobapp.twh-lb.org:444/datacenter/MobilePersonProfile.aspx?tokenid=' . $token);
    }

    public function getQRCode(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:app_users,id',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }
        $qr_code = AppUser::find($request->id);
        return response()->json(asset($qr_code->qr_code));
    }
    public function getInternalProcess(Request $request){
        $internal_process = InternalProcess::all();
        return response()->json($internal_process->map(function ($g) {
            return [
                'id' => $g->id,
                'title' => $g->getTranslation('name', 'ar'),
                'description' => $g->getTranslation('description', 'ar'),
                'link' => $g->link,
            ];
        }));
    }
    public function getSingleInternalProcess(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:internal_process,id',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }
        $internal_process = InternalProcess::find($request->id);
        return response()->json([
            'id' => $internal_process->id,
            'title' => $internal_process->getTranslation('name', 'ar'),
            'description' => $internal_process->getTranslation('description', 'ar'),
            'link' => $internal_process->link,
        ]);
    }

    public function getMemos(Request $request, ApiRepository $apiRepo)
    {
        if (!request('user') && $request->header('token')) {
            $resolved = \App\V2\AppUser::where('token', $request->header('token'))->first();
            if ($resolved) $request->merge(['user' => $resolved, 'groups' => $resolved->groups->pluck('GroupId')->toArray()]);
        }
        $groups = request('groups');
        $memos = ($groups && is_array($groups) && count($groups) > 0)
            ? $apiRepo->getMemosByGroups($groups)
            : \App\V2\Memo::orderBy('date', 'desc')->get();
        return response()->json($memos->map(function ($g) {
            return [
                'id'   => $g->id,
                'name' => $g->name,
                'date' => ($date = $g->date) ? Carbon::parse($date)->timestamp : null,
                'file' => $g->file ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/storage/images/memos/' . $g->file) : null,
            ];
        })->values());
    }


    public function getWebviews(Request $request)
    {

        $token = $request->user ? $request->user->token : null;
        $groups = request('groups') ? implode(',', request('groups')) : null;

        $webviews = Webview::all()->map(function ($r) use ($token, $groups) {
            return [
                'name' => $r->slug,
                'url' => $r->url . '?tokenid=' . $token . '&accesstoken=' . $token . '&groupids=' . $groups,
            ];
        });

        return response()->json($webviews);
    }

    public function likeNews(Request $request, ApiRepository $repo)
    {
        // Resolve user from token header when middleware hasn't set it
        $user = request('user');
        if (!$user && $request->header('token')) {
            $user = \App\V2\AppUser::where('token', $request->header('token'))->first();
        }
        if (!$user) {
            return $this->api_error_response('invalid_token', 101, 'User not authenticated');
        }

        $validator = Validator::make($request->all(), [
            'news_id' => 'required|exists:news,id',
            'like' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }
        return response()->json($repo->likeNews($user, request('news_id'), request('like')));
    }



    public function shareNews(Request $request, ApiRepository $repo)
    {
        $user = request('user');
        if (!$user && $request->header('token')) {
            $user = \App\V2\AppUser::where('token', $request->header('token'))->first();
        }

        $validator = Validator::make($request->all(), [
            'news_id' => 'required|exists:news,id',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        return response()->json($repo->shareNews(request('news_id'), $user));
    }

    public function downloadMemoFiles(Request $request, ApiRepository $repo)
    {
        $validator = Validator::make($request->all(), [
            'memo_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $zipFile = $repo->getMemoFiles($request->memo_id);

        return response()->json(['path' => url($zipFile)]);
    }


    public function getGroups(Request $request)
    {
        $groups = request('groups_info');

        return response()->json($groups->map(function ($g) {
            return [
                'id' => $g->GroupId,
                'name' => $g->GroupName,
            ];
        }));
    }


    public function getMembers(Request $request, FpmApisRepository $fpmRepo)
    {
        //dd(request('groups'));

        $data = [
            'accesstoken' => $request->user->token,
            'group_ids' => request('group_id') ?: implode(',', request('groups')),
        ];

        $members = $fpmRepo->getCMSGroupsMembers($data);

        $salehMembers = [];
        $user = request('user');

        foreach ($members as $member) {
            $salehMembers[] = [
                'member_id' => $member->MemberId,
                'full_name' => $member->Fullname,
                'image' => $member->PersonImage,
                'favorite' => $user->favorites()->where('favorite', $member->MemberId)->first() ? true : false,
                'description' => $member->Description,
                'phone_number' => $member->MobileNumber,
            ];
        }

        return response()->json($salehMembers);
    }


    public function getFavorites(Request $request, FpmApisRepository $fpmRepo)
    {
        $data = [
            'accesstoken' => $request->user->token,
            'group_ids' => implode(',', request('groups'))
        ];

        $members = $fpmRepo->getCMSGroupsMembers($data);

        $salehMembers = [];

        $user = request('user');

        foreach ($members as $member) {
            if ($user->favorites()->where('favorite', $member->MemberId)->first()) {
                $salehMembers[] = [
                    'member_id' => $member->MemberId,
                    'full_name' => $member->Fullname,
                    'image' => $member->PersonImage,
                    'favorite' => $user->favorites()->where('favorite', $member->MemberId)->first() ? true : false,
                ];
            }
        }

        return response()->json($salehMembers);
    }


    public function favoriteMember(Request $request, ApiRepository $repo)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required',
            'favorite' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $repo->makeFavorite(request('user'), request('member_id'), request('favorite'));

        return response()->json(['message' => 'User added as favorite']);
    }

    public function getMessages(Request $request, ApiRepository $repo)
    {
        $placeholder = Placeholder::where('type', 'messages')->first()->image;

        return response()->json($repo->getMessages(request('user'))->map(function ($row) use ($placeholder) {
            return [
                'id' => $row->id,
                'title' => $row->title,
                'text' => $row->text,
                'image' => $row->image ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/notification_images/' . $row->image) : Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/placeholders/' . $placeholder),
            ];
        }));
    }


    public function getPreviousEventsWithPagination(Request $request, ApiRepository $repo)
    {
        if (!request('user') && $request->header('token')) {
            $resolved = \App\V2\AppUser::where('token', $request->header('token'))->first();
            if ($resolved) $request->merge(['user' => $resolved, 'groups' => $resolved->groups->pluck('GroupId')->toArray()]);
        }
        $events = $repo->getEventsPreviousByGroup(request('groups'));

        $user = request('user');

        #see if the news has been ->get() before pagination see news
        $events = $events->orderByDesc("created_at")->paginate(10);

        $events->transform(function ($q) use ($repo) {
            return $repo->getEventInstance($q);
        });

        return response()->json($events);
    }


    //Albums
    #ALl Albums
    public function getAllAlbums(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'type' => 'string|in:videos,images,pdfs,Not defined'
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $album = Album::select("id", "name", "description", "type", "created_at", "thumbnail");

        if (request('type')) {
            $album->where('type', request('type'));
        }

        return response()->json($album->get()->map(function ($q) {
            return [
                "id" => $q->id,
                "name" => $q->getTranslation('name', 'ar'),
                "thumbnail" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'media/thumbnail/' . $q->thumbnail),
                "description" => $q->getTranslation('description', 'ar'),
                "medias" => $q->medias->map(function ($m) {

                    return array(
                        "name" => $m->getTranslation('name', 'ar'),
                        "type" => $m->type,
                        "is_youtube" => ($m->youtube) ? true : false,
                        "file" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'media/' . $m->file_name),
                        "thumbnail" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'media/thumbnail/' . $m->thumbnail),
                        "youtube" => $m->youtube
                    );
                }),
                "type" => $q->type
            ];
        }));
    }


    #Get Album by Id

    #NOT USED ANY MORE
    public function getAlbum(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }
        /*
        $album = Album::whereHas('medias', function($q){
            return $q->get();
        })->get(); */
        $album = Album::where("id", request("id"))->with("medias");


        $album = $album->get()->map(function ($q) {
            return [
                "id" => $q->id,
                "name" => $q->getTranslation('name', 'ar'),
                "description" => $q->getTranslation('description', 'ar'),
                "type" => $q->type,
                "album_id" => $q->album_id,
                "medias" => Media::where('album_id', $q->id)->get()->map(function ($q) {
                    return [
                        "id" => $q->id,
                        "name" => $q->name,
                        "description" => $q->description,
                        "thumbnail" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'media/' . $q->thumbnail),
                        "file" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'media/' . $q->file_name),
                        "images" => [
                            "http://fpm.tedmob.com/images/events/1569255843Crowd.jpeg",
                            "http://fpm.tedmob.com/images/events/1569255843Crowd.jpeg",
                            "http://fpm.tedmob.com/images/events/1569255843Crowd.jpeg"
                        ]
                    ];
                })
            ];
        });
        return response()->json($album);
    }

    #delete Album
    public function deleteAlbum($id)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $album = Album::find($id);

        if (!$album) {
            return $this->api_error_response('missing_parameters', 101, "Album not found");
        }

        $mediasNames = $album->medias()->pluck('name')->toArray();

        foreach ($mediasNames as $mediaName) {
            $this->removeFile("media/" . $mediaName);
        }

        $album->delete();
        return response()->json(["message" => "the album has been deleted successfully"]);
    }


    #delete Item
    public function deleteMedia($id)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $media = Media::find($id);

        if (!$album) {
            return $this->api_error_response('missing_parameters', 101, "item not found");
        }
        $media->delete();
        return response()->json(["message" => "the item has been deleted successfully"]);
    }


    #Add media
    public function createAlbum(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        Album::create($request->only(['name', 'description']));

        return response()->json(["message" => "the item has been deleted successfully"]);
    }


    #add Album
    public function addMedia(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            "media" => 'required|mimes:txt,pdf,xls,xlsx,jepg,jpg,bmp,png,doc,docs,pptx,ppt,avi,mpeg,quicktime,mp4,zip,rar',
            'album_id' => 'required|exist:albums,id'
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $name = $this->moveFile(request('media'), 'media');

        Media::create($request->only(['name', 'description']));
        return response()->json(["message" => "the added successfully"]);
    }


    public function getWallFeed(Request $request, ApiRepository $apiRepository)
    {
        // Resolve user from token header when middleware hasn't set it
        if (!request('user') && $request->header('token')) {
            $resolved = \App\V2\AppUser::where('token', $request->header('token'))->first();
            if ($resolved) $request->merge(['user' => $resolved]);
        }

        $merged = null;

        if (request('user')) {
            $polls = $apiRepository->getPollsByGroupsRaw(request('user'), request('groups'))->sortByDesc('created_at');

            if ($polls->count() > 0) {
                $polls = $polls->map(function ($poll) use ($apiRepository) {
                    return $apiRepository->getGenericInstance($poll, "POLLS");
                });
                $merged = $polls;
            }
        }



        $events = $apiRepository->getEventsUpcomingByGroup(request('groups'))->get()->sortByDesc('created_at');

        if ($events->count() > 0) {
            $events = $events->map(function ($e) use ($apiRepository) {
                return   $apiRepository->getGenericInstance($e, "EVENTS");
            });

            if ($merged) {
                $merged = $merged->merge($events)->sortByDesc('created_at');
            } else {
                $merged = $events;
            }
        }

        // $placeholder = Placeholder::where('type', 'news')->first()->image;
        $user = request('user');
        $news = $apiRepository->getNewsByGroups(request('groups'))->get()->sortByDesc('created_at');


        if ($news->count() > 0) {
            $news = $news->map(function ($n) use ($apiRepository, $user) {
                return  $apiRepository->getGenericInstance($n, "NEWS", null, $user);
            });

            if ($merged) {
                $merged = $merged->merge($news)->sortByDesc('created_at');
            } else {
                $merged = $news;
            }
        }

        if (!$merged) {
            return response()->json(array(
                "current_page" => 1,
                "data" => [],
                "first_page_url" => null,
                "from" =>  1,
                "last_page" => 1,
                "last_page_url" => null,
                "next_page_url" => null,
                "path" =>  null,
                "per_page" => 10,
                "prev_page_url" => null,
                "to" => 1,
                "total" => 0
            ));
        }
        //Define how many items we want to be visible in each page
        $perPage = 6;

        //Get current page form url e.g. &page=6
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        //Slice the collection to get the items to display in current page
        $currentPageSearchResults = $merged->slice(($currentPage - 1) * $perPage, $perPage)->all();

        //Create our paginator and pass it to the view
        $paginatedSearchResults = new LengthAwarePaginator(array_values($currentPageSearchResults), count($merged), $perPage, $currentPage,  ['path' => url('/api/v2/get-wall-feed')]);

        //return response()->json($paginatedSearchResults);
        return response()->json($paginatedSearchResults);
    }


    public function getUpcomingEventsWithPagination(Request $request, ApiRepository $repo)
    {
        if (!request('user') && $request->header('token')) {
            $resolved = \App\V2\AppUser::where('token', $request->header('token'))->first();
            if ($resolved) $request->merge(['user' => $resolved, 'groups' => $resolved->groups->pluck('GroupId')->toArray()]);
        }
        $events = $repo->getEventsUpcomingByGroup(request('groups'));

        $user = request('user');

        #see if the news has been ->get() before pagination see news
        $events = $events->orderByDesc("created_at")->paginate(10);

        $events->transform(function ($q) use ($repo) {
            return $repo->getEventInstance($q);
        });

        return response()->json($events);
    }

    public function getNewsWithPagination(Request $request, ApiRepository $repo)
    {
        if (request('user')) {

            //dd(app()->getLocale());


            $placeholder = Placeholder::where('type', 'news')->first()->image;
            $news = $repo->getNewsByGroups(request('groups'));

            $user = request('user');

            $news = $news->paginate(10);


            $news->transform(function ($q) use ($user, $placeholder, $repo) {
                return $repo->getNewsInstance($q, $user, $placeholder);
            });
        } else {

            $news = $repo->skipModeNews();


            $perPage = 10;

            $page = request('page') ?: (Paginator::resolveCurrentPage() ?: 1);


            $items = $news instanceof Collection ? $news : Collection::make($news);

            $lap = new LengthAwarePaginator(array_values(array_filter($items->forPage($page, $perPage)->all())), $items->count(), $perPage, $page, []);

            $baseUrl = route('api.news.paginated');
            $lap->setPath($baseUrl);

            return response()->json($lap);
        }


        return response()->json($news);
    }


    //App Versions -  Force Update
    public function appVersions()
    {
        // $settings = Setting::select(['android_version', 'ios_version', 'force_update_ios', 'force_update_android', 'update_title', 'update_message'])->first();
        $settings = ['android_version' => '1.1.1', 'ios_version' => '3.0.10', 'force_update_ios' => false, 'force_update_android' => false, 'update_title' => 'Title', 'update_message' => 'Text'];
        return response()->json($settings);
    }

    public function getNews(Request $request, ApiRepository $repo)
    {

        if (request('user')) {

            //dd(app()->getLocale());


            $placeholder = Placeholder::where('type', 'news')->first()->image;
            $news = $repo->getNewsByGroups(request('groups'))->get();

            $user = request('user');
            $news = $news->map(function ($n) use ($user, $placeholder, $repo) {

                return $repo->getNewsInstance($n, $user, $placeholder);
                /*
                return [
                    'id' => $n->id,
                    'title' => $n->title,
                    'source' => $n->source,
                    'source_image' => $n->source_image ? secure_url($n->source_image) : secure_url($placeholder),
                    'details' => $n->details,
                    'type' => $n->type,
                    'file' => secure_url($n->file),
                    'thumbnail' => $n->thumbnail ? secure_url($n->thumbnail) : secure_url($placeholder),
                    'date' => strtotime($n->date),
                    'link' => secure_url($n->file),
                    'likes_nb' => $n->users()->count(),
                    'like' => $n->users()->where('user_id', $user->id)->first() ? true : false,
                    'shares' => $n->shares,
                ];
                */
            });
        } else
            $news = $repo->skipModeNews();


        return response()->json($news);
    }


    /////////////////////////////
    //  National Council Polls //
    /////////////////////////////
    public function canIVoteToNationalCouncilPollMessage(Request $request, ApiRepository $apiRepository)
    {
        $user = request('user');

        $statusCode = $this->canIVoteToNationalCouncilPoll($request, $apiRepository);

        $response = [
            'is_poll_available' => false,
            'status' => 0,
            'title' => null,
            'can_i_vote' => false,
            'message' => null
        ];

        //No poll available
        if ($statusCode == 404) {
            $response['message'] = 'ليس هنالك أية فرصة متاحة للتصويت أو انك غير مصرّح لك بالتصويت';
            $response['status'] = 404;
            return response()->json($response);
        }

        $poll = $apiRepository->getNationalCouncil($user);
        $response['title'] = ($poll) ? $poll->title : null;

        if ($statusCode == 200) {
            //Not voted yet
            $response['is_poll_available'] = true;
            $response['can_i_vote'] = true;
            $response['message'] = 'يمكنك التصويت';
            $response['is_poll_available'] = true;
            $response['status'] = 200;
            return response()->json($response);
        } else {
            //Already Voted
            $response['message'] = 'لقد قمت بالتصويت';
            $response['is_poll_available'] = true;
            $response['status'] = 401;
            return response()->json($response);
        }
    }

    public function canIVoteToNationalCouncilPoll($request, ApiRepository $apiRepository, $withPolls = false)
    {
        $user = $request->user;

        //Check if there is a poll and the user is authorised to vote
        //$poll = $apiRepository->getNationalCouncilByGroup(request('groups'))->first();
        $poll = $apiRepository->getNationalCouncil($user);
        if (!$poll) return 404;

        //Check if the user has voted or not
        $vote = CouncilNationalPollVote::where('poll_id', $poll->id)->where('user_id', $user->id)->first();

        if (!$vote && $withPolls) return $poll;

        if (!$vote) return 200;

        return 401;
    }

    public function getLatestNationalCouncilPoll(Request $request, ApiRepository $apiRepository)
    {
        //Return a poll
        $statusCodeOrPoll = $this->canIVoteToNationalCouncilPoll($request, $apiRepository, true);

        if (gettype($statusCodeOrPoll) != 'object') return $this->api_error_response('missing_parameters', 101, implode(', ', ['message' => 'لا يمكنك التصويت']));

        $poll = $statusCodeOrPoll;

        return response()->json($poll->format());
    }

    //////////////////////////////////
    //  National Council Polls Vote //
    //////////////////////////////////
    public function voteToNationalPoll(Request $request, ApiRepository  $apiRepository)
    {
        $validator = Validator::make($request->all(), [
            'poll_id' => 'required|exists:council_national_polls,id',
            'answers.*.question_id' => 'required',
            'answers.*.answer_id' => 'required'
        ]);

        $statusCode = $this->canIVoteToNationalCouncilPoll($request, $apiRepository);
        if ($statusCode != 200) return $this->api_error_response('can_vote', 101, implode(', ', ['message' => 'لا يمكنك التصويت']));

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }


        $poll  = CouncilNationalPoll::find($request->poll_id);

        //$weight = App\V2\CouncilNationalPollPermission::where('poll_id', $poll->id)->where('user_id', $request->user->id)->first()->vote_weight;
        $weight = App\V2\CouncilNationalPollPermission::where('poll_id', $poll->id)->where('member_id', $request->user->member_id)->first()->vote_weight;

        foreach ($request->answers as $answer) {

            App\V2\CouncilNationalPollVote::create(
                [
                    'user_id' => $request->user->id,
                    'question_id' => $answer['question_id'],
                    'answer_id' => $answer['answer_id'],
                    'poll_id' => $request['poll_id'],
                    'weight' => $weight
                ]
            );
        }

        return response()->json(['message' => 'لقد تم التصويت بنجاح']);
    }


    public function getPollById(Request $request, ApiRepository $repo)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:polls,id',
        ]);
        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $poll = $repo->getPollById(request('user'), request('groups'), request('id'));

        return response()->json($poll);
    }

    /*
        Route::post('get-my-candidates-status','ApiController@getCandidates');
        Route::post('get-my-candidacy-status','ApiController@getCandidacy');
    */


    public function getCandidates(Request $request)
    {
        if (!request('user') && $request->header('token')) {
            $resolved = \App\V2\AppUser::where('token', $request->header('token'))->first();
            if ($resolved) $request->merge(['user' => $resolved]);
        }
        $user = request('user');

        return response()->json(
            [
                "url" => secure_url("/tracker/" . $user->token)
            ]
        );
    }


    public function getPolls(Request $request, ApiRepository $repo)
    {
        if (!request('user') && $request->header('token')) {
            $resolved = \App\V2\AppUser::where('token', $request->header('token'))->first();
            if ($resolved) $request->merge(['user' => $resolved, 'groups' => $resolved->groups->pluck('GroupId')->toArray()]);
        }
        $polls = $repo->getPollsByGroups(request('user'), request('groups'));
        return response()->json($polls);
    }



    public function getPreviousPolls(Request $request, ApiRepository $repo)
    {
        //previous polls
        $polls = $repo->getPreviousPolls(request('user'), request('groups'));

        return response()->json($polls);
    }

    public function answerPoll(Request $request, ApiRepository $repo)
    {
        if (!request('user') && $request->header('token')) {
            $resolved = \App\V2\AppUser::where('token', $request->header('token'))->first();
            if ($resolved) $request->merge(['user' => $resolved]);
        }
        $validator = Validator::make($request->all(), [
            'option_id' => 'required|exists:polls_options,id',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $repo->userAnswerPoll(request('user'), request('option_id'));

        return response()->json(['message' => 'Thank you for participating.']);
    }

    public function volunteers(Request $request)
    {
        return response()->json(Volunteer::all()->map(function ($v) {
            return [
                'id' => $v->id,
                'title' => $v->title,
                'text' => $v->text,
                'image' => $v->image ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/volunteers/' . $v->image) : null,
            ];
        }));
    }

    public function getPreviousEvents(Request $request, ApiRepository $repo)
    {
        $events = $repo->getEventsPreviousByGroup(request('groups'))->orderByDesc('created_at')->get();

        return response()->json($events->map(function ($e) use ($repo) {

            return $repo->getEventInstance($e);
            /*
           return [
               'id' => $e->id,
               'name' => $e->name,
               'details' => $e->details,
               'organized_by' => $e->organized_by,
               'location' => $e->location,
               'lng' => $e->lng,
               'lat' => $e->lat,
               'from_date' => strtotime($e->from_date),
               'to_date' => strtotime($e->to_date),
               'thumbnail' => secure_url($e->image),
               'images' => $e->images->map(function($i){
                   return [
                       'img' => secure_url($i->src),
                   ];
               })->pluck('img')->toArray(),
           ];
           */
        }));
    }

    public function getUpcomingEvents(Request $request, ApiRepository $repo)
    {
        $events = $repo->getEventsUpcomingByGroup(request('groups'))->orderByDesc('created_at')->get();

        return response()->json($events->map(function ($e) use ($repo) {

            return $repo->getEventInstance($e);
            /*
            return [
                'id' => $e->id,
                'name' => $e->name,
                'details' => $e->details,
                'organized_by' => $e->organized_by,
                'location' => $e->location,
                'lng' => $e->lng,
                'lat' => $e->lat,
                'from_date' => strtotime($e->from_date),
                'to_date' => strtotime($e->to_date),
                'thumbnail' => secure_url($e->image),
                'images' => $e->images->map(function($i){
                    return [
                        'img' => secure_url($i->src),
                    ];
                })->pluck('img')->toArray(),
            ];
            */
        }));
    }

    public function getEventById(Request $request, ApiRepository $repo)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id,deleted_at,NULL',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $event = Event::find(request('event_id'));


        return response()->json($repo->getEventInstance($event));
    }

    public function getNewsById(Request $request, ApiRepository $repo)
    {
        $validator = Validator::make($request->all(), [
            'news_id' => 'required|exists:news,id,deleted_at,NULL',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $news = News::find(request('news_id'));


        $user = request('user');
        $placeholder = Placeholder::where('type', 'news')->first()->image;

        return response()->json($repo->getNewsInstance($news, $user, $placeholder));
    }

    public function becomeVolunteer(Request $request, ApiRepository $repo)
    {
        $validator = Validator::make($request->all(), [
            'volunteer_id' => 'exists:volunteers,id',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $repo->saveVolunteer(request('user'), request('volunteer_id'));

        return response()->json(['message' => 'Thank you for applying.']);
    }


    public function getRepresentatives(Request $request, ApiRepository $repo)
    {
        // $validator = Validator::make($request->all(), [
        //     'type' => 'required',
        // ]);

        $sections =  DynamicRepresentative::orderBy('order', 'asc')->get()->map(function ($r) {

            return [
                'id' => $r->id,
                'title' => $r->title,
                'text' => $r->text,
                'persons' => $r->persons()->orderBy('rep_order', 'asc')->get()->map(function ($p) {
                    return [
                        "id" => $p->id,
                        "type" => $p->type,
                        "name" => $p->name,
                        "category" => $p->category,
                        "image" => isset($p->image) ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/representatives/' . $p->image) : null,
                        "position" => ($p->position) ? $p->position->name : null
                    ];
                }),
            ];
        });
        return response()->json([
            'sections' => $sections,
        ]);
    }

    public function getBiographyDetails(Request $request, ApiRepository $repo, $id)
    {
        $sections = $repo->getBiographyDetails($id);

        return response()->json($sections);
    }

    public function getAchievements(Request $request)
    {
        $achievements = Achievement::where('deleted_at', null)->get()->map(function ($a) {
            return [
                "id" => $a->id,
                "title" => $a->title,
                "text" => $a->text,
            ];
        });
        return response()->json(
            $achievements
        );
    }

    public function getLaws(Request $request, ApiRepository $apiRepo)
    {
        return response()->json($apiRepo->getLaws($request->status)->map(function ($g) {
            return [
                'id' => $g->id,
                'name' => $g->name,
                'date' => ($date = $g->date) ? Carbon::parse($date)->timestamp : null,
                'details' => $g->details,
                'file' => ($g->file) ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/laws/' . $g->file) : null,
            ];
        }));
    }

    public function downloadLawFiles(Request $request, ApiRepository $repo)
    {
        $validator = Validator::make($request->all(), [
            'law_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $zipFile = $repo->getLawFiles($request->law_id);

        return response()->json(['path' => url($zipFile)]);
    }

    public function getLinks(Request $request, ApiRepository $repo)
    {
        if (!request('user') && $request->header('token')) {
            $resolved = \App\V2\AppUser::where('token', $request->header('token'))->first();
            if ($resolved) $request->merge(['user' => $resolved, 'groups' => $resolved->groups->pluck('GroupId')->toArray()]);
        }

        if (!request('user'))
            $links = $repo->getPublicLinks();
        else
            $links = $repo->getLinksByGroups(request('groups'));

        return response()->json($links->map(function ($l) {
            return [
                'id' => $l->id,
                'name' => $l->name,
                'link' => $l->link,
            ];
        }));
    }

    public function aboutUs(Request $request)
    {
        $content = Content::where('category', 'about-us-content')->first();
        $media = Content::where('category', 'about-us-social-media')->first();

        $mediaObj = $media ? json_decode($media->text) : null;

        return response()->json([
            'images' => ($content && $content->image)
                ? [Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/content/' . $content->image)]
                : [],
            'text' => $content ? (App::getLocale() == 'en' ? $content->text : $content->text_ar) : '',
            'email' => $mediaObj->email ?? null,
            'facebook' => $mediaObj->facebook ?? null,
            'instagram' => $mediaObj->instagram ?? null,
            'youtube' => $mediaObj->youtube ?? null,
            'linkedIn' => $mediaObj->linkedIn ?? null,
            'twitter' => $mediaObj->twitter ?? null,
            'phone_number' => '96103067387',
        ]);
    }

    public function faqCategories(Request $request)
    {
        return response()->json(FaqCategory::orderBy('order', 'asc')->select('id', 'name')->get()->map(function ($r) {
            return [
                'id' => $r->id,
                'name' => $r->name,
            ];
        }));
    }

    public function userTalk(Request $request, ApiRepository $repo)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'message' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        return response()->json($repo->userTalkToUs(request('user'), $request->all()));
    }

    public function logout(Request $request)
    {
        $user = request('user');
        $user->player_id = null;
        $user->save();

        return response()->json(['message' => 'User Logout.']);
    }

    public function faqsByCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cat_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        return response()->json(Faq::orderBy('order', 'asc')->select('id', 'name', 'details')
            ->where('cat_id', request('cat_id'))->get()->map(function ($r) {
                return [
                    'id' => $r->id,
                    'name' => $r->name,
                    'details' => $r->details,
                ];
            }));
    }

    public function getCountryCodes(Request $request)
    {
        $country_codes = json_decode('[{"name":"Afghanistan","dial_code":"+93","code":"AF"},{"name":"Albania","dial_code":"+355","code":"AL"},{"name":"Algeria","dial_code":"+213","code":"DZ"},{"name":"AmericanSamoa","dial_code":"+1 684","code":"AS"},{"name":"Andorra","dial_code":"+376","code":"AD"},{"name":"Angola","dial_code":"+244","code":"AO"},{"name":"Anguilla","dial_code":"+1 264","code":"AI"},{"name":"Antigua and Barbuda","dial_code":"+1268","code":"AG"},{"name":"Argentina","dial_code":"+54","code":"AR"},{"name":"Armenia","dial_code":"+374","code":"AM"},{"name":"Aruba","dial_code":"+297","code":"AW"},{"name":"Australia","dial_code":"+61","code":"AU"},{"name":"Austria","dial_code":"+43","code":"AT"},{"name":"Azerbaijan","dial_code":"+994","code":"AZ"},{"name":"Bahamas","dial_code":"+1 242","code":"BS"},{"name":"Bahrain","dial_code":"+973","code":"BH"},{"name":"Bangladesh","dial_code":"+880","code":"BD"},{"name":"Barbados","dial_code":"+1 246","code":"BB"},{"name":"Belarus","dial_code":"+375","code":"BY"},{"name":"Belgium","dial_code":"+32","code":"BE"},{"name":"Belize","dial_code":"+501","code":"BZ"},{"name":"Benin","dial_code":"+229","code":"BJ"},{"name":"Bermuda","dial_code":"+1 441","code":"BM"},{"name":"Bhutan","dial_code":"+975","code":"BT"},{"name":"Bosnia and Herzegovina","dial_code":"+387","code":"BA"},{"name":"Botswana","dial_code":"+267","code":"BW"},{"name":"Brazil","dial_code":"+55","code":"BR"},{"name":"British Indian Ocean Territory","dial_code":"+246","code":"IO"},{"name":"Bulgaria","dial_code":"+359","code":"BG"},{"name":"Burkina Faso","dial_code":"+226","code":"BF"},{"name":"Burundi","dial_code":"+257","code":"BI"},{"name":"Cambodia","dial_code":"+855","code":"KH"},{"name":"Cameroon","dial_code":"+237","code":"CM"},{"name":"Canada","dial_code":"+1","code":"CA"},{"name":"Cape Verde","dial_code":"+238","code":"CV"},{"name":"Cayman Islands","dial_code":"+ 345","code":"KY"},{"name":"Central African Republic","dial_code":"+236","code":"CF"},{"name":"Chad","dial_code":"+235","code":"TD"},{"name":"Chile","dial_code":"+56","code":"CL"},{"name":"China","dial_code":"+86","code":"CN"},{"name":"Christmas Island","dial_code":"+61","code":"CX"},{"name":"Colombia","dial_code":"+57","code":"CO"},{"name":"Comoros","dial_code":"+269","code":"KM"},{"name":"Congo","dial_code":"+242","code":"CG"},{"name":"Cook Islands","dial_code":"+682","code":"CK"},{"name":"Costa Rica","dial_code":"+506","code":"CR"},{"name":"Croatia","dial_code":"+385","code":"HR"},{"name":"Cuba","dial_code":"+53","code":"CU"},{"name":"Cyprus","dial_code":"+537","code":"CY"},{"name":"Czech Republic","dial_code":"+420","code":"CZ"},{"name":"Denmark","dial_code":"+45","code":"DK"},{"name":"Djibouti","dial_code":"+253","code":"DJ"},{"name":"Dominica","dial_code":"+1 767","code":"DM"},{"name":"Dominican Republic","dial_code":"+1 849","code":"DO"},{"name":"Ecuador","dial_code":"+593","code":"EC"},{"name":"Egypt","dial_code":"+20","code":"EG"},{"name":"El Salvador","dial_code":"+503","code":"SV"},{"name":"Equatorial Guinea","dial_code":"+240","code":"GQ"},{"name":"Eritrea","dial_code":"+291","code":"ER"},{"name":"Estonia","dial_code":"+372","code":"EE"},{"name":"Ethiopia","dial_code":"+251","code":"ET"},{"name":"Faroe Islands","dial_code":"+298","code":"FO"},{"name":"Fiji","dial_code":"+679","code":"FJ"},{"name":"Finland","dial_code":"+358","code":"FI"},{"name":"France","dial_code":"+33","code":"FR"},{"name":"French Guiana","dial_code":"+594","code":"GF"},{"name":"French Polynesia","dial_code":"+689","code":"PF"},{"name":"Gabon","dial_code":"+241","code":"GA"},{"name":"Gambia","dial_code":"+220","code":"GM"},{"name":"Georgia","dial_code":"+995","code":"GE"},{"name":"Germany","dial_code":"+49","code":"DE"},{"name":"Ghana","dial_code":"+233","code":"GH"},{"name":"Gibraltar","dial_code":"+350","code":"GI"},{"name":"Greece","dial_code":"+30","code":"GR"},{"name":"Greenland","dial_code":"+299","code":"GL"},{"name":"Grenada","dial_code":"+1 473","code":"GD"},{"name":"Guadeloupe","dial_code":"+590","code":"GP"},{"name":"Guam","dial_code":"+1 671","code":"GU"},{"name":"Guatemala","dial_code":"+502","code":"GT"},{"name":"Guinea","dial_code":"+224","code":"GN"},{"name":"Guinea-Bissau","dial_code":"+245","code":"GW"},{"name":"Guyana","dial_code":"+595","code":"GY"},{"name":"Haiti","dial_code":"+509","code":"HT"},{"name":"Honduras","dial_code":"+504","code":"HN"},{"name":"Hungary","dial_code":"+36","code":"HU"},{"name":"Iceland","dial_code":"+354","code":"IS"},{"name":"India","dial_code":"+91","code":"IN"},{"name":"Indonesia","dial_code":"+62","code":"ID"},{"name":"Iraq","dial_code":"+964","code":"IQ"},{"name":"Ireland","dial_code":"+353","code":"IE"},{"name":"Israel","dial_code":"+972","code":"IL"},{"name":"Italy","dial_code":"+39","code":"IT"},{"name":"Jamaica","dial_code":"+1 876","code":"JM"},{"name":"Japan","dial_code":"+81","code":"JP"},{"name":"Jordan","dial_code":"+962","code":"JO"},{"name":"Kazakhstan","dial_code":"+7 7","code":"KZ"},{"name":"Kenya","dial_code":"+254","code":"KE"},{"name":"Kiribati","dial_code":"+686","code":"KI"},{"name":"Kuwait","dial_code":"+965","code":"KW"},{"name":"Kyrgyzstan","dial_code":"+996","code":"KG"},{"name":"Latvia","dial_code":"+371","code":"LV"},{"name":"Lebanon","dial_code":"+961","code":"LB"},{"name":"Lesotho","dial_code":"+266","code":"LS"},{"name":"Liberia","dial_code":"+231","code":"LR"},{"name":"Liechtenstein","dial_code":"+423","code":"LI"},{"name":"Lithuania","dial_code":"+370","code":"LT"},{"name":"Luxembourg","dial_code":"+352","code":"LU"},{"name":"Madagascar","dial_code":"+261","code":"MG"},{"name":"Malawi","dial_code":"+265","code":"MW"},{"name":"Malaysia","dial_code":"+60","code":"MY"},{"name":"Maldives","dial_code":"+960","code":"MV"},{"name":"Mali","dial_code":"+223","code":"ML"},{"name":"Malta","dial_code":"+356","code":"MT"},{"name":"Marshall Islands","dial_code":"+692","code":"MH"},{"name":"Martinique","dial_code":"+596","code":"MQ"},{"name":"Mauritania","dial_code":"+222","code":"MR"},{"name":"Mauritius","dial_code":"+230","code":"MU"},{"name":"Mayotte","dial_code":"+262","code":"YT"},{"name":"Mexico","dial_code":"+52","code":"MX"},{"name":"Monaco","dial_code":"+377","code":"MC"},{"name":"Mongolia","dial_code":"+976","code":"MN"},{"name":"Montenegro","dial_code":"+382","code":"ME"},{"name":"Montserrat","dial_code":"+1664","code":"MS"},{"name":"Morocco","dial_code":"+212","code":"MA"},{"name":"Myanmar","dial_code":"+95","code":"MM"},{"name":"Namibia","dial_code":"+264","code":"NA"},{"name":"Nauru","dial_code":"+674","code":"NR"},{"name":"Nepal","dial_code":"+977","code":"NP"},{"name":"Netherlands","dial_code":"+31","code":"NL"},{"name":"Netherlands Antilles","dial_code":"+599","code":"AN"},{"name":"New Caledonia","dial_code":"+687","code":"NC"},{"name":"New Zealand","dial_code":"+64","code":"NZ"},{"name":"Nicaragua","dial_code":"+505","code":"NI"},{"name":"Niger","dial_code":"+227","code":"NE"},{"name":"Nigeria","dial_code":"+234","code":"NG"},{"name":"Niue","dial_code":"+683","code":"NU"},{"name":"Norfolk Island","dial_code":"+672","code":"NF"},{"name":"Northern Mariana Islands","dial_code":"+1 670","code":"MP"},{"name":"Norway","dial_code":"+47","code":"NO"},{"name":"Oman","dial_code":"+968","code":"OM"},{"name":"Pakistan","dial_code":"+92","code":"PK"},{"name":"Palau","dial_code":"+680","code":"PW"},{"name":"Panama","dial_code":"+507","code":"PA"},{"name":"Papua New Guinea","dial_code":"+675","code":"PG"},{"name":"Paraguay","dial_code":"+595","code":"PY"},{"name":"Peru","dial_code":"+51","code":"PE"},{"name":"Philippines","dial_code":"+63","code":"PH"},{"name":"Poland","dial_code":"+48","code":"PL"},{"name":"Portugal","dial_code":"+351","code":"PT"},{"name":"Puerto Rico","dial_code":"+1 939","code":"PR"},{"name":"Qatar","dial_code":"+974","code":"QA"},{"name":"Romania","dial_code":"+40","code":"RO"},{"name":"Rwanda","dial_code":"+250","code":"RW"},{"name":"Samoa","dial_code":"+685","code":"WS"},{"name":"San Marino","dial_code":"+378","code":"SM"},{"name":"Saudi Arabia","dial_code":"+966","code":"SA"},{"name":"Senegal","dial_code":"+221","code":"SN"},{"name":"Serbia","dial_code":"+381","code":"RS"},{"name":"Seychelles","dial_code":"+248","code":"SC"},{"name":"Sierra Leone","dial_code":"+232","code":"SL"},{"name":"Singapore","dial_code":"+65","code":"SG"},{"name":"Slovakia","dial_code":"+421","code":"SK"},{"name":"Slovenia","dial_code":"+386","code":"SI"},{"name":"Solomon Islands","dial_code":"+677","code":"SB"},{"name":"South Africa","dial_code":"+27","code":"ZA"},{"name":"South Georgia and the South Sandwich Islands","dial_code":"+500","code":"GS"},{"name":"Spain","dial_code":"+34","code":"ES"},{"name":"Sri Lanka","dial_code":"+94","code":"LK"},{"name":"Sudan","dial_code":"+249","code":"SD"},{"name":"Suriname","dial_code":"+597","code":"SR"},{"name":"Swaziland","dial_code":"+268","code":"SZ"},{"name":"Sweden","dial_code":"+46","code":"SE"},{"name":"Switzerland","dial_code":"+41","code":"CH"},{"name":"Tajikistan","dial_code":"+992","code":"TJ"},{"name":"Thailand","dial_code":"+66","code":"TH"},{"name":"Togo","dial_code":"+228","code":"TG"},{"name":"Tokelau","dial_code":"+690","code":"TK"},{"name":"Tonga","dial_code":"+676","code":"TO"},{"name":"Trinidad and Tobago","dial_code":"+1 868","code":"TT"},{"name":"Tunisia","dial_code":"+216","code":"TN"},{"name":"Turkey","dial_code":"+90","code":"TR"},{"name":"Turkmenistan","dial_code":"+993","code":"TM"},{"name":"Turks and Caicos Islands","dial_code":"+1 649","code":"TC"},{"name":"Tuvalu","dial_code":"+688","code":"TV"},{"name":"Uganda","dial_code":"+256","code":"UG"},{"name":"Ukraine","dial_code":"+380","code":"UA"},{"name":"United Arab Emirates","dial_code":"+971","code":"AE"},{"name":"United Kingdom","dial_code":"+44","code":"GB"},{"name":"United States","dial_code":"+1","code":"US"},{"name":"Uruguay","dial_code":"+598","code":"UY"},{"name":"Uzbekistan","dial_code":"+998","code":"UZ"},{"name":"Vanuatu","dial_code":"+678","code":"VU"},{"name":"Wallis and Futuna","dial_code":"+681","code":"WF"},{"name":"Yemen","dial_code":"+967","code":"YE"},{"name":"Zambia","dial_code":"+260","code":"ZM"},{"name":"Zimbabwe","dial_code":"+263","code":"ZW"},{"name":"land Islands","dial_code":"","code":"AX"},{"name":"Antarctica","dial_code":null,"code":"AQ"},{"name":"Bolivia, Plurinational State of","dial_code":"+591","code":"BO"},{"name":"Brunei Darussalam","dial_code":"+673","code":"BN"},{"name":"Cocos (Keeling) Islands","dial_code":"+61","code":"CC"},{"name":"Congo, The Democratic Republic of the","dial_code":"+243","code":"CD"},{"name":"Cote d\'Ivoire","dial_code":"+225","code":"CI"},{"name":"Falkland Islands (Malvinas)","dial_code":"+500","code":"FK"},{"name":"Guernsey","dial_code":"+44","code":"GG"},{"name":"Holy See (Vatican City State)","dial_code":"+379","code":"VA"},{"name":"Hong Kong","dial_code":"+852","code":"HK"},{"name":"Iran, Islamic Republic of","dial_code":"+98","code":"IR"},{"name":"Isle of Man","dial_code":"+44","code":"IM"},{"name":"Jersey","dial_code":"+44","code":"JE"},{"name":"Korea, Democratic People\'s Republic of","dial_code":"+850","code":"KP"},{"name":"Korea, Republic of","dial_code":"+82","code":"KR"},{"name":"Lao People\'s Democratic Republic","dial_code":"+856","code":"LA"},{"name":"Libyan Arab Jamahiriya","dial_code":"+218","code":"LY"},{"name":"Macao","dial_code":"+853","code":"MO"},{"name":"Macedonia, The Former Yugoslav Republic of","dial_code":"+389","code":"MK"},{"name":"Micronesia, Federated States of","dial_code":"+691","code":"FM"},{"name":"Moldova, Republic of","dial_code":"+373","code":"MD"},{"name":"Mozambique","dial_code":"+258","code":"MZ"},{"name":"Palestinian Territory, Occupied","dial_code":"+970","code":"PS"},{"name":"Pitcairn","dial_code":"+872","code":"PN"},{"name":"Réunion","dial_code":"+262","code":"RE"},{"name":"Russia","dial_code":"+7","code":"RU"},{"name":"Saint Barthélemy","dial_code":"+590","code":"BL"},{"name":"Saint Helena, Ascension and Tristan Da Cunha","dial_code":"+290","code":"SH"},{"name":"Saint Kitts and Nevis","dial_code":"+1 869","code":"KN"},{"name":"Saint Lucia","dial_code":"+1 758","code":"LC"},{"name":"Saint Martin","dial_code":"+590","code":"MF"},{"name":"Saint Pierre and Miquelon","dial_code":"+508","code":"PM"},{"name":"Saint Vincent and the Grenadines","dial_code":"+1 784","code":"VC"},{"name":"Sao Tome and Principe","dial_code":"+239","code":"ST"},{"name":"Somalia","dial_code":"+252","code":"SO"},{"name":"Svalbard and Jan Mayen","dial_code":"+47","code":"SJ"},{"name":"Syrian Arab Republic","dial_code":"+963","code":"SY"},{"name":"Taiwan, Province of China","dial_code":"+886","code":"TW"},{"name":"Tanzania, United Republic of","dial_code":"+255","code":"TZ"},{"name":"Timor-Leste","dial_code":"+670","code":"TL"},{"name":"Venezuela, Bolivarian Republic of","dial_code":"+58","code":"VE"},{"name":"Viet Nam","dial_code":"+84","code":"VN"},{"name":"Virgin Islands, British","dial_code":"+1 284","code":"VG"},{"name":"Virgin Islands, U.S.","dial_code":"+1 340","code":"VI"}]');

        return response()->json($country_codes);
    }

    public function getFaqsWebview(Request $request)
    {
        $faqsCategories = FaqCategory::all();

        return view('faqs')->with(compact('faqsCategories'));
    }

    public function getLiveStream(Request $request, LiveStreamsRepository $liveStreamsRepo)
    {
        $liveStream = LiveStream::whereHas('groups', function ($q) {
            return $q->whereIn('group_id', request('groups'))->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->first();

        return $liveStreamsRepo->getInstance($liveStream);
    }

    public function pay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable',
            'name' => 'required',
            'phone_number' => 'required',
            'email' => 'required',
            'currency' => 'required|in:USD,LBP',
            'amount' => 'required',

        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $transaction = Transaction::create([
            'user_id' => request('user_id'),
            'transaction_id' => 'MOB_' . (int)(microtime(true) * 1000),
            'amount' => request('amount'),
            'currency' => currency_iso(request('currency')),
            'user_name' => request('name'),
            'phone_number' => request('phone_number'),
            'email' => request('email'),
        ]);

        return response()->json([
            'url' => route('netcommerce.payment.redirect', $transaction->id) . '?payment=i-pay',
        ]);
    }

    public function bill(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable',
            'name' => 'required',
            'phone_number' => 'required',
            'email' => 'required|email',
            'currency' => 'required|in:USD,LBP',
            'amount' => 'required',
            'recurrence' => 'required|in:monthly,quarterly,yearly,bi-yearly'
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $transaction = BillTransaction::create([
            'user_id' => request('user_id'),
            'amount' => request('amount'),
            'currency' => currency_iso(request('currency')),
            'user_name' => request('name'),
            'phone_number' => request('phone_number'),
            'email' => request('email'),
            'recurrent_freq' => request('recurrence'),
        ]);

        $transaction->transaction_id = 'MOB_BILL_' . $transaction->id;

        $transaction->save();


        return response()->json([
            'url' => route('netcommerce.payment.redirect', $transaction->id) . '?payment=bill',
        ]);
    }

    public function getPaymentAmounts()
    {
        return response()->json([
            'LBP' => [
                5000,
                20000,
                50000,
                100000,
                500000,
            ],
            'USD' => [
                5,
                10,
                50,
                100,
                500,
            ],

        ]);
    }


    ///Election Module

    function canIVoteEndpoint(Request $request)
    {
        $userInfo = request('loggedInUser');
        $user = request('user');

        $response = [
            'is_election_available' => false,
            'can_i_vote' => false,
            'title' => null,
            'message' => null
        ];

        $result = $this->canIVote($user, $userInfo->ElectionStateId);

        if ($result === -1 || !$result) {
            //the default response works fine
        }

        $response['election_state'] = $userInfo->ElectionStateId;

        //Adding Title
        $poll = InternalElection::where('is_active', true)->orderBy('id', 'desc')->first();
        if ($poll) {
            $response['title'] = $poll->title;
        }


        if ($result === 500) {
            $response['message'] = 'لا مرشحين عن دائرتك الإنتخابية';
            $response['is_election_available'] = true;
            $response['can_i_vote'] = false;
        }

        if ($result === 401) {
            $response['message'] = 'لقد قمت بالتصويت';
            $response['is_election_available'] = true;
            $response['can_i_vote'] = false;
        }

        if ($result === 200) {
            $response['is_election_available'] = true;
            $response['can_i_vote'] = true;
        }



        return response()->json($response);
    }

    //Check if the user can vote
    function canIVote($user, $electionStateId)
    {
        $election = InternalElection::orderBy('id', 'desc')->first();

        if (!$election) {
            //No election is create yet
            return -1;
        }


        if ($election->is_active == false) {
            //the election has been closed - He can't vote even if he didn't yet
            return false;
        }


        //Check if there are condidates
        $candidatesCount = InternalElection::orderBy('id', 'desc')->first()->candidates()->where('election_state_id', $electionStateId)->count();

        if ($candidatesCount == 0) {
            return 500;
        }

        $vote = $user->votes()->where('internal_election_id', $election->id)->first();

        if (!$vote) {
            //the user have not voted yet
            return 200;
        } else {
            //the user has already voted
            return 401;
        }
    }


    //get Candidates of the internal Elaction
    function getInternalElectionCandidates(Request $request)
    {
        $user = request('user');
        $userInfo = request('loggedInUser');


        if (!$user) {
            return $this->api_error_response('missing_parameters', 101, "user not found");
        }

        $canIVote = $this->canIVote($user, $userInfo->ElectionStateId);

        if ($canIVote === -1) {
            return $this->api_error_response('missing_parameters', 101, "ما مِن إنتخابات في الوقت الراهن");
        }

        if (!$canIVote || $canIVote === 401 || $canIVote === 500) {
            return $this->api_error_response('missing_parameters', 101, "لقد قمت بالتصويت أو أن صناديق الإقتراع قد أقفلت");
        }

        //The user can vote so return the cadidates list of his election region 'دائرة إنتخابية'
        $candidates = InternalElection::orderBy('id', 'desc')->first()->candidates()->where('election_state_id', $userInfo->ElectionStateId)->get();

        return response()->json([
            'max_to_rank' => $this->getMaxToRankNumber($candidates),
            'cadidates' => $candidates->shuffle()->map(function ($candidate) {
                return [
                    "name" => $candidate->name,
                    "id" => $candidate->id,
                    "photo" => url($candidate->image_name)
                ];
            })
        ], 200);
    }

    function getMaxToRankNumber($candidates)
    {
        if ($candidates->count() >= 5) {
            return 5;
        } else {
            return $candidates->count();
        }
    }

    function canIVoteFor($user, $candidates, $electionStateId)
    {
        $_candidates = InternalElection::orderBy('id', 'desc')->first()->candidates()->where('election_state_id', $electionStateId)->get()->pluck('id')->toArray();


        for ($i = 0; $i < count($candidates); $i++) {

            $result =  in_array($candidates[$i]['id'], $_candidates);
            if (!$result) {
                return false;
            }
        }

        return true;
    }


    function internalElectionVote(Request $request)
    {
        //Can I vote
        $userInfo = request('loggedInUser');
        $user = request('user');

        $result = $this->canIVote($user, $userInfo->ElectionStateId);

        if ($result !== 200) {
            return $this->api_error_response('missing_parameterss', 101, "لا يمكنك التصويت");
        }

        //Validate array of 5 items
        $validator = Validator::make($request->all(), [
            'votes' => 'required|array',
            'votes.*.rank' => 'required|distinct|between:1,5',
            'votes.*.id' => 'required|distinct'
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        //Validate the submitted
        $result = $this->canIVoteFor($user, request('votes'), $userInfo->ElectionStateId);

        if (!$result) {
            return $this->api_error_response('missing_parameters', 101,  "لا يمكنك التصويت لان مرشح أو أكثر ليس من ضمن دائرتك الإنتخابية");
        }

        //Current election id
        $id = InternalElection::orderBy('id', 'DESC')->first()->id;

        foreach (request('votes') as $candidate) {
            $user->votes()->attach($candidate['id'], ['rank' => $candidate['rank'], 'internal_election_id' => $id, 'weight' => 5 - $candidate['rank'] + 1]);
        }

        return response()->json([
            "message" => "لقد تم التصويت بنجاح شكراً"
        ], 201);
    }


    ///End of election module


    //Delete my account

    public function deleteAccountVerification()
    {
        $userInfo = request('loggedInUser');
        $user = request('user');

        //generate pin
        if ($user->phone_number == "+96171106394" || $user->phone_number == "96171106394" || $user->phone_number == "+96171106394" || $user->phone_number == '96176979532' || $user->phone_number == '+96176979532' || $user->phone_number == '+9613956719') {
            $user->verification_nb = '1111';
        } else {
            $user->verification_nb = $this->generatePIN();
        }

        $user->save();

        //send verification pin
        $fpmRepo = new FpmApisRepository();

        $fpmRepo->sendSMS([
            'phone_number' => $user->phone_number,
            'message' => 'Cde ' . $user->verification_nb,
        ]);

        return response()->json([
            "message" => "لقد ارسل لكم رمز التحقق"
        ], 200);
    }


    public function deleteAccount()
    {
        $userInfo = request('loggedInUser');
        $user = request('user');
        $pin   = request('pin');

        if ($user->verification_nb == $pin) {
            $user->token = null;
            $user->image = null;
            $user->phone_number = null;
            $user->member_id = null;
            $user->name = "Deleted Account";
            $user->player_id = null;
            $user->email = null;

            $user->save();
            $user->delete();

            return response()->json([
                "message" => "لقد تم حذف حسابكم بنجاح"
            ], 200);
        }

        return $this->api_error_response('wrong_pin', 101,  "رقم التحقق غير صحيح");
    }
}
