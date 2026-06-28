<?php

namespace App\Http\Controllers\Api;

use App;
use App\Faq;
use Storage;
use App\News;
use App\Event;
use Validator;
use App\Webview;
use App\Content;
use App\Volunteer;
use App\LiveStream;
use App\FaqCategory;
use App\Placeholder;
use App\Transaction;
use App\Representative;
use App\BillTransaction;
use Illuminate\Http\Request;
use App\Http\Traits\ResponseTrait;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\Paginator;
use App\Http\Repositories\ApiRepository;
use App\Http\Repositories\FpmApisRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Repositories\LiveStreamsRepository;



class ApiController extends Controller
{

    use ResponseTrait;


    public function myProfile(Request $request)
    {
        $token = $request->user->token;

        return redirect('https://mobapp.twh-lb.org:444/datacenter/MobilePersonProfile.aspx?tokenid='.$token);
    }

    public function getMemos(Request $request, ApiRepository $apiRepo)
    {
        return response()->json($apiRepo->getMemosByGroups(request('groups'))->map(function($g){
            return [
                'id' => $g->id,
                'name' => $g->name,
                //'details' => $g->details,
                'file' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/memos/' . $g->file),
            ];
        }));
    }

    public function getWebviews(Request $request)
    {

        $token = $request->user ? $request->user->token : null;
	    $groups = request('groups') ? implode(',', request('groups')) : null;

        $webviews = Webview::all()->map(function($r) use ($token, $groups){
            return [
                'name' => $r->slug,
                'url' => $r->url . '?tokenid='.$token.'&accesstoken='.$token.'&groupids='.$groups,
            ];
        });

        return response()->json($webviews);
    }

    public function likeNews(Request $request, ApiRepository $repo)
    {
        $validator = Validator::make($request->all(), [
            'news_id' => 'required|exists:news,id',
            'like' => 'required|boolean'
        ]);

        if ($validator->fails())
        {
            return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
        }


        return response()->json($repo->likeNews(request('user'), request('news_id'), request('like')));
    }

    public function shareNews(Request $request, ApiRepository $repo)
    {
        $validator = Validator::make($request->all(), [
            'news_id' => 'required|exists:news,id',
        ]);

        if ($validator->fails())
        {
            return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
        }


        return response()->json($repo->shareNews(request('news_id'), request('user')));

    }

    public function downloadMemoFiles(Request $request, ApiRepository $repo)
    {
        $validator = Validator::make($request->all(), [
            'memo_id' => 'required',
        ]);

        if ($validator->fails())
        {
            return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
        }

        $zipFile = $repo->getMemoFiles(request('memo_id'));

        return response()->json(['path' => url($zipFile)]);
    }

    public function getGroups(Request $request)
    {
        $groups = request('groups_info');

        return response()->json($groups->map(function($g){
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
            'group_ids' => request('group_id') ? : implode(',', request('groups')),
        ];

        $members = $fpmRepo->getCMSGroupsMembers($data);

        $salehMembers = [];
        $user = request('user');

        foreach($members as $member){
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

        foreach($members as $member){
            if($user->favorites()->where('favorite', $member->MemberId)->first()){
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

        if ($validator->fails())
        {
            return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
        }

        $repo->makeFavorite(request('user'), request('member_id'), request('favorite'));

        return response()->json(['message' => 'User added as favorite']);
    }

    public function getMessages(Request $request, ApiRepository $repo)
    {
        $placeholder = Placeholder::where('type', 'messages')->first()->image;

        return response()->json($repo->getMessages(request('user'))->map(function($row) use ($placeholder){
            return [
                'id' => $row->id,
                'title' => $row->title,
                'text' => $row->text,
                'image' => $row->image ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/placeholders/' . $row->image) : Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/placeholders/' . $placeholder),
            ];
        }));
    }

    public function getNewsWithPagination(Request $request, ApiRepository $repo)
    {
        if(request('user')){

            //dd(app()->getLocale());


            $placeholder = Placeholder::where('type', 'news')->first()->image;
            $news = $repo->getNewsByGroups(request('groups'));

            $user = request('user');

            $news = $news->paginate(10);


            $news->transform(function($q) use ($user, $placeholder, $repo){
                return $repo->getNewsInstance($q,$user,$placeholder);
            });

        }
        else{

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

    public function getNews(Request $request, ApiRepository $repo)
    {
        if(request('user')){

            //dd(app()->getLocale());


            $placeholder = Placeholder::where('type', 'news')->first()->image;
            $news = $repo->getNewsByGroups(request('groups'))->get();

            $user = request('user');
            $news = $news->map(function($n) use ($user, $placeholder, $repo){

                return $repo->getNewsInstance($n,$user,$placeholder);
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

        }
        else
            $news = $repo->skipModeNews();


        return response()->json($news);
    }

    public function getPolls(Request $request, ApiRepository $repo)
    {
        //current polls
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
        $validator = Validator::make($request->all(), [
            'option_id' => 'required|exists:polls_options,id',
        ]);

        if ($validator->fails())
        {
            return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
        }

        $repo->userAnswerPoll(request('user'), request('option_id'));

        return response()->json(['message' => 'Thank you for participating.']);
    }

    public function volunteers(Request $request)
    {
        return response()->json(Volunteer::all()->map(function($v){
            return [
                'id' => $v->id,
                'title' => $v->title,
                'text' => $v->text,
                'image' => $v->image ?  Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/volunteers/' . $v->image) : null,
            ];
        }));
    }

    public function getPreviousEvents(Request $request, ApiRepository $repo)
    {
       $events = $repo->getEventsPreviousByGroup(request('groups'));

       return response()->json($events->map(function($e) use ($repo){

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



    public function getUpcomingEventsPaginate(Request $request, ApiRepository $repo){

     /*    //TODO need pagination
            $placeholder = Placeholder::where('type', 'news')->first()->image;
            $news = $repo->getNewsByGroups(request('groups'));
            $user = request('user');
            $news = $news->paginate(10);
            $news->transform(function($q) use ($user, $placeholder, $repo){
                return $repo->getEventInstance($q);
            });
            return response()->json($news);

        $events = $repo->getEventsUpcomingByGroup(request('groups'));
        return response()->json($events->map(function($e) use ($repo){
            return $repo->getEventInstance($e);
        }); */

    }



    public function getUpcomingEvents(Request $request, ApiRepository $repo)
    {
        $events = $repo->getEventsUpcomingByGroup(request('groups'));

        return response()->json($events->map(function($e) use ($repo){

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

        if ($validator->fails())
        {
            return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
        }

        $event = Event::find(request('event_id'));


        return response()->json($repo->getEventInstance($event));
    }

    public function getNewsById(Request $request, ApiRepository $repo)
    {
        $validator = Validator::make($request->all(), [
            'news_id' => 'required|exists:news,id,deleted_at,NULL',
        ]);

        if ($validator->fails())
        {
            return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
        }

        $news = News::find(request('news_id'));


        $user = request('user');
        $placeholder = Placeholder::where('type', 'news')->first()->image;

        return response()->json($repo->getNewsInstance($news,$user,$placeholder));

    }

    public function becomeVolunteer(Request $request, ApiRepository $repo)
    {
        $validator = Validator::make($request->all(), [
            'volunteer_id' => 'exists:volunteers,id',
        ]);

        if ($validator->fails())
        {
            return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
        }

        $repo->saveVolunteer(request('user'), request('volunteer_id'));

        return response()->json(['message' => 'Thank you for applying.']);
    }

    public function getRepresentatives(Request $request, ApiRepository $repo)
    {
        $content = Content::where('category', 'representative-content')->first();
        $representatives =  Representative::orderBy('order', 'asc')->get()->map(function($r){
		return [
			'id' => $r->id,
			'name' => $r->name,
			'category' => $r->category,
			'image' => $r->image ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/representatives/' . $r->image) : null,
		];
	});


        return response()->json([
            'title' => $content ? $content->title : null,
            'text' => $content ? $content->text : null,
            'representatives' => $representatives,
        ]);
    }

    public function getLinks(Request $request, ApiRepository $repo)
    {
        if(!request('user'))
            $links = $repo->getPublicLinks();

        else $links = $repo->getLinksByGroups(request('groups'));

        return response()->json($links->map(function($l){
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

        if(!$content || !$media)
            return $this->api_error_response('missing_parameters', 101, 'No content available');


        $media = json_decode($media->text);

        return response()->json([
            'images' => [Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/content/' . $content->image)],
            'text' => App::getLocale() == 'en' ? $content->text : $content->text_ar,
            'email' => $media->email,
            'facebook' => $media->facebook,
            'instagram' => $media->instagram,
            'youtube' => $media->youtube,
            'linkedIn' => $media->linkedIn,
            'twitter' => $media->twitter,
            'phone_number' => '96103067387',
        ]);
    }

    public function faqCategories(Request $request)
    {
        return response()->json(FaqCategory::orderBy('order', 'asc')->select('id', 'name')->get()->map(function($r){
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

        if ($validator->fails())
        {
            return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
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

        if ($validator->fails())
        {
            return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
        }

        return response()->json(Faq::orderBy('order', 'asc')->select('id', 'name', 'details')
            ->where('cat_id', request('cat_id'))->get()->map(function($r){
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
        $liveStream = LiveStream::whereHas('groups', function($q){
            return $q->whereIn('group_id', request('groups'));
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

        if ($validator->fails())
        {
            return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
        }

        $transaction = Transaction::create([
            'user_id' => request('user_id'),
            'transaction_id' => 'MOB_'.(int)(microtime(true)*1000),
            'amount' => request('amount'),
            'currency' => currency_iso(request('currency')),
            'user_name' => request('name'),
            'phone_number' => request('phone_number'),
            'email' => request('email'),
        ]);

        return response()->json([
            'url' => route('netcommerce.payment.redirect',$transaction->id).'?payment=i-pay',
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

        if ($validator->fails())
        {
            return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
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

        $transaction->transaction_id = 'MOB_BILL_'.$transaction->id;

        $transaction->save();


        return response()->json([
            'url' => route('netcommerce.payment.redirect',$transaction->id).'?payment=bill',
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

}
