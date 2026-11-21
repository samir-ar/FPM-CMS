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
use App\V2\EventAttendance;
use App\V2\CheckinEvent;
use Illuminate\Database\QueryException;
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
use App\V2\CompetencyVacancy;
use App\V2\CompetencyProfile;
use App\V2\CompetencyNomination;
use App\V2\InternalElection;
use App\V2\InternalElectionCandidate;
use App\V2\InternalElectionPermission;
use App\V2\InternalElectionVoterViewer;
use App\V2\InternalElectionDistrictVotingSetting;
use App\V2\ElectionState;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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

    public function myProfile(Request $request)
    {
        $token = $request->user->token;

        return redirect('https://mobapp.twh-lb.org:444/datacenter/MobilePersonProfile.aspx?tokenid=' . $token);
    }

    public function refreshPermissions(Request $request)
    {
        $user = $request->user;

        // Opportunistic player_id sync: the login screen reads OneSignal's
        // pushSubscription.id too early (before OneSignal finishes
        // registering with APNs/FCM), so it's often empty at that point.
        // This endpoint is called every time the Home page loads for a
        // logged-in user — by then OneSignal has had much more time — so
        // it doubles as a reliable place to catch a player_id that wasn't
        // captured (or changed) at login.
        $playerId = $request->input('player_id');
        if (!empty($playerId) && $playerId !== $user->player_id) {
            $user->player_id = $playerId;
            $user->save();
        }

        // fpm_users (synced nightly from TWH) is the source of truth for
        // these fields — app_users' own columns are only a fallback for
        // orphaned accounts whose member_id has no matching roster row.
        $fpmUser = FpmUser::where('MemberId', $user->member_id)->first();

        return response()->json([
            'can_scan_checkin' => (bool) $user->can_scan_checkin,
            'district' => $fpmUser?->district ?? $user->district,
            'town' => $fpmUser?->town ?? $user->town,
            'sect' => $fpmUser?->sect ?? $user->sect,
            'sect_number' => $fpmUser?->sect_number ?? $user->sect_number,
            'date_of_birth' => $fpmUser?->date_of_birth ?? $user->date_of_birth,
            'gender' => $fpmUser?->gender ?? $user->gender,
            'last_unit_position' => $fpmUser?->LastUnitPosition,
            'last_position_unit_name' => $fpmUser?->LastPositionUnitName,
            'nashat_unit' => $fpmUser?->NashatUnit,
            'noufous_unit' => $fpmUser?->NoufousUnit,
            // Not restricted to published elections on purpose — a candidate
            // should be able to see and start reaching out to their voters
            // before the election is officially published, not just once it goes live.
            // Also true for a granted (non-candidate) voter-list viewer, so the
            // existing app-side flag/tile works for them with no app update needed.
            // Does respect voter_list_visible though — this flag controls the
            // Home-screen tile itself, which should disappear entirely (not just
            // error on tap) once an admin turns list visibility off.
            'is_internal_election_candidate' => InternalElectionCandidate::where('member_id', $user->member_id)
                ->whereHas('internalElection', fn($q) => $q->where('voter_list_visible', true))
                ->exists()
                || InternalElectionVoterViewer::where('member_id', $user->member_id)
                ->whereHas('internalElection', fn($q) => $q->where('voter_list_visible', true))
                ->exists(),
        ]);
    }

    public function updateProfileInfo(Request $request)
    {
        $user = $request->user;

        $validator = Validator::make($request->all(), [
            'district' => 'nullable|string|max:191',
            'town' => 'nullable|string|max:191',
            'sect' => 'nullable|string|max:191',
            'sect_number' => 'nullable|string|max:191',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string|in:ذكر,انثى',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $appUser = AppUser::find($user->id);
        foreach (['district', 'town', 'sect', 'sect_number', 'date_of_birth', 'gender'] as $field) {
            if ($request->has($field)) {
                $appUser->$field = $request->get($field);
            }
        }
        $appUser->save();

        return response()->json($appUser->getUser());
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
        $memos = $apiRepo->getMemosByGroups(is_array($groups) ? $groups : []);
        return response()->json($memos->map(function ($g) {
            return [
                'id'   => $g->id,
                'name' => $g->name,
                'date' => ($date = $g->date) ? Carbon::parse($date)->timestamp : null,
                'file' => $g->file ? Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/storage/images/memos/' . $g->file) : null,
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
        $user = $request->user;

        $validator = Validator::make($request->all(), [
            'news_id' => 'required|exists:news,id',
            'like' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }
        return response()->json($repo->likeNews($user, request('news_id'), request('like')));
    }



    // Replace the photo shown in the app (selfie / gallery). Stored in its own column so
    // the next login (which refreshes app_users.image from the party's photo) does not
    // undo it; the party's own photo (fpm_users.PersonImage) is never touched.
    public function uploadProfilePhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|string|max:900000',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $raw = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', (string) $request->input('image')), true);
        if ($raw === false || strlen($raw) < 100) {
            return $this->api_error_response('invalid_image', 101, 'الصورة غير صالحة');
        }

        $info = @getimagesizefromstring($raw);
        $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
        if (!$info || !in_array($info[2], $allowed, true) || ($info[0] * $info[1]) > 25000000) {
            return $this->api_error_response('invalid_image', 101, 'الصورة غير صالحة');
        }

        $src = @imagecreatefromstring($raw);
        if (!$src) {
            return $this->api_error_response('invalid_image', 101, 'الصورة غير صالحة');
        }

        // Re-encode on the server (never store the uploaded bytes as they came): a plain
        // JPEG, at most 512px on the long side, white background for transparent PNGs.
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, 512 / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $jpeg = null;
        foreach ([85, 75, 65, 55] as $quality) {
            ob_start();
            imagejpeg($dst, null, $quality);
            $jpeg = ob_get_clean();
            if (strlen($jpeg) <= 300000) break;
        }
        imagedestroy($src);
        imagedestroy($dst);

        $encoded = base64_encode($jpeg);
        \App\V2\AppUser::where('id', $request->user->id)->update(['custom_image' => $encoded]);

        return response()->json(['image' => $encoded]);
    }

    // Go back to the party's own photo.
    public function removeProfilePhoto(Request $request)
    {
        $id = $request->user->id;
        \App\V2\AppUser::where('id', $id)->update(['custom_image' => null]);

        return response()->json(['image' => \App\V2\AppUser::where('id', $id)->value('image')]);
    }

    public function likeEvent(Request $request, ApiRepository $repo)
    {
        $user = $request->user;

        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id',
            'like' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        return response()->json($repo->likeEvent($user, $request->input('event_id'), (bool) $request->input('like')));
    }

    public function shareEvent(Request $request, ApiRepository $repo)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        return response()->json($repo->shareEvent($request->input('event_id')));
    }

    public function shareNews(Request $request, ApiRepository $repo)
    {
        $user = $request->user;

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
                'image' => $row->image ? Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/' . 'storage/' . 'images/notification_images/' . $row->image) : Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/' . 'storage/' . 'images/placeholders/' . $placeholder),
            ];
        }));
    }


    public function getPreviousEventsWithPagination(Request $request, ApiRepository $repo)
    {
        if (!request('user') && $request->header('token')) {
            $resolved = \App\V2\AppUser::where('token', $request->header('token'))->first();
            if ($resolved) $request->merge(['user' => $resolved, 'groups' => $resolved->groups->pluck('GroupId')->toArray()]);
        }
        $events = $repo->getEventsPreviousByGroup(request('groups'), !request('user'));

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
                "thumbnail" => Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/' . 'storage/' . 'media/thumbnail/' . $q->thumbnail),
                "description" => $q->getTranslation('description', 'ar'),
                "medias" => $q->medias->map(function ($m) {

                    return array(
                        "name" => $m->getTranslation('name', 'ar'),
                        "type" => $m->type,
                        "is_youtube" => ($m->youtube) ? true : false,
                        "file" => Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/' . 'storage/' . 'media/' . $m->file_name),
                        "thumbnail" => Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/' . 'storage/' . 'media/thumbnail/' . $m->thumbnail),
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
                        "thumbnail" => Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/' . 'storage/' . 'media/' . $q->thumbnail),
                        "file" => Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/' . 'storage/' . 'media/' . $q->file_name),
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
        $user = $request->user;

        // Gather raw (un-hydrated) items first — hydration (S3 URLs, likes, etc.)
        // is deferred until after sorting/pagination so it only runs on the
        // handful of items actually returned by this page, not the whole feed.
        $merged = collect();

        if ($user) {
            foreach ($apiRepository->getPollsByGroupsRaw($user, request('groups')) as $poll) {
                $merged->push(['type' => 'POLL', 'created_at' => $poll->created_at, 'model' => $poll]);
            }
        } else {
            // a guest sees the polls an admin allowed for guests (members-only polls stay hidden)
            foreach ($apiRepository->getGuestPollsRaw(request('groups')) as $poll) {
                $merged->push(['type' => 'POLL', 'created_at' => $poll->created_at, 'model' => $poll]);
            }
        }

        // A guest (no login) only gets the events ticked "Public" in the event form, same as the
        // Events tab - before this the Home feed handed guests every event of the All Members group.
        foreach ($apiRepository->getEventsUpcomingByGroup(request('groups'), !$user)->get() as $event) {
            $merged->push(['type' => 'EVENT', 'created_at' => $event->created_at, 'model' => $event]);
        }

        foreach ($apiRepository->getNewsByGroups(request('groups'), !$user)->get() as $n) {
            $merged->push(['type' => 'NEWS', 'created_at' => $n->created_at, 'model' => $n]);
        }

        if ($merged->isEmpty()) {
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

        $merged = $merged->sortByDesc('created_at')->values();

        //Define how many items we want to be visible in each page
        $perPage = 6;

        //Get current page form url e.g. &page=6
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        //Slice the collection to get the items to display in current page
        $pageItems = $merged->slice(($currentPage - 1) * $perPage, $perPage)->values();

        // Batch-load like counts / liked-by-me status for just this page's news items
        $newsIdsOnPage = $pageItems->where('type', 'NEWS')->pluck('model.id');
        [$likesCounts, $likedByMe] = $apiRepository->getNewsLikesData($newsIdsOnPage, $user);

        // ...and the same for the events on this page (they have their own like table)
        $eventIdsOnPage = $pageItems->where('type', 'EVENT')->pluck('model.id');
        [$eventLikesCounts, $eventLikedByMe] = $apiRepository->getEventLikesData($eventIdsOnPage, $user);

        $currentPageSearchResults = $pageItems->map(function ($item) use ($apiRepository, $user, $likesCounts, $likedByMe, $eventLikesCounts, $eventLikedByMe) {
            return $apiRepository->getGenericInstance($item['model'], $item['type'], null, $user, $likesCounts, $likedByMe, $eventLikesCounts, $eventLikedByMe);
        })->all();

        //Create our paginator and pass it to the view
        $paginatedSearchResults = new LengthAwarePaginator(array_values($currentPageSearchResults), $merged->count(), $perPage, $currentPage,  ['path' => url('/api/v2/get-wall-feed')]);

        return response()->json($paginatedSearchResults);
    }


    public function getUpcomingEventsWithPagination(Request $request, ApiRepository $repo)
    {
        if (!request('user') && $request->header('token')) {
            $resolved = \App\V2\AppUser::where('token', $request->header('token'))->first();
            if ($resolved) $request->merge(['user' => $resolved, 'groups' => $resolved->groups->pluck('GroupId')->toArray()]);
        }
        $events = $repo->getEventsUpcomingByGroup(request('groups'), !request('user'));

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
        $settings = Setting::select(['android_version', 'ios_version', 'force_update_ios', 'force_update_android', 'update_title', 'update_message'])->first();
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

        if (!$this->meetsNationalCouncilMinBuild()) {
            $response['message'] = 'يرجى تحديث التطبيق للمشاركة في التصويت';
            $response['status'] = 404;
            return response()->json($response);
        }

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

    // Separate from the Internal Election's minimum build: each is raised on
    // its own once its own app release is live in both stores.
    private function meetsNationalCouncilMinBuild()
    {
        $min = Setting::first()->national_council_min_build ?? null;
        if (!$min) return true;

        return (int) request('build_number', 0) >= $min;
    }

    // Which phone a vote / answer came from (all optional, trimmed to the 191 characters the columns hold):
    // the app's random installation id, the phone's make / model / OS version, and the phone's own hardware id.
    private function deviceMeta($request): array
    {
        $clean = fn($key) => ($v = trim((string) $request->input($key))) !== '' ? mb_substr($v, 0, 191) : null;

        return ['device_id' => $clean('device_id'), 'device_model' => $clean('device_model'), 'hardware_id' => $clean('hardware_id')];
    }

    // "Already voted" is decided per MEMBER, not per app account: a member
    // holding two app_users rows must not be able to vote once from each.
    // The user_id arm only keeps rows written before member_id existed working.
    private function nationalCouncilVoteExists($pollId, $user)
    {
        return CouncilNationalPollVote::where('poll_id', $pollId)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (!empty($user->member_id)) {
                    $q->orWhere('member_id', $user->member_id);
                }
            })
            ->exists();
    }

    public function canIVoteToNationalCouncilPoll($request, ApiRepository $apiRepository, $withPolls = false)
    {
        $user = $request->user;

        //Check if there is a poll and the user is authorised to vote
        $poll = $apiRepository->getNationalCouncil($user);
        if (!$poll) return 404;

        // older app builds only know "a poll to vote in or not": a closed poll is simply not available to them
        if ($poll->isClosed()) return 404;

        //Check if the member has voted or not
        $voted = $this->nationalCouncilVoteExists($poll->id, $user);

        if (!$voted && $withPolls) return $poll;

        if (!$voted) return 200;

        return 401;
    }

    // EVERY published poll the member is permitted for, each with whether he can still vote in it, so the app
    // can list them all (like the Internal Election's list) instead of only the latest one.
    public function listNationalCouncilPolls(Request $request, ApiRepository $apiRepository)
    {
        if (!$this->meetsNationalCouncilMinBuild()) {
            return $this->api_error_response('app_update_required', 101, 'يرجى تحديث التطبيق للمشاركة في التصويت');
        }

        $user = $request->user;

        $polls = $apiRepository->getNationalCouncilPolls($user)->map(function ($poll) use ($user) {
            $voted = $this->nationalCouncilVoteExists($poll->id, $user);
            $closed = $poll->isClosed();

            return [
                'id' => $poll->id,
                'title' => $poll->title,
                'closes_at' => $poll->closesAtIso(),
                'closed' => $closed,
                // a closed poll stays in the list (marked closed) until it is unpublished, but cannot be opened
                'can_i_vote' => !$voted && !$closed,
                'voted' => $voted,
                'message' => $voted ? 'لقد قمت بالتصويت' : ($closed ? 'انتهى وقت التصويت' : 'يمكنك التصويت'),
            ];
        })->values();

        return response()->json(['polls' => $polls]);
    }

    public function getLatestNationalCouncilPoll(Request $request, ApiRepository $apiRepository)
    {
        if (!$this->meetsNationalCouncilMinBuild()) {
            return $this->api_error_response('app_update_required', 101, 'يرجى تحديث التطبيق للمشاركة في التصويت');
        }

        // A specific poll was asked for (the new app lists all polls): it must be one of the published polls this
        // member is permitted for, and he must not have voted in it yet. Without poll_id the old behaviour stays
        // (the latest one), so older app builds keep working.
        if ($request->filled('poll_id')) {
            $poll = $apiRepository->getNationalCouncilPolls($request->user)->firstWhere('id', (int) $request->input('poll_id'));
            if (!$poll || $this->nationalCouncilVoteExists($poll->id, $request->user)) {
                return $this->api_error_response('missing_parameters', 101, implode(', ', ['message' => 'لا يمكنك التصويت']));
            }
            if ($poll->isClosed()) {
                return $this->api_error_response('poll_closed', 101, 'انتهى وقت التصويت');
            }

            return response()->json($poll->format());
        }

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
        if (!$this->meetsNationalCouncilMinBuild()) {
            return $this->api_error_response('app_update_required', 101, 'يرجى تحديث التطبيق للمشاركة في التصويت');
        }

        $validator = Validator::make($request->all(), [
            'poll_id' => 'required|integer|exists:council_national_polls,id,deleted_at,NULL',
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|integer',
            'answers.*.answer_id' => 'required|integer',
            'device_id' => 'nullable|string|max:191',
            'device_model' => 'nullable|string|max:191',
            'hardware_id' => 'nullable|string|max:191',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $user = $request->user;

        // The poll being voted on must be one of the PUBLISHED polls this member is permitted for (the server
        // decides that, the request only names which of them): previously poll_id was taken from the request and
        // could point at any poll, including an unpublished one or one the member isn't in. A member can now be
        // in several published polls at once, so it is looked up in the whole list, not just the latest.
        $poll = $apiRepository->getNationalCouncilPolls($user)->firstWhere('id', (int) $request->poll_id);
        if (!$poll) {
            return $this->api_error_response('can_vote', 101, 'لا يمكنك التصويت');
        }

        // The closing time is enforced here, by the server, not by the app's countdown: after it nobody can vote.
        if ($poll->isClosed()) {
            return $this->api_error_response('poll_closed', 101, 'انتهى وقت التصويت');
        }

        if ($this->nationalCouncilVoteExists($poll->id, $user)) {
            return $this->api_error_response('can_vote', 101, 'لا يمكنك التصويت');
        }

        $permission = App\V2\CouncilNationalPollPermission::where('poll_id', $poll->id)->where('member_id', $user->member_id)->first();
        if (!$permission) {
            return $this->api_error_response('can_vote', 101, 'لا يمكنك التصويت');
        }
        $weight = $permission->vote_weight;

        // Every answer must belong to a question of THIS poll, and be one of
        // that question's own answers; a question may be answered only once.
        $answers = collect($request->answers);
        if ($answers->pluck('question_id')->unique()->count() !== $answers->count()) {
            return $this->api_error_response('missing_parameters', 101, 'لا يمكن الإجابة على السؤال أكثر من مرة');
        }

        $pollQuestionIds = $poll->questions()->pluck('id')->map(fn($id) => (int) $id)->all();
        foreach ($answers as $answer) {
            $questionId = (int) $answer['question_id'];
            $answerId = (int) $answer['answer_id'];

            $valid = in_array($questionId, $pollQuestionIds, true)
                && App\V2\AnswerCouncilNationalPoll::where('id', $answerId)->where('question_id', $questionId)->exists();

            if (!$valid) {
                return $this->api_error_response('missing_parameters', 101, 'الإجابة غير صالحة');
            }
        }

        // All-or-nothing, and backed by the unique (poll, question, member)
        // key: two parallel requests (double tap, retry, two sessions) can
        // both pass the checks above, but only one can ever insert.
        try {
            \DB::transaction(function () use ($answers, $poll, $user, $weight, $request) {
                foreach ($answers as $answer) {
                    CouncilNationalPollVote::create([
                        'user_id' => $user->id,
                        'member_id' => $user->member_id,
                        'poll_id' => $poll->id,
                        'question_id' => (int) $answer['question_id'],
                        'answer_id' => (int) $answer['answer_id'],
                        'weight' => $weight,
                        'device_id' => $request->input('device_id'),
                        'device_model' => $request->input('device_model') ? mb_substr((string) $request->input('device_model'), 0, 191) : null,
                        'hardware_id' => $request->input('hardware_id') ? mb_substr((string) $request->input('hardware_id'), 0, 191) : null,
                        'voted_at' => now(),
                    ]);
                }
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return $this->api_error_response('can_vote', 101, 'لا يمكنك التصويت');
            }
            throw $e;
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
        $polls = $repo->getPollsByGroups(request('user'), request('groups'), request('device_id'), request('hardware_id'));
        return response()->json($polls);
    }



    public function getPreviousPolls(Request $request, ApiRepository $repo)
    {
        //previous polls
        $polls = $repo->getPreviousPolls(request('user'), request('groups'), request('device_id'), request('hardware_id'));

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
            'answer_text' => 'nullable|string|max:5000',
            'device_id' => 'nullable|string|max:191',
            'device_model' => 'nullable|string|max:191',
            'hardware_id' => 'nullable|string|max:191',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        if (!request('user') && !request('device_id')) {
            return $this->api_error_response('missing_parameters', 101, 'device_id is required to vote as a guest');
        }

        // A guest is identified by the phone's hardware id, always: without one the vote is refused (an old app
        // build that does not send it has to update first).
        if (!request('user') && !trim((string) request('hardware_id'))) {
            return $this->api_error_response('app_update_required', 101, 'يرجى تحديث التطبيق للمشاركة في الاستطلاع');
        }

        $option = \App\PollOption::find(request('option_id'));
        $poll = \App\Poll::find($option->poll_id);

        // The poll stays visible (results-only) for 24h after it closes, but
        // voting itself must stop the moment expiry_date actually passes.
        if (!$poll || \Carbon\Carbon::parse($poll->expiry_date)->isPast()) {
            return $this->api_error_response('invalid_parameters', 101, 'This poll is closed');
        }

        if (!request('user') && !$poll->show_for_guest) {
            return $this->api_error_response('invalid_parameters', 101, 'هذا الاستطلاع للأعضاء فقط');
        }

        // One answer per MEMBER: if any app account carrying this member id (with or without a leading zero) has
        // already answered this poll, it is not answered again - not from the same account, not from another.
        if (request('user') && $this->memberHasAnsweredPoll(request('user'), $poll->id)) {
            return $this->api_error_response('already_voted', 101, 'لقد شاركت في هذا الاستطلاع مسبقاً');
        }

        // A guest counts once per PHONE: the hardware id is the physical identity of the phone (clearing the app's
        // data or reinstalling gives it a new device_id, never a new hardware_id).
        if (!request('user')) {
            $meta = $this->deviceMeta($request);
            $already = \App\V2\GuestPollVote::where('poll_id', $poll->id)
                ->where(function ($q) use ($meta) {
                    $q->where('hardware_id', $meta['hardware_id'])->orWhere('device_id', $meta['device_id']);
                })->exists();
            if ($already) {
                return $this->api_error_response('already_voted', 101, 'لقد شاركت في هذا الاستطلاع مسبقاً');
            }
        }

        // An option can let the member WRITE his own text: then the text is required, tags are stripped, and it is at
        // most 300 characters (the app stops typing at 300; a longer text can only come from a modified client).
        // An option that does not take text ignores any text that is sent. The text is never sent back to members.
        $answerText = null;
        if ($option->allows_text) {
            $answerText = trim(strip_tags((string) $request->input('answer_text', '')));
            if ($answerText === '') {
                return $this->api_error_response('missing_parameters', 101, 'يرجى كتابة إجابتك');
            }
            if (mb_strlen($answerText) > \App\V2\PollOption::TEXT_MAX) {
                return $this->api_error_response('invalid_parameters', 101, 'الإجابة طويلة جداً (' . \App\V2\PollOption::TEXT_MAX . ' حرفاً كحد أقصى)');
            }
        }

        if (request('user')) {
            try {
                $repo->userAnswerPoll(request('user'), request('option_id'), $this->deviceMeta($request) + ['answer_text' => $answerText]);
            } catch (\Illuminate\Database\QueryException $e) {
                // two accounts of one member answering at the same instant: the database rule lets only one through
                if ($e->getCode() === '23000') {
                    return $this->api_error_response('already_voted', 101, 'لقد شاركت في هذا الاستطلاع مسبقاً');
                }
                throw $e;
            }
        } else {
            $repo->guestAnswerPoll(request('device_id'), request('option_id'), $this->deviceMeta($request) + ['answer_text' => $answerText]);
        }

        return response()->json(['message' => 'Thank you for participating.']);
    }

    public function volunteers(Request $request)
    {
        $userId = $request->user?->id;

        $appliedVolunteerIds = $userId
            ? \Illuminate\Support\Facades\DB::table('users_volunteers')->where('user_id', $userId)->pluck('volunteer_id')->toArray()
            : [];

        return response()->json(Volunteer::with('fields')->where('is_active', true)->get()->map(function ($v) use ($appliedVolunteerIds) {
            // Every volunteer opportunity now has a fixed display language (set by
            // the admin), shown to every viewer regardless of their app's language.
            // Falls back to Arabic for any legacy rows saved before this field existed.
            $forcedLocale = in_array($v->display_language, ['en', 'ar']) ? $v->display_language : 'ar';

            return [
                'id' => $v->id,
                'title' => $v->getTranslation('title', $forcedLocale),
                'text' => $v->getTranslation('text', $forcedLocale),
                'language' => $forcedLocale,
                'image' => $v->image ? Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/' . 'storage/' . 'images/volunteers/' . $v->image) : null,
                'event_date' => $v->event_date ? strtotime($v->event_date) : null,
                // A member can only apply to one field per volunteer opportunity —
                // once applied (to any field), the whole opportunity is "done" for them.
                'already_applied' => in_array($v->id, $appliedVolunteerIds),
                'fields' => $v->fields->map(function ($f) use ($forcedLocale) {
                    return [
                        'id' => $f->id,
                        'name' => $f->getTranslation('name', $forcedLocale),
                        'needed_count' => $f->needed_count,
                        'filled_count' => \Illuminate\Support\Facades\DB::table('users_volunteers')->where('volunteer_field_id', $f->id)->count(),
                    ];
                }),
            ];
        }));
    }

    public function getPreviousEvents(Request $request, ApiRepository $repo)
    {
        $events = $repo->getEventsPreviousByGroup(request('groups'), !request('user'))->orderByDesc('created_at')->limit(100)->get();

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
        $events = $repo->getEventsUpcomingByGroup(request('groups'), !request('user'))->orderByDesc('created_at')->get();

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

    public function getCheckinEvents(Request $request)
    {
        $user = $request->user;

        if (!$user || !$user->can_scan_checkin) {
            return $this->api_error_response('not_authorized', 103, 'You are not authorized to scan check-ins.');
        }

        $events = CheckinEvent::where('is_active', true)
            ->orderByDesc('event_date')
            ->get(['id', 'name', 'location', 'event_date']);

        // Open National Council meetings are scannable too. Older app builds
        // only read 'data' (the events) and ignore this key.
        $meetings = \App\V2\CouncilMeeting::where('is_active', true)
            ->orderByDesc('id')
            ->get(['id', 'title', 'meeting_date'])
            ->map(fn($m) => [
                'id' => $m->id,
                'name' => $m->title,
                'event_date' => optional($m->meeting_date)->format('Y-m-d'),
            ]);

        return response()->json(['data' => $events, 'council_meetings' => $meetings]);
    }

    // QR scan for a National Council meeting. Same people allowed to scan as
    // for events (can_scan_checkin); only members on the council roster can be
    // marked present; attendance only feeds the quorum, never voting.
    public function councilMeetingCheckIn(Request $request)
    {
        $user = $request->user;

        if (!$user || !$user->can_scan_checkin) {
            return $this->api_error_response('not_authorized', 103, 'You are not authorized to scan check-ins.');
        }

        $validator = Validator::make($request->all(), [
            'meeting_id' => 'required|integer|exists:council_meetings,id,is_active,1',
            'member_id' => 'required|digits_between:1,20',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $meeting = \App\V2\CouncilMeeting::find($request->input('meeting_id'));
        $memberId = (string) $request->input('member_id');

        $rosterMember = \App\V2\CouncilRosterMember::active()->where('member_id', $memberId)->first();
        if (!$rosterMember) {
            return $this->api_error_response('not_in_council', 101, 'هذا الشخص ليس من أعضاء المجلس الوطني');
        }

        $memberName = \App\Support\CouncilMemberLookup::displayName(
            $memberId,
            \DB::table('fpm_users')->where('MemberId', $memberId)->value('UserFullName')
        );
        $position = $rosterMember->role ?: null;

        $already = false;
        try {
            \App\V2\CouncilMeetingAttendance::create([
                'meeting_id' => $meeting->id,
                'member_id' => $memberId,
                'weight' => $rosterMember->weight,
                'checked_in_at' => now(),
                'scanned_by_app_user_id' => $user->id,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $already = true;
        }

        $stats = $meeting->stats();

        return response()->json([
            'already_checked_in' => $already,
            'member_name' => $memberName,
            'position' => $position,
            'weight' => (int) $rosterMember->weight,
            'message' => $already
                ? $memberName . ' is already checked in to this meeting.'
                : $memberName . ' checked in successfully.',
            'present_weight' => $stats['present_weight'],
            'total_weight' => $stats['total_weight'],
            'majority_reached' => $stats['majority_reached'],
            'two_thirds_reached' => $stats['two_thirds_reached'],
        ]);
    }

    public function checkInMember(Request $request)
    {
        $user = $request->user;

        if (!$user || !$user->can_scan_checkin) {
            return $this->api_error_response('not_authorized', 103, 'You are not authorized to scan check-ins.');
        }

        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:checkin_events,id,is_active,1',
            'member_id' => 'required|integer|exists:fpm_users,MemberId',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $member = \DB::table('fpm_users')->where('MemberId', request('member_id'))
            ->first(['UserFullName', 'LastUnitPosition']);
        $memberName = $member->UserFullName ?? null;
        $position = $member->LastUnitPosition ?: null;

        try {
            EventAttendance::create([
                'event_id' => request('event_id'),
                'member_id' => request('member_id'),
                'checked_in_by_app_user_id' => $user->id,
                'checked_in_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'already_checked_in' => true,
                    'member_name' => $memberName,
                    'position' => $position,
                    'message' => $memberName . ' is already checked in to this event.',
                ]);
            }
            throw $e;
        }

        return response()->json([
            'already_checked_in' => false,
            'member_name' => $memberName,
            'position' => $position,
            'message' => $memberName . ' checked in successfully.',
        ]);
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
            'volunteer_field_id' => 'nullable|exists:volunteer_fields,id',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $isActive = Volunteer::where('id', request('volunteer_id'))->value('is_active');
        if (!$isActive) {
            return $this->api_error_response('invalid_parameters', 101, 'This volunteer opportunity is no longer active');
        }

        // If a field was specified, make sure it actually belongs to this volunteer opportunity.
        if (request('volunteer_field_id')) {
            $belongs = \App\V2\VolunteerField::where('id', request('volunteer_field_id'))
                ->where('volunteer_id', request('volunteer_id'))
                ->exists();

            if (!$belongs) {
                return $this->api_error_response('invalid_parameters', 101, 'This field does not belong to the selected volunteer opportunity');
            }
        }

        // A member can only apply to one field per volunteer opportunity — reject a
        // second application to the same opportunity, whichever field it's for.
        $alreadyApplied = \Illuminate\Support\Facades\DB::table('users_volunteers')
            ->where('user_id', request('user')->id)
            ->where('volunteer_id', request('volunteer_id'))
            ->exists();

        if ($alreadyApplied) {
            return $this->api_error_response('invalid_parameters', 101, 'You have already applied to this opportunity');
        }

        $repo->saveVolunteer(request('user'), request('volunteer_id'), request('volunteer_field_id'));

        return response()->json(['message' => 'Thank you for applying.']);
    }


    // Serves a real party member's photo (stored as raw bytes in fpm_users.PersonImage)
    // as a normal image response, so it can be used as an ordinary image URL.
    public function getMemberPhoto($memberId)
    {
        // Public (no login) because representatives' and election candidates'
        // photos have to show in the app for everyone - so it may only serve
        // the photo of someone who is actually a representative or a candidate.
        // Before this any member id returned that member's real photo to anyone.
        $isPollCandidate = \DB::table('answer_council_national_polls')->where('member_id', (string) $memberId)->exists();

        $isPublicFace = \DB::table('persons')->where('member_id', $memberId)->exists()
            || \DB::table('internal_election_candidates')->where('member_id', $memberId)->exists()
            || $isPollCandidate;

        if (!$isPublicFace) {
            abort(404);
        }

        // A National Council poll candidate shows his Representatives picture when he has one (usually the newer
        // photo), like the council pages in the CMS; otherwise the member-list photo below.
        if ($isPollCandidate && ($repUrl = \App\Support\CouncilMemberLookup::repImageUrl($memberId))) {
            return redirect()->away($repUrl)->header('Cache-Control', 'public, max-age=3600');
        }

        $bytes =\DB::table('fpm_users')->where('MemberId', $memberId)->value('PersonImage');

        if (empty($bytes)) {
            abort(404);
        }

        return response($bytes, 200)
            ->header('Content-Type', 'image/jpeg')
            ->header('Content-Length', (string) strlen($bytes))
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function representativePersonImageUrl($p, $membersWithPhotos)
    {
        if (!empty($p->member_id) && in_array($p->member_id, $membersWithPhotos)) {
            return url('/api/v2/member-photo/' . $p->member_id);
        }

        return !empty($p->image)
            ? Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/' . 'storage/' . 'images/representatives/' . rawurlencode($p->image))
            : null;
    }

    public function getRepresentatives(Request $request, ApiRepository $repo)
    {
        // $validator = Validator::make($request->all(), [
        //     'type' => 'required',
        // ]);

        $categories = DynamicRepresentative::with(['persons' => function ($q) {
            $q->orderBy('order', 'asc');
        }])->orderBy('order', 'asc')->get();

        $memberIds = $categories->flatMap(fn($r) => $r->persons)
            ->pluck('member_id')->filter()->unique()->values();

        $membersWithPhotos = $memberIds->isEmpty() ? [] : \DB::table('fpm_users')
            ->whereIn('MemberId', $memberIds)
            ->whereNotNull('PersonImage')
            ->where('PersonImage', '!=', '')
            ->pluck('MemberId')->all();

        $sections = $categories->map(function ($r) use ($membersWithPhotos) {

            return [
                'id' => $r->id,
                'title' => $r->title,
                'text' => $r->text,
                'persons' => $r->persons->map(function ($p) use ($membersWithPhotos) {
                    return [
                        "id" => $p->id,
                        "type" => $p->type,
                        "name" => $p->name,
                        "category" => $p->category,
                        "image" => $this->representativePersonImageUrl($p, $membersWithPhotos),
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
                'file' => ($g->file) ? Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/' . 'storage/' . 'images/laws/' . $g->file) : null,
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
                ? [Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/' . 'storage/' . 'images/content/' . $content->image)]
                : [],
            'text' => $content ? (App::getLocale() == 'en' ? $content->text : $content->text_ar) : '',
            'email' => $mediaObj->email ?? null,
            'facebook' => $mediaObj->facebook ?? null,
            'instagram' => $mediaObj->instagram ?? null,
            'youtube' => $mediaObj->youtube ?? null,
            'linkedIn' => $mediaObj->linkedIn ?? null,
            'twitter' => $mediaObj->twitter ?? null,
            'phone_number' => $mediaObj->phone ?? '96103067387',
            'mobile_number' => $mediaObj->mobile ?? null,
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

    public function getPoliticalWork(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|in:takattol,haya_siyasiya,majlis_siyasi',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $docs = \App\V2\PoliticalWorkDocument::where('category', request('category'))
            ->orderBy('document_date', 'desc')
            ->get()
            ->map(function ($d) {
                return [
                    'id'       => $d->id,
                    'title'    => $d->title,
                    'date'     => optional($d->document_date)->toDateString(),
                    'file_url' => $d->fileUrl(),
                ];
            });

        return response()->json($docs);
    }

    public function getLegislativeCategories(Request $request)
    {
        $categories = \App\V2\LegislativeDocCategory::orderBy('order')->get()
            ->map(fn($c) => [
                'id'   => $c->id,
                'name' => $c->getTranslation('name', 'ar'),
            ]);
        return response()->json($categories);
    }

    public function getLegislativeSubcategories(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:legislative_doc_categories,id',
        ]);
        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }
        $subcategories = \App\V2\LegislativeDocSubcategory::where('category_id', $request->category_id)
            ->orderBy('order')->get()
            ->map(fn($s) => [
                'id'   => $s->id,
                'name' => $s->getTranslation('name', 'ar'),
            ]);
        return response()->json($subcategories);
    }

    public function getLegislativeDocs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:legislative_doc_categories,id',
            'subcategory_id' => 'nullable|exists:legislative_doc_subcategories,id',
        ]);
        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $query = \App\V2\LegislativeDoc::where('category_id', $request->category_id);

        // A doc without a subcategory shows directly on the main category tab;
        // picking a specific subcategory narrows down to just that subcategory's docs.
        if ($request->subcategory_id) {
            $query->where('subcategory_id', $request->subcategory_id);
        } else {
            $query->whereNull('subcategory_id');
        }

        $docs = $query->orderByRaw('date IS NULL')->orderBy('date', 'desc')
            ->orderBy('order')->get()
            ->map(fn($d) => [
                'id'       => $d->id,
                'title'    => $d->title,
                'date'     => $d->date,
                'file_url' => Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/storage/legislative_docs/' . $d->file_name),
            ]);
        return response()->json($docs);
    }

    public function getNationalPlans(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tab' => 'required|in:iqtisad,kahraba,maa,muhajareen,lamarkaziya',
        ]);
        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }
        $docs = \App\V2\NationalPlan::where('tab', $request->tab)
            ->orderByRaw('date IS NULL')->orderBy('date', 'desc')
            ->orderBy('order')->get()
            ->map(fn($d) => [
                'id'       => $d->id,
                'title'    => $d->title,
                'date'     => $d->date,
                'file_url' => Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/storage/national_plans/' . $d->file_name),
            ]);
        return response()->json($docs);
    }

    public function getInternalOrg(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tab' => 'required|in:nizham_dakhili,tawjihat_tatbiqiya',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $doc = \App\V2\InternalOrgDocument::where('tab', $request->tab)->first();

        return response()->json([
            'tab'     => $request->tab,
            'content' => optional($doc)->content,
        ]);
    }

    public function getCompetencyStaticPage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:terms,faq',
            'language' => 'nullable|in:ar,en',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        // the terms exist in Arabic (default) and English; the member switches with one button in the app
        $language = $request->input('language') === 'en' ? 'en' : 'ar';
        $category = $request->type === 'terms' ? ($language === 'en' ? 'competency-terms-en' : 'competency-terms') : 'competency-faq';
        $content = Content::where('category', $category)->first();

        return response()->json([
            'type'     => $request->type,
            'language' => $language,
            'content'  => optional($content)->text,
        ]);
    }

    // بلديات / مخاتير lists hand back real names and phone numbers in bulk: like the district voters list, every pull
    // is counted per MEMBER (a flat cap that only a script could hit - a person browsing the screens makes a handful
    // of calls) and written to the log (who, what, which قضاء / village / municipality, from which IP), so a slow
    // scraper pacing itself under the cap still leaves a trail. Returns a 429 response when the cap is reached.
    private function guardDirectoryPull(Request $request, string $endpoint)
    {
        $user = request('user');
        $memberId = $user->member_id ?? 'unknown';
        $hourKey = 'directory-pulls:' . $memberId;
        $dayKey = 'directory-pulls-day:' . $memberId;
        $context = ['member_id' => $memberId, 'endpoint' => $endpoint, 'ip' => $request->ip()];

        // the daily cap: a person never gets near it, a patient scraper (who stays under the hourly cap all day)
        // is held to a small number of lists per day
        if (RateLimiter::tooManyAttempts($dayKey, 150)) {
            Log::warning('directory_pull_daily_cap', $context);
            return $this->api_error_response('too_many_requests', 429, 'لقد بلغت الحد اليومي لتصفّح هذه الصفحات، يرجى المحاولة غداً.', 429);
        }

        if (RateLimiter::tooManyAttempts($hourKey, 50)) {
            Log::warning('directory_pull_rate_limited', $context);
            return $this->api_error_response('too_many_requests', 429, 'لقد تصفّحت عدداً كبيراً من الصفحات خلال الساعة الأخيرة، يرجى المحاولة مجدداً بعد قليل.', 429);
        }

        RateLimiter::hit($hourKey, 3600);
        $today = RateLimiter::hit($dayKey, 86400);

        // the alert: one warning, the moment a member passes 100 pulls in a day (well before the daily cap), so the
        // log shows WHO is pulling that much without waiting for them to hit the cap
        if ($today === 100) {
            Log::warning('directory_pull_heavy_use', $context + ['pulls_today' => $today]);
        }

        Log::info('directory_pull', $context + [
            'qada' => $request->input('qada'),
            'village' => $request->input('village'),
            'municipality' => $request->input('municipality'),
        ]);

        return null;
    }

    // Returns mukhtars for a given قضاء + village
    public function getMukhtars(Request $request)
    {
        if ($limited = $this->guardDirectoryPull($request, 'getMukhtars')) {
            return $limited;
        }

        $validator = Validator::make($request->all(), [
            'qada'    => 'required|string',
            'village' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $members = \App\V2\Mukhtar::where('qada', $request->qada)
            ->where('village_name', $request->village)
            ->orderByRaw("CASE position WHEN 'مختار' THEN 1 WHEN 'عضو اختياري' THEN 2 ELSE 3 END")
            ->orderBy('votes', 'desc')
            ->get()
            ->map(fn($m) => [
                'id'           => $m->id,
                'full_name'    => $m->full_name,
                'neighborhood' => $m->neighborhood,
                'position'     => $m->position,
                'phone'        => $m->phone,
                'is_mountasib' => (bool) $m->is_mountasib,
            ]);

        return response()->json($members);
    }

    // Returns distinct villages for قضاء (mukhtars)
    public function getMukhtarVillages(Request $request)
    {
        if ($limited = $this->guardDirectoryPull($request, 'getMukhtarVillages')) {
            return $limited;
        }

        $validator = Validator::make($request->all(), [
            'qada' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $list = \App\V2\Mukhtar::where('qada', $request->qada)
            ->select('village_name')
            ->groupBy('village_name')
            ->orderBy('village_name')
            ->pluck('village_name');

        return response()->json($list);
    }

    // Returns distinct municipality names for a given قضاء
    public function getMunicipalities(Request $request)
    {
        if ($limited = $this->guardDirectoryPull($request, 'getMunicipalities')) {
            return $limited;
        }

        $validator = Validator::make($request->all(), [
            'qada' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $list = \App\V2\MunicipalityMember::where('qada', $request->qada)
            ->select('municipality_name')
            ->groupBy('municipality_name')
            ->orderBy('municipality_name')
            ->pluck('municipality_name');

        return response()->json($list);
    }

    // Returns all members for a given قضاء + municipality
    public function getMunicipalityMembers(Request $request)
    {
        if ($limited = $this->guardDirectoryPull($request, 'getMunicipalityMembers')) {
            return $limited;
        }

        $validator = Validator::make($request->all(), [
            'qada'     => 'required|string',
            'municipality' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $members = \App\V2\MunicipalityMember::where('qada', $request->qada)
            ->where('municipality_name', $request->municipality)
            ->orderByRaw("CASE position WHEN 'رئيس' THEN 1 WHEN 'نائب رئيس' THEN 2 WHEN 'عضو' THEN 3 ELSE 4 END")
            ->orderBy('votes', 'desc')
            ->get()
            ->map(fn($m) => [
                'id'           => $m->id,
                'full_name'    => $m->full_name,
                'village_name' => $m->village_name,
                'position'     => $m->position,
                'phone'        => $m->phone,
                'is_mountasib' => (bool) $m->is_mountasib,
            ]);

        return response()->json($members);
    }

    private const COMPETENCY_MAX_NOMINATIONS_PER_VACANCY = 5;

    // Returns vacancies currently open for nomination
    public function getCompetencyVacancies(Request $request)
    {
        $resolvedUser = $request->user;

        $now = now();
        $type = in_array($request->get('type'), ['specific', 'general']) ? $request->get('type') : 'specific';

        $vacancies = CompetencyVacancy::where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->where('is_active', true)
            ->where('type', $type)
            ->orderByDesc('start_date')
            ->get()
            ->map(function ($v) use ($resolvedUser) {
                $myNominationsCount = $resolvedUser
                    ? $v->nominations()->where('submitted_by_user_id', $resolvedUser->id)->count()
                    : 0;

                return [
                    'id'                 => $v->id,
                    'title'              => $v->title,
                    'description'        => $v->description,
                    'start_date'         => $v->start_date->timestamp,
                    'end_date'           => $v->end_date->timestamp,
                    'my_nominations_count' => $myNominationsCount,
                    'max_nominations'    => self::COMPETENCY_MAX_NOMINATIONS_PER_VACANCY,
                    'requires_nomination_category' => (bool) $v->requires_nomination_category,
                ];
            });

        return response()->json($vacancies);
    }

    // Returns the requesting user's known profile fields, for pre-filling a self-nomination form
    public function getCompetencyProfile(Request $request)
    {
        $user = $request->user;

        $profile = \App\V2\CompetencyProfile::where('user_id', $user->id)->first();

        return response()->json([
            'full_name'         => $user->name,
            'phone'             => $user->phone_number,
            'district'          => optional($profile)->district,
            'town'              => optional($profile)->town,
            'civil_record'      => optional($profile)->civil_record,
            'profession'        => optional($profile)->profession,
            'party_role'        => optional($profile)->party_role,
            'education_level'   => optional($profile)->education_level,
            'specialization'    => optional($profile)->specialization,
        ]);
    }

    // Returns all electoral districts, for the القضاء dropdown
    public function getElectoralDistricts(Request $request)
    {
        $districts = \App\V2\ElectoralDistrict::orderBy('name')->get(['id', 'name']);

        return response()->json($districts);
    }

    // Returns towns belonging to the given electoral district, for the البلدة dropdown
    public function getTowns(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'electoral_district_id' => 'required|integer|exists:electoral_districts,id',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $towns = \App\V2\Town::where('electoral_district_id', $request->electoral_district_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($towns);
    }

    // Submits a self- or other-nomination for an open vacancy
    public function submitCompetencyNomination(Request $request)
    {
        $user = $request->user;

        $validator = Validator::make($request->all(), [
            'vacancy_id'                => 'required|integer|exists:competency_vacancies,id',
            'nomination_type'           => 'required|in:self,other',
            'full_name'                 => 'required_if:nomination_type,other|nullable|string|max:191',
            'district'                  => 'required_if:nomination_type,other|nullable|string|max:191',
            'town'                      => 'required_if:nomination_type,other|nullable|string|max:191',
            'civil_record'              => 'nullable|string|max:191',
            'phone'                     => 'required_if:nomination_type,other|nullable|string|max:191',
            'profession'                => 'required|string|max:191',
            'party_role'                => 'nullable|string|max:191',
            'education_level'           => 'required|string|max:191',
            'specialization'            => 'required|string|max:191',
            'nominee_relation'          => 'required_if:nomination_type,other|nullable|string|max:191',
            'nomination_reason'         => 'required|string|max:2000',
            'nominee_aware'             => 'required_if:nomination_type,other|nullable|boolean',
            'responsibility_confirmed'  => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $vacancy = CompetencyVacancy::findOrFail($request->vacancy_id);
        $now = now();

        // Only vacancies with this explicitly enabled in the CMS ask for a
        // "نوع الترشيح" category — everything else stays exactly as before.
        if ($vacancy->requires_nomination_category) {
            $categoryValidator = Validator::make($request->all(), [
                'nomination_category' => 'required|in:سياسي,تنفيذي/اداري,مناطقي',
            ]);
            if ($categoryValidator->fails()) {
                return $this->api_error_response('missing_parameters', 101, implode(', ', $categoryValidator->messages()->all()));
            }
        }

        if ($now->lt($vacancy->start_date) || $now->gt($vacancy->end_date)) {
            return $this->api_error_response('vacancy_closed', 102, 'الترشيح لهذا المنصب مقفل حالياً');
        }

        $existingCount = $vacancy->nominations()->where('submitted_by_user_id', $user->id)->count();

        if ($existingCount >= self::COMPETENCY_MAX_NOMINATIONS_PER_VACANCY) {
            return $this->api_error_response('max_nominations_reached', 103, 'لقد وصلت الى الحد الأقصى من الترشيحات لهذا المنصب');
        }

        if ($request->nomination_type === 'self') {
            \App\V2\CompetencyProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'district'          => $request->district,
                    'town'              => $request->town,
                    'civil_record'      => $request->civil_record,
                    'profession'        => $request->profession,
                    'party_role'        => $request->party_role,
                    'education_level'   => $request->education_level,
                    'specialization'    => $request->specialization,
                ]
            );
        }

        $nomination = CompetencyNomination::create([
            'vacancy_id'                => $vacancy->id,
            'submitted_by_user_id'      => $user->id,
            'nomination_type'           => $request->nomination_type,
            'nomination_category'       => $vacancy->requires_nomination_category ? $request->nomination_category : null,
            'full_name'                 => $request->full_name ?: $user->name,
            'district'                  => $request->district,
            'town'                      => $request->town,
            'civil_record'              => $request->civil_record,
            'phone'                     => $request->phone ?: $user->phone_number,
            'profession'                => $request->profession,
            'party_role'                => $request->party_role,
            'education_level'           => $request->education_level,
            'specialization'            => $request->specialization,
            'nominee_relation'          => $request->nominee_relation,
            'nomination_reason'         => $request->nomination_reason,
            'nominee_aware'             => $request->nomination_type === 'other' ? $request->boolean('nominee_aware') : null,
            'responsibility_confirmed'  => true,
        ]);

        return response()->json(['success' => true, 'id' => $nomination->id]);
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

    // Resolves a member's voting district from their synced TWH `district`
    // text (e.g. "المتن الشمالي") to an `election_states.id`. `ElectionStateId`
    // is not used here — it's unpopulated (0) for every member in this
    // database, while `district` is TWH-synced and reliably current.
    function resolveElectionStateId($userInfo)
    {
        if (!$userInfo || !$userInfo->district) {
            return null;
        }

        return ElectionState::where('name', $userInfo->district)->value('id');
    }

    // Every currently-published election — a voter can be eligible for
    // more than one at once, so callers must always work with the full
    // list and a specific election, never assume there's just "the" one.
    function getActiveElections()
    {
        return InternalElection::where('is_active', true)->orderBy('id', 'desc')->get();
    }

    // Internal Election access (voting + the candidate district-voters
    // list) can be gated behind a minimum app build number, set in Admin ->
    // App Versions -> "Internal Election Minimum Build Number". Android and
    // iOS share one monotonically-increasing build-number sequence in this
    // project, so a single configured number works for both platforms
    // without needing to know which OS the request came from. Leave the
    // setting blank for no restriction (the default). A client too old to
    // send build_number at all is treated as "too old" once a minimum is
    // configured, same as an explicitly-old build.
    function meetsInternalElectionMinBuild()
    {
        $min = Setting::first()->internal_election_min_build ?? null;
        if (!$min) return true;

        $build = (int) request('build_number', 0);
        return $build >= $min;
    }

    // Returns one row per currently-published election, each annotated
    // with whether this specific voter can vote in it right now and why
    // not if not — the app shows this as a list to choose from.
    function canIVoteEndpoint(Request $request)
    {
        $userInfo = request('loggedInUser');
        $user = request('user');
        $electionStateId = $this->resolveElectionStateId($userInfo);
        $tooOld = !$this->meetsInternalElectionMinBuild();

        $elections = $this->getActiveElections()->map(function ($election) use ($user, $electionStateId, $tooOld) {
            if ($tooOld) {
                return [
                    'id' => $election->id,
                    'title' => $election->title,
                    'election_date' => $election->created_at ? $election->created_at->format('Y-m-d H:i') : null,
                    'closes_at' => $election->closes_at ? Carbon::parse($election->closes_at)->toIso8601String() : null,
                    'requires_selfie' => (bool) $election->requires_selfie,
                    'can_i_vote' => false,
                    'message' => 'يرجى تحديث التطبيق للمشاركة في الانتخابات الداخلية',
                    '_hide' => false,
                ];
            }

            $result = $this->canIVote($user, $electionStateId, $election);

            $canVote = $result === 200;
            $message = null;

            if ($result === 500) {
                $message = 'لا مرشحين عن دائرتك الإنتخابية';
            } elseif ($result === 401) {
                $message = 'لقد قمت بالتصويت';
            } elseif ($result === false) {
                $message = 'أقفل باب التصويت';
            } elseif ($result === 405) {
                $message = 'تم تحديد نتيجة دائرتك مسبقاً';
            }

            return [
                'id' => $election->id,
                'title' => $election->title,
                'election_date' => $election->created_at ? $election->created_at->format('Y-m-d H:i') : null,
                'closes_at' => $election->closes_at ? Carbon::parse($election->closes_at)->toIso8601String() : null,
                'requires_selfie' => (bool) $election->requires_selfie,
                'can_i_vote' => $canVote,
                'message' => $message,
                // Result 403 = not Allowed_to_vote / no district match at all —
                // not eligible in any way, so it's dropped from the list
                // entirely below rather than shown as a disabled tile.
                '_hide' => $result === 403,
            ];
        })->filter(fn ($e) => !$e['_hide'])
            ->map(fn ($e) => collect($e)->except('_hide')->all())
            ->values();

        return response()->json(['elections' => $elections]);
    }

    //Check if the user can vote in one specific election
    function canIVote($user, $electionStateId, $election)
    {
        if (!$election) {
            //Election doesn't exist, or isn't currently published
            return -1;
        }

        if ($election->closes_at && Carbon::parse($election->closes_at)->isPast()) {
            //closing time has passed — treat the same as manually closed
            return false;
        }


        // Per-election allowlist, uploaded by admin staff for this specific
        // election (internal_election_permissions) — required for everyone
        // in addition to (not instead of) the district match below. Replaces
        // the old global Allowed_to_vote column (removed 2026-09-29).
        //
        // member_id is matched tolerating a leading-zero difference: the
        // permitted-voters Excel lists get built from various external
        // sources/exports whose ID formatting doesn't reliably agree with
        // app_users.member_id (confirmed 2026-11-05 — 736 existing
        // permission rows across elections differed from the real member's
        // app account by exactly one leading zero, silently locking those
        // members out with no visible error). Checking both padded and
        // stripped variants here fixes it for every already-uploaded list
        // and every future one, without needing to clean the underlying
        // data.
        $isAllowed = InternalElectionPermission::where('election_id', $election->id)
            ->where(function ($q) use ($user) {
                $q->where('member_id', $user->member_id)
                    ->orWhere('member_id', '0' . $user->member_id)
                    ->orWhere('member_id', ltrim($user->member_id, '0'));
            })
            ->exists();

        if (!$isAllowed) {
            return 403;
        }

        // Per-district voting toggle, set by admin staff for districts
        // whose outcome is already agreed/uncontested (1-3 candidates with
        // no real contest, or an agreed رديف among exactly 4) - no row
        // means voting is on (the default, normal contested-race case).
        $votingEnabled = InternalElectionDistrictVotingSetting::where('election_id', $election->id)
            ->where('election_state_id', $electionStateId)
            ->value('voting_enabled');

        if ($votingEnabled === false) {
            return 405;
        }

        //Check if there are condidates
        $candidatesCount = $election->candidates()->whereHas('electionStates', function($q) use ($electionStateId){ $q->where('election_states.id', $electionStateId); })->count();

        if ($candidatesCount == 0) {
            return 500;
        }

        if (!$this->memberHasVotedInElection($user, $election->id)) {
            //this member has not voted yet
            return 200;
        } else {
            //this member has already voted
            return 401;
        }
    }

    // True when this member (through any app account carrying their member id, with or without a leading zero)
    // already answered the poll.
    private function memberHasAnsweredPoll($user, $pollId): bool
    {
        $stripped = ltrim((string) $user->member_id, '0');
        $ids = array_values(array_unique(array_filter([(string) $user->member_id, $stripped, '0' . $stripped], fn ($v) => $v !== '' && $v !== '0')));

        return \DB::table('users_polls')
            ->join('app_users', 'app_users.id', '=', 'users_polls.app_user_id')
            ->where('users_polls.poll_id', $pollId)
            ->where(function ($q) use ($user, $ids) {
                $q->where('users_polls.app_user_id', $user->id);
                if ($ids) {
                    $q->orWhereIn('app_users.member_id', $ids);
                }
            })
            ->exists();
    }

    // True when this member already has a vote in the election - through ANY app account that carries their member
    // id (with or without a leading zero), not only the account that is logged in now. A vote is saved against an
    // app account, so two accounts of one member could otherwise each vote once.
    private function memberHasVotedInElection($user, $electionId): bool
    {
        $stripped = ltrim((string) $user->member_id, '0');
        $ids = array_values(array_unique(array_filter([(string) $user->member_id, $stripped, '0' . $stripped], fn ($v) => $v !== '' && $v !== '0')));

        return \DB::table('internal_election_votes')
            ->join('app_users', 'app_users.id', '=', 'internal_election_votes.user_id')
            ->where('internal_election_votes.internal_election_id', $electionId)
            ->where(function ($q) use ($user, $ids) {
                $q->where('internal_election_votes.user_id', $user->id);
                if ($ids) {
                    $q->orWhereIn('app_users.member_id', $ids);
                }
            })
            ->exists();
    }


    //get Candidates of a specific internal election
    function getInternalElectionCandidates(Request $request)
    {
        if (!$this->meetsInternalElectionMinBuild()) {
            return $this->api_error_response('app_update_required', 101, 'يرجى تحديث التطبيق للمشاركة في الانتخابات الداخلية');
        }

        $user = request('user');
        $userInfo = request('loggedInUser');
        $electionStateId = $this->resolveElectionStateId($userInfo);


        if (!$user) {
            return $this->api_error_response('missing_parameters', 101, "user not found");
        }

        $validator = Validator::make($request->all(), [
            'election_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $election = InternalElection::where('id', request('election_id'))->where('is_active', true)->first();

        $canIVote = $this->canIVote($user, $electionStateId, $election);

        if ($canIVote === -1) {
            return $this->api_error_response('missing_parameters', 101, "ما مِن إنتخابات في الوقت الراهن");
        }

        if (!$canIVote || $canIVote === 401 || $canIVote === 500 || $canIVote === 403 || $canIVote === 405) {
            return $this->api_error_response('missing_parameters', 101, "لقد قمت بالتصويت أو أن صناديق الإقتراع قد أقفلت");
        }

        //The user can vote so return the cadidates list of his election region 'دائرة إنتخابية'
        $candidates = $election->candidates()->whereHas('electionStates', function($q) use ($electionStateId){ $q->where('election_states.id', $electionStateId); })->get();

        return response()->json([
            'cadidates' => $candidates->sortBy(function ($candidate) {
                // Admin-set display_order wins when set (lower first);
                // candidates without one sort after, alphabetically by
                // family name among themselves.
                $order = $candidate->display_order !== null
                    ? str_pad($candidate->display_order, 6, '0', STR_PAD_LEFT)
                    : '999999';
                return $order . '_' . $candidate->family_name;
            })->values()->map(function ($candidate) {
                return [
                    "name" => $candidate->name,
                    "id" => $candidate->id,
                    "photo" => $candidate->photo_url
                ];
            })
        ], 200);
    }

    function canIVoteFor($user, $candidates, $electionStateId, $election)
    {
        $_candidates = $election->candidates()->whereHas('electionStates', function($q) use ($electionStateId){ $q->where('election_states.id', $electionStateId); })->get()->pluck('id')->toArray();


        for ($i = 0; $i < count($candidates); $i++) {

            $result =  in_array($candidates[$i]['id'], $_candidates);
            if (!$result) {
                return false;
            }
        }

        return true;
    }


    // Internal-election selfie uploads go straight to S3 via a presigned
    // PUT URL, never through this app server - a server-mediated upload
    // would tie up a PHP-FPM worker for the whole upload duration on every
    // vote, during the exact kind of concurrent-vote spike that's already
    // the capacity bottleneck on election day.
    function internalElectionSelfieUploadUrl(Request $request)
    {
        $user = request('user');

        if (!$user) {
            return $this->api_error_response('missing_parameters', 101, "user not found");
        }

        $fileName = time() . '_' . Str::random(12) . '.jpg';
        $path = config('app.aws_bucket_project_name') . '/' . 'storage/' . 'images/internal_election_selfies/' . $fileName;

        $uploadUrl = Storage::disk('s3')->temporaryUploadUrl($path, now()->addMinutes(5));

        return response()->json([
            'upload_url' => $uploadUrl['url'] ?? $uploadUrl,
            'filename' => $fileName,
        ]);
    }

    function internalElectionVote(Request $request)
    {
        if (!$this->meetsInternalElectionMinBuild()) {
            return $this->api_error_response('app_update_required', 101, 'يرجى تحديث التطبيق للمشاركة في الانتخابات الداخلية');
        }

        $userInfo = request('loggedInUser');
        $user = request('user');
        $electionStateId = $this->resolveElectionStateId($userInfo);

        $validator = Validator::make($request->all(), [
            'election_id' => 'required|integer',
            'candidate_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $election = InternalElection::where('id', request('election_id'))->where('is_active', true)->first();

        $result = $this->canIVote($user, $electionStateId, $election);

        if ($result !== 200) {
            return $this->api_error_response('missing_parameterss', 101, "لا يمكنك التصويت");
        }

        //Validate the submitted candidate is actually in the voter's district, in this election
        $result = $this->canIVoteFor($user, [['id' => request('candidate_id')]], $electionStateId, $election);

        if (!$result) {
            return $this->api_error_response('missing_parameters', 101,  "لا يمكنك التصويت لان هذا المرشح ليس من ضمن دائرتك الإنتخابية");
        }

        // selfie_filename is only mandatory for elections that have the
        // requires_selfie toggle turned on by the admin - everything else
        // about voting is unchanged for elections without it.
        $selfieValidator = Validator::make($request->all(), [
            'selfie_filename' => $election->requires_selfie ? 'required|string' : 'nullable|string',
        ]);

        if ($selfieValidator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $selfieValidator->messages()->all()));
        }

        $selfieFilename = request('selfie_filename');

        if ($election->requires_selfie) {
            $path = config('app.aws_bucket_project_name') . '/' . 'storage/' . 'images/internal_election_selfies/' . $selfieFilename;

            // Without this, a client could skip the actual upload and just
            // send a made-up filename, defeating the whole point of the
            // verification step.
            if (!Storage::disk('s3')->exists($path)) {
                return $this->api_error_response('missing_parameters', 101, "لم يتم العثور على صورة التحقق، الرجاء إعادة المحاولة");
            }
        }

        // The canIVote() "already voted" check above isn't atomic with this
        // insert - under real concurrent load (double-tap, a network retry,
        // two sessions) two requests could both pass it. A unique DB
        // constraint on (user_id, internal_election_id) is the actual
        // guarantee; this just turns that into the same friendly message
        // instead of a raw 500 if it's ever hit.
        // last look right before saving, so a second account of the same member that is voting at this very moment is caught too
        if ($this->memberHasVotedInElection($user, $election->id)) {
            return $this->api_error_response('missing_parameterss', 101, "لا يمكنك التصويت");
        }

        try {
            $user->votes()->attach(request('candidate_id'), [
                'rank' => 1,
                'internal_election_id' => $election->id,
                // the database rule: one vote per member per election, whichever account sends it
                'member_key' => \App\Support\MemberKey::from($user->member_id),
                'weight' => 1,
                'selfie_path' => $election->requires_selfie ? $selfieFilename : null,
                'voted_at' => now(),
                'device_id' => request('device_id'),
                'device_model' => request('device_model') ? mb_substr((string) request('device_model'), 0, 191) : null,
                'hardware_id' => request('hardware_id') ? mb_substr((string) request('hardware_id'), 0, 191) : null,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return $this->api_error_response('missing_parameterss', 101, "لا يمكنك التصويت");
            }
            throw $e;
        }

        return response()->json([
            "message" => "لقد تم التصويت بنجاح شكراً"
        ], 201);
    }

    // For a registered candidate: every currently-eligible voter (member_status
    // active + on the per-election allowlist) across every district this
    // candidate is running in, across all their candidate rows in any active
    // election — not the general member list, so only actual candidates can
    // see it.
    function getMyDistrictVoters(Request $request)
    {
        if (!$this->meetsInternalElectionMinBuild()) {
            return $this->api_error_response('app_update_required', 101, 'يرجى تحديث التطبيق لعرض لائحة الناخبين');
        }

        $user = request('user');

        if (!$user || !$user->member_id) {
            return $this->api_error_response('missing_parameters', 101, "user not found");
        }

        // Per-candidate rate limit — this endpoint hands back real voter
        // names and phone numbers for an entire district, so nothing short
        // of this stops a script (using a perfectly legitimate login) from
        // pulling it far faster than any human reading the list would.
        // 60/hour at 100 voters/page is 6,000 voters/hour — comfortably
        // covers a real candidate paging through even a large district
        // (Zahle) in one sitting plus refreshing a few times, without the
        // forced per-request wait a lower cap + cooldown used to need.
        // Only ever bites sustained automated polling.
        // No escalating lockout (removed 2026-11-05) — repeat trips within
        // the same day used to push the next cooldown out to 6h then 24h,
        // which punished legitimate repeat testing far more than it ever
        // discouraged a real scraper (who'd just wait out whatever window
        // applies anyway). A flat, always-the-same 1-hour wait every time
        // keeps the real protection (the steady-state cap below never
        // changes) without that extra penalty.
        $rateLimitKey = 'district-voters:' . $user->member_id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 60)) {
            Log::warning('district_voters_rate_limited', [
                'member_id' => $user->member_id,
            ]);
            return $this->api_error_response('too_many_requests', 429, 'لقد تجاوزت الحد المسموح من الطلبات، يرجى المحاولة لاحقاً', 429);
        }
        RateLimiter::hit($rateLimitKey, 3600);

        // Checked without the voter_list_visible filter first, so we can
        // tell "not a candidate/viewer at all" apart from "is one, but the
        // admin has the list paused right now" and return the right message
        // for each — otherwise an authorized candidate/viewer gets told
        // "this page is for candidates only", which is just wrong.
        $isCandidateOrViewer = InternalElectionCandidate::where('member_id', $user->member_id)->exists()
            || InternalElectionVoterViewer::where('member_id', $user->member_id)->exists();

        if (!$isCandidateOrViewer) {
            return $this->api_error_response('unauthorized', 403, "هذه الصفحة مخصصة للمرشحين فقط");
        }

        // Not restricted to published elections — a candidate can see and
        // start reaching out to their voters before the election goes live.
        // voter_list_visible is a separate on/off switch from is_active
        // (published) — lets an admin turn list visibility off for a
        // specific election (e.g. once voting starts) without touching
        // whether the election itself is published.
        $candidateRows = InternalElectionCandidate::where('member_id', $user->member_id)
            ->whereHas('internalElection', fn($q) => $q->where('voter_list_visible', true))
            ->with('electionStates')
            ->get();

        // Admin-granted access for a specific non-candidate (e.g. campaign
        // staff) — same district-scoped visibility a candidate gets,
        // without being a registered candidate themselves.
        $viewerRows = InternalElectionVoterViewer::where('member_id', $user->member_id)
            ->whereHas('internalElection', fn($q) => $q->where('voter_list_visible', true))
            ->with('electionState')
            ->get();

        // A district with voting toggled off is already-agreed/uncontested -
        // the candidate's own voter list for that specific district is
        // hidden the same way the ballot itself is (see canIVote()). A
        // candidate spanning multiple districts only loses the disabled
        // one(s), not their whole list.
        $relevantElectionIds = $candidateRows->pluck('election_id')
            ->merge($viewerRows->pluck('election_id'))
            ->unique();
        $disabledPairs = InternalElectionDistrictVotingSetting::whereIn('election_id', $relevantElectionIds)
            ->where('voting_enabled', false)
            ->get(['election_id', 'election_state_id'])
            ->map(fn($s) => $s->election_id . '_' . $s->election_state_id)
            ->flip();

        $candidateRows = $candidateRows->map(function ($candidate) use ($disabledPairs) {
            $candidate->electionStates = $candidate->electionStates->filter(
                fn($state) => !$disabledPairs->has($candidate->election_id . '_' . $state->id)
            )->values();
            return $candidate;
        })->filter(fn($c) => $c->electionStates->isNotEmpty())->values();

        $viewerRows = $viewerRows->filter(
            fn($viewer) => !$disabledPairs->has($viewer->election_id . '_' . $viewer->election_state_id)
        )->values();

        if ($candidateRows->isEmpty() && $viewerRows->isEmpty()) {
            return $this->api_error_response('list_not_visible', 403, "لائحة الناخبين غير متاحة حالياً");
        }

        $districtNames = $candidateRows
            ->flatMap(function ($candidate) {
                return $candidate->electionStates->pluck('name');
            })
            ->merge($viewerRows->pluck('electionState.name'))
            ->filter()
            ->unique()
            ->values();

        // Allowed in at least one of this candidate's own elections, or one
        // they've been granted viewer access to — a voter permitted for a
        // different, unrelated election shouldn't show up here.
        $electionIds = $candidateRows->pluck('election_id')
            ->merge($viewerRows->pluck('election_id'))
            ->unique()
            ->values();
        $allowedMemberIds = InternalElectionPermission::whereIn('election_id', $electionIds)
            ->pluck('member_id');

        // Who's actually voted yet, across this candidate's own elections -
        // joined through app_users since internal_election_votes.user_id
        // points there, not at MemberId directly.
        $votedMemberIds = \DB::table('internal_election_votes')
            ->join('app_users', 'app_users.id', '=', 'internal_election_votes.user_id')
            ->whereIn('internal_election_votes.internal_election_id', $electionIds)
            ->whereNull('app_users.deleted_at')
            ->pluck('app_users.member_id')
            ->unique();

        // Paginated, fixed server-side regardless of anything the client
        // sends — turns "one call = the whole district" into "dozens of
        // calls needed", which is what makes the rate limit above actually
        // mean something instead of just gating a single instant dump.
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 100;

        // Town filter is applied server-side, not just client-side on
        // whatever's already loaded — otherwise a large district (e.g.
        // Zahle, many towns/voters sorted alphabetically) would show "no
        // matches" for any town not yet reached by pagination, even though
        // it genuinely has voters. '' (as opposed to absent) means "no town
        // on file", matching the app's "غير محدد" option.
        $townFilter = $request->query('town');

        $votersQuery = FpmUser::whereIn('district', $districtNames)
            ->where('member_status', 1)
            ->whereIn('MemberId', $allowedMemberIds);

        if ($townFilter !== null) {
            if ($townFilter === '') {
                $votersQuery->where(fn($q) => $q->whereNull('town')->orWhere('town', ''));
            } else {
                $votersQuery->where('town', $townFilter);
            }
        }

        // Same reasoning as the town filter above — applied server-side,
        // before pagination, not just on whatever's already loaded.
        // $votedMemberIds (computed above) already covers the candidate's
        // full district scope, not just this page, so this is cheap to add
        // here.
        $voteStatusFilter = $request->query('vote_status');
        if ($voteStatusFilter === 'voted') {
            $votersQuery->whereIn('MemberId', $votedMemberIds);
        } elseif ($voteStatusFilter === 'not_voted') {
            $votersQuery->whereNotIn('MemberId', $votedMemberIds);
        }

        $paginated = $votersQuery
            ->orderBy('town')
            ->orderBy('UserFullName')
            ->paginate($perPage, ['MemberId', 'UserFullName', 'MobileNumber', 'town'], 'page', $page);

        $voters = collect($paginated->items())->map(function ($voter) use ($votedMemberIds) {
            return [
                'full_name' => $this->shortVoterName($voter->UserFullName),
                'mobile_number' => $voter->MobileNumber,
                'town' => $voter->town,
                'has_voted' => $votedMemberIds->contains($voter->MemberId),
            ];
        })->values();

        // Distinct town list across the candidate's FULL scope (not just
        // this page) — cheap aggregate, no per-voter data in it — so the
        // app's town filter dropdown is complete from page 1 onward
        // instead of only showing towns seen so far as pages load.
        $towns = FpmUser::whereIn('district', $districtNames)
            ->where('member_status', 1)
            ->whereIn('MemberId', $allowedMemberIds)
            ->whereNotNull('town')
            ->where('town', '!=', '')
            ->distinct()
            ->orderBy('town')
            ->pluck('town')
            ->values();

        // Same full-scope reasoning as $towns above — whether the "غير
        // محدد" (unspecified town) option should even appear in the
        // dropdown shouldn't depend on whether such a voter happens to be
        // on the page that's loaded so far.
        $hasUnspecifiedTown = FpmUser::whereIn('district', $districtNames)
            ->where('member_status', 1)
            ->whereIn('MemberId', $allowedMemberIds)
            ->where(fn($q) => $q->whereNull('town')->orWhere('town', ''))
            ->exists();

        // Audit trail — a district's voter list is real member PII (names +
        // phone numbers); this is what lets staff see exactly who pulled
        // how much, when, and from where if a list ever leaks.
        Log::info('district_voters_pull', [
            'member_id' => $user->member_id,
            'page' => $page,
            'count' => $voters->count(),
            'ip' => $request->ip(),
        ]);

        // A sustained high volume across many hours (not just one burst)
        // is the real signature of someone looping this endpoint slowly
        // enough to stay under the hourly cap while still rebuilding the
        // full dataset over a day — logged distinctly (warning level) so
        // it's easy to grep/alert on separately from ordinary use. Fires
        // once per member per day, right when the threshold is crossed.
        $dailyKey = 'district-voters-daily:' . $user->member_id . ':' . now()->toDateString();
        $dailyCount = Cache::increment($dailyKey);
        if ($dailyCount == 300) {
            Log::warning('district_voters_abuse_suspected', [
                'member_id' => $user->member_id,
                'daily_pull_count' => $dailyCount,
                'ip' => $request->ip(),
            ]);
        }

        // Whether any of this candidate's/viewer's own elections is
        // actually published yet — the vote-status filter (صوّتوا / لم
        // يصوّتوا) only makes sense once voting can actually happen, so the
        // app hides it entirely before that.
        $isPublished = InternalElection::whereIn('id', $relevantElectionIds)
            ->where('is_active', true)
            ->exists();

        return response()->json([
            'voters' => $voters,
            'towns' => $towns,
            'has_unspecified_town' => $hasUnspecifiedTown,
            'page' => $page,
            'has_more' => $paginated->hasMorePages(),
            'is_published' => $isPublished,
        ], 200);
    }

    // Drops the father/grandfather middle name(s), keeping just first + family
    // name — e.g. "جورج طنوس طنوس سلوم" -> "جورج سلوم" — for the district
    // voters list, which only needs enough to identify who to call.
    function shortVoterName($fullName)
    {
        $parts = preg_split('/\s+/', trim($fullName ?? ''));

        if (count($parts) <= 2) {
            return $fullName;
        }

        // Common Lebanese compound-surname prefixes (e.g. "ابي زيد", "ابو
        // خليل") — keep the prefix attached to the family name instead of
        // dropping it as if it were a middle/father name.
        $compoundPrefixes = ['ابي', 'أبي', 'ابو', 'أبو', 'بو'];
        $familyStart = count($parts) - 1;

        if ($familyStart > 0) {
            // "الله" never stands alone as a Lebanese family name - it's
            // always the second half of a compound surname (عطا الله, نصر
            // الله, فضل الله, جار الله, ...), whatever the first half is,
            // so always keep the word before it rather than only matching
            // a fixed prefix list.
            if ($parts[$familyStart] === 'الله' || in_array($parts[$familyStart - 1], $compoundPrefixes)) {
                $familyStart--;
            }
        }

        return $parts[0] . ' ' . implode(' ', array_slice($parts, $familyStart));
    }


    ///End of election module


    //Delete my account

    public function deleteAccountVerification()
    {
        $userInfo = request('loggedInUser');
        $user = request('user');

        //generate pin
        if (app(\App\Http\Repositories\V2\RegistrationRepository::class)->usesFixedReviewCode($user->phone_number)) {
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

        if ($pin !== null && $pin !== '' && $user->verification_nb !== null && hash_equals((string) $user->verification_nb, (string) $pin)) {
            $user->token = null;
            $user->image = null;
            $user->custom_image = null;
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
