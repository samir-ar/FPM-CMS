<?php


namespace App\Http\Repositories\V2;

use App\V2\AppUser;
use App\V2\CouncilNationalPoll;
use Illuminate\Support\Facades\Log;

use App\V2\Favorite;
use App\V2\Placeholder;
use App\V2\TalkToUs;
use DB;
use DOMXPath;
use App\V2\Poll;
use App\V2\News;
use App\V2\Memo;
use App\V2\Link;
use App\V2\Polls;
use App\V2\Event;
use App\V2\Law;
use App\V2\Biography;
use DOMDocument;
use App\V2\Content;
use Carbon\Carbon;
use App\V2\Volunteer;
use App\V2\PollOption;
use App\V2\AppUserPoll;
use App\V2\Representative;
use App\Http\Traits\FileTrait;
use App\V2\CouncilNationalPollPermission;
use Storage;

class ApiRepository
{

    use FileTrait;

    public function getMemosByGroups($group_ids)
    {
        $group_ids = $group_ids ?? [];
        return Memo::whereHas('groups', function ($q) use ($group_ids) {
            return $q->whereIn('group_id', $group_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->orderBy('date', 'desc')->get();
    }

    public function getLaws($status)
    {
        $query = Law::where('deleted_at', null)->orderBy('date', 'desc');
        if ($status !== null) {
            $query->where('status', $status);
        }
        return $query->get();
    }

    public function getMemoFiles($memo_id)
    {
        $memo = Memo::find($memo_id);

        $path = 'tmp/' . uniqid() . str_replace(' ', '_', $memo->name) . '.zip';

        $zip = new \ZipArchive();
        $zip->open(public_path($path), \ZipArchive::CREATE);
        $zip->close();

        return $path;
    }

    public function getLawFiles($law_id)
    {
        $law = Law::find($law_id);

        $path = 'tmp/' . uniqid() . str_replace(' ', '_', $law->name) . '.zip';

        $zip = new \ZipArchive();
        $zip->open(public_path($path), \ZipArchive::CREATE);
        $zip->close();

        return $path;
    }

    public function getBiographyDetails($id)
    {
        $bio = Biography::where('person_id',$id)->where('deleted_at',null)->first();

        return [
            'title' => $bio->getTranslation('title', 'ar'),
            'body' => $bio->getTranslation('body', 'ar'),
        ];
    }

    public function getNationalCouncil(AppUser $user)
    {
        return ($poll = $user->nationalCouncilPollPermitted()->orderBy('created_at', 'desc')->whereHas('poll', function ($q) {
            $q->where('is_published', true);
        })->first()) ? $poll->poll : null;
    }

    public function getNewsByGroups($groups_ids)
    {
        $groups_ids = $groups_ids ?? [];
        return News::with("images")->whereHas('groups', function ($q) use ($groups_ids) {
            return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->orderBy('date', 'desc')->limit(200);
    }

    public function skipModeNews()
    {
        $url = "https://www.tayyar.org/Rss/Type/4";

        $xml = simplexml_load_file($url);

        //dd($xml);

        $items = [];

        $placeholder = Placeholder::where('type', 'news')->first()->image;

        foreach ($xml->channel->item as $item) {
            preg_match('/(<img[^>]+>)/i', (string)$item->description, $matches);

            if (isset($matches[0])) {
                //remove the match from the details
                $details = str_replace($matches[0], "", (string)$item->description);


                $img = $matches[0];
                $xpath = new DOMXPath(@DOMDocument::loadHTML($img));
                $file = $xpath->evaluate("string(//img/@src)");

                if (substr($details, 0, 12) == "<br /><br />")
                    $details = substr($details, 12);
            } else {
                $details = (string)$item->description;
                $file = null;
            }


            $items[] = (object)[
                'id' => null,
                'title' => (string)$item->title,
                'source' => null,
                'source_image' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/placeholders/' . $placeholder),
                'details' => $details,
                'link' => (string)$item->link,
                'file' => $file,
                'type' => 'image',
                'date' => strtotime(Carbon::parse((string)$item->children('http://www.w3.org/2005/Atom')->updated)->format('Y-m-d H:i:s')),
                'likes_nb' => null,
                'like' => null,
                'shares' => null,
            ];
        }
        return $items;
    }


    public function getMappedOption($poll)
    {
        $_options = $poll->options()->get()->map(function ($option) {
            return (object)[
                'id' => $option->id,
                'option' => $option->getTranslation('option', 'ar')
            ];
        });

        return $_options;
    }

    public function getPollsByGroups($user, $groups_ids)
    {
        $answered_polls = $user->polls->pluck('id')->toArray();

        return Poll::with('options')
            ->whereNotIn('polls.id', $answered_polls)
            ->where('expiry_date', '>', Carbon::today())
            ->whereHas('groups', function ($q) use ($groups_ids) {
                return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
            })
            ->where('show', true)
            ->get()
            ->map(function ($r) {
                return [
                    'expiry_date' => $r->expiry_date,
                    'question' => $r->question,
                    'details' => $r->details,
                    'strict_lang' => $r->strict_lang,
                    'options' => ($r->options) ? $this->getMappedOption($r) : [],
                ];
            });
    }



    //Get polls without any filtering
    public function getPollsByGroupsRaw($user, $groups_ids)
    {
        $groups_ids = $groups_ids ?? [];
        $answered_polls = $user->polls->pluck('id')->toArray();
        return Poll::with('options')
            ->whereNotIn('polls.id', $answered_polls)
            ->where('expiry_date', '>', Carbon::today())
            ->whereHas('groups', function ($q) use ($groups_ids) {
                return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
            })
            ->where('show', true)
            ->limit(20)
            ->get();
    }

    public function getMessages($user)
    {
        return $user->notifications()->orderBy('created_at', 'desc')->get();
    }

    public function likeNews($user, $news_id, $status)
    {

        if ($status == true) {
            if (!$user->news->where('news_id', $news_id)->first())
                $user->news()->attach($news_id);
        } else {
            $user->news()->detach($news_id);
        }

        $n = News::find($news_id);
        $placeholder = Placeholder::where('type', 'news')->first()->image;

        return [
            'id' => $n->id,
            'title' => $n->title,
            'source' => $n->source,
            'source_image' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/' . $n->source_image), //$this->getNewsImage($n, $placeholder) ,
            'attachments' => $n->attachments->map(function ($e) {
                return ["file" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'news/attachments/' . $e->name)];
            }),
            'details' => $n->details,
            'type' => $n->type,
            'file' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'news/attachments/' . $n->file),
            'date' => strtotime($n->date),
            'link' => null,
            'likes_nb' => $n->users()->count(),
            'like' => $n->users()->where('user_id', $user->id)->first() ? true : false,
            'shares' => $n->shares,
        ];
    }


    public function makeFavorite($user, $member_id, $make_favorite)
    {
        if (!$user->favorites()->where('favorite', $member_id)->first() && $make_favorite == true) {

            $favorite = new Favorite();
            $favorite->user_id = $user->id;
            $favorite->favorite = $member_id;
            $favorite->save();
        } elseif ($user->favorites()->where('favorite', $member_id)->first() && $make_favorite == false) {
            $favorite = $user->favorites()->where('favorite', $member_id)->first();
            $favorite->delete();
        }

        return;
    }



    public function shareNews($news_id, $user)
    {
        $n = News::find($news_id);
        $n->shares = $n->shares + 1;
        $n->save();

        $placeholder = Placeholder::where('type', 'news')->first()->image;

        return [
            'id' => $n->id,
            'title' => $n->title,
            'source' => $n->source,
            'source_image' => $this->getNewsImage($n, $placeholder),
            'details' => $n->details,
            'type' => $n->type,
            'file' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'news/attachments/' . $n->file),
            'date' => strtotime($n->date),
            'link' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'news/attachments/' . $n->file),
            'likes_nb' => $n->users()->count(),
            'like' => $n->users()->where('user_id', $user->id)->first() ? true : false,
            'shares' => $n->shares,
        ];
    }


    public function getPollById($user, $groups_ids, $poll_id)
    {
        $poll =  Poll::whereHas('groups', function ($q) use ($groups_ids) {
            $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })
            ->where('id', $poll_id)->first();

        return array(
            "id" => $poll->id,
            "question" => $poll->getTranslation("question", 'ar'),
            "options" => $poll->options()->get()->map(function ($option) {
                return (object)[
                    'id' => $option->id,
                    'option' => $option->getTranslation('option', 'ar')
                ];
            }),
            "details" => $poll->getTranslation('details', 'ar'),
            "expiry_date" => $poll->expiry_date
        );
    }

    public function getPreviousPolls($user, $groups_ids)
    {
        //return polls answerd with the answered option flagged
        return $user->polls()->whereHas('groups', function ($q) use ($groups_ids) {
            return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->get()->map(function ($p) use ($user) {
            return [
                'question' => $p->question,
                'date' => $p->created_at->toDateString(),
                'strict_lang' => $p->strict_lang,
                'answer' => $this->getPollOptionsWithRatings($user->id, $p->id),
            ];
        });
    }

    public function getMembersByGroup($groups_ids)
    {
        return Poll::where('show', true)->whereHas('groups', function ($q) use ($groups_ids) {
            return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->get();
    }


    public function userAnswerPoll($user, $option_id)
    {
        $option = PollOption::find($option_id);
        $poll_id = $option->poll_id;

        //remove old records
        $user->polls()->detach($poll_id);

        //add the record
        $user->polls()->attach($poll_id, ['option_id' => $option_id]);

        return true;
    }

    public function getPollOptionsWithRatings($user_id, $poll_id)
    {
        $total_count = AppUserPoll::where('poll_id', $poll_id)->count();

        return Poll::find($poll_id)->options()->get()->map(function ($o) use ($total_count, $user_id) {
            return [
                'id' => $o->id,
                'percentage' => number_format(round(($o->answers->count() * 100) / $total_count, 0)),
                'option' => $o->option,
                'answered' => $o->answers->where('app_user_id', $user_id)->first() ? true : false,
            ];
        });
    }

    public function getNationalCouncilByGroup($groups_ids)
    {
        return CouncilNationalPoll::whereHas('groups', function ($q) use ($groups_ids) {
            return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->orderBy('created_at', 'desc')->where('is_published', true);
    }

    public function getEventsPreviousByGroup($groups_ids)
    {
        $groups_ids = $groups_ids ?? [];
        return Event::whereHas('groups', function ($q) use ($groups_ids) {
            return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->where('to_date', '<', Carbon::today());
    }

    public function getEventsUpcomingByGroup($groups_ids)
    {
        $groups_ids = $groups_ids ?? [];
        return Event::whereHas('groups', function ($q) use ($groups_ids) {
            return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->where('to_date', '>', Carbon::today())->limit(20);
    }

    public function saveVolunteer($user, $volunteer_id)
    {
        $user->volunteers()->attach($volunteer_id);

        return;
    }

    public function getPublicLinks()
    {
        $links = Link::orderBy('order', 'asc')
            ->orderBy('created_at', 'desc')
            ->where('public', true)->get();

        return $links;
    }

    public function userTalkToUs($user, $data)
    {
        $talk = new TalkToUs();
        $talk->user_id = $user->id;
        $talk->title = $data['title'];
        $talk->text = $data['message'];

        $talk->save();

        return ['message' => 'Thank you for you message.'];
    }

    public function getLinksByGroups($groups_ids)
    {
        $links = Link::whereHas('groups', function ($q) use ($groups_ids) {
            return $q->whereIn('group_id', $groups_ids)->orWhere('group_id', 81)->orWhere('group_id', 82);
        })->orderBy('order', 'asc')->orderBy('created_at', 'desc')->get();

        return $links;
    }

    public function getNewsImage($n, $placeholder)
    {
        $images = $n->images->map(function ($i) {
            return [
                "type" => "image",
                "thumbnail" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/' . $i->name),
                "link" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/' . $i->name)
            ];
        })->toArray();
        return (count($images) > 0) ? $images : [array("img" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/placeholders/' . $placeholder))];
    }

    public function getMedia($type, $element, $placeholder)
    {
        switch ($type) {
            case "NEWS":
                return $this->getNewsMedia($element, $placeholder);
            case "EVENTS":
                return $this->getEventsMedia($element);
            default:
                return [];
        }
    }

    public function getEventsMedia($element)
    {
        return $element->images->map(function ($i) {
            return [
                "type" => 'image',
                "thumbnail" => ($i->src) ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/events/' . $i->src) : null,
                "link" => ($i->src) ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/events/' . $i->src) : null
            ];
        });
    }

    public function getNewsMedia($n, $placeholder)
    {
        $media = [];
        if ($n->type) {
            // $path = "images/news/";

            return array([
                "type" => $n->type,
                "thumbnail" => ($n->thumbnail) ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'news/vedio/thumbnails/' . $n->thumbnail) : Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/' . $n->file),
                "link" => ($n->file) ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'news/attachments/' . $n->file) : Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/placeholders/' . $placeholder)
            ]);
        }

        //---->images/news (will hold the images - including the video thumbnail + and source_image: which is the photo of publisher)
        //---->/news/videos (will hold the videos)
        //---->/news/videos/thumbnails (will hold the videos' thumbnail)
        //---->/news/attachments/ (will hold the pdfs)

        //NEWS table
        //--> file (will hold the name of the video only)
        //--> thumbnail (will hold the thumbnail of the video only)

        //NEWS_Attachements table
        //-->  (will hold the names of the pdfs)

        $images = $n->images->map(function ($i) {
            return [
                //The thumbnail of an image will be the the same image file
                "type" => "image",
                "thumbnail" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/' . $i->name),
                "link" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/' . $i->name)
            ];
        })->toArray();


        //Add Video
        $video = ($n->file) ? array([
            "type" => "video",
            "thumbnail" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'news/videos/thumbnails/' . $n->thumbnail),
            "link" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'news/videos/' . $n->file)
        ]) : [];


        //Getting the placeholder of the pdf
        $pdfPlaceholder = Placeholder::where('type', 'pdf_news')->first();

        if ($pdfPlaceholder) {
            $pdfPlaceholder = Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/placeholders/' . Placeholder::where('type', 'pdf_news')->first()->image);
        }

        //Add pdf
        $attachment = $n->attachments->map(function ($e) use ($pdfPlaceholder) {
            return [
                "type" => "pdf",
                "thumbnail" => $pdfPlaceholder,
                "link" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'news/attachments/' . $e->name)
            ];
        })->toArray();

        $media = array_merge($images, $video);
        $media = array_merge($media, $attachment);

        return (count($media) > 0) ? array_values($media) : array([
            "type" => "image",
            "thumbnail" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/placeholders/' . $placeholder),
            "link" => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/placeholders/' . $placeholder)
        ]);
    }


    public function getNewsInstance($n, $user, $placeholder)
    {
        /*        return [
            'id' => $n->id,
            'title' => $n->title,
            'source' => $n->source,
            'source_image' => (array)$this->getNewsImage($n, $placeholder),
            'attachments' => $n->attachments->map(function($e){ return secure_url("attachements/".$e->name); }),

            'details' => $n->details,
            'type' => $n->type,
            'file' => secure_url($n->file),
            'thumbnail' => $n->thumbnail ? secure_url($n->thumbnail) : secure_url($placeholder),
            'date' => strtotime($n->date),
            'link' => secure_url($n->file),
            'likes_nb' => $n->users()->count(),
            'like' => $n->users()->where('user_id', $user->id)->first() ? true : false,
            'shares' => $n->shares,
            'force_lang' => $n->strict_lang,
        ]; */
        return [
            'id' => $n->id,
            'title' => $n->title,
            'details' => $n->details,
            'source' => $n->source,
            'post_image' => ($n->source_image) ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/' . $n->source_image) : null,
            'media' => $this->getNewsMedia($n, $placeholder),
            'type' => $n->type,
            'thumbnail' => $n->thumbnail ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'news/videos/thumbnails/' . $n->thumbnail) : Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/placeholders/' . $placeholder),
            'date' => strtotime($n->date),
            'likes_nb' => $n->users()->count(),
            'like' => $n->users()->where('user_id', $user->id)->first() ? true : false,
            'strict_lang' => $n->strict_lang,
            'link' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'news/attachments/' . $n->file),
            'shares' => $n->shares,
            "created_at" => (int) strtotime($n->created_at),
            //'file' => secure_url($n->file),
            //'attachments' => $n->attachments->map(function($e){ return secure_url("attachements/".$e->name); }),
        ];
    }


    public function getEventInstance($e)
    {
        return [
            'id' => $e->id,
            'name' => ($e->strict_lang && $e->strict_lang == 'en') ? $e->getTranslation('name', 'en') : $e->getTranslation('name', 'ar'),
            'details' => ($e->strict_lang && $e->strict_lang == 'en') ? $e->getTranslation('details', 'en') : $e->getTranslation('details', 'ar'),
            'organized_by' => $e->organized_by,
            'location' => $e->location,
            'lng' => floatval($e->lng),
            'lat' => floatval($e->lat),
            'from_date' => strtotime($e->from_date),
            'to_date' => strtotime($e->to_date),
            'thumbnail' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/events/' . $e->image),
            'images' => $e->images->map(function ($i) {
                return [
                    'img' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/events/' . $i->src),
                ];
            })->pluck('img')->toArray(),
            'force_lang' => $e->strict_lang
        ];
    }


    //Jihad Code start here
    public function getGenericInstance($g, $type, $placeholder = null, $user = null)
    {

        //Thumbnail
        $thumbnail = null;

        if ($type == 'EVENTS' && $g->image) {
            $thumbnail = $g->image;
        }

        if ($g->thumbnail) {
            $thumbnail = $g->image;
        }

        $name = null;
        $details = null;

        if ($g->name) {
            $name = ($g->strict_lang == 'en') ? $g->getTranslation('name', 'en') : $g->getTranslation('name', 'ar');
        }
        if ($g->details) {
            $details = ($g->strict_lang == 'en') ? $g->getTranslation('details', 'en') : $g->getTranslation('details', 'ar');
        }

        return collect([
            'id' => $g->id,
            'title' => ($g->title) ? $g->title : $name,
            'details' => $details,
            'source' => $g->source,
            "post_image" => ($g->source_image) ? ($type === "NEWS" ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/' . $g->source_image) : ($type === "EVENTS" ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/events/' . $g->source_image) : null)) : null,
            'media' => $this->getMedia($type, $g, $placeholder),
            'type' => $g->type,
            "thumbnail" => ($thumbnail) ? ($type === "NEWS" ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'news/videos/thumbnails/' . $thumbnail) : ($type === "EVENTS" ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/events/' . $thumbnail) : null)) : null,
            "date" => ($g->date) ? strtotime($g->date) : null,
            "likes_nb" => ($type === "NEWS") ? $g->users()->count() : null,
            "like" => ($type === "NEWS") ? ($user ? (($g->users()->where('user_id', $user->id)->first()) ? true : false) : false) : null,
            "strict_lang" => ($g->strict_lang) ? $g->strict_lang : "ar",
            "link" => ($g->link) ? secure_url($g->link) : null,
            "shares" => $g->shares,


            'question' => $g->question,
            //'option' => ($g->options)?$g->options:[],
            'options' => ($g->options) ? $g->options()->get()->map(function ($r) {
                return [
                    'id' => $r->id,
                    'option' =>  $r->getTranslation('option', 'ar')
                ];
            }) : [],
            'strict_lang' => $g->strict_lang,

            //'attachments' =>  ($type === "NEWS")?$g->attachments->map(function($e){ return secure_url("attachements/".$e->name); }):null,
            "expiry_date" => $g->expiry_date,

            "organized_by" => $g->organized_by,
            "location" => $g->location,
            "lng" => ($g->lng) ? floatval($g->lng) : null,
            "lat" => ($g->lat) ? floatval($g->lat) : null,
            "from_date" => ($g->from_date) ? strtotime($g->from_date) : null,
            "to_date" => ($g->to_date) ? strtotime($g->to_date) : null,
            /*"images"=> ($g->images)?$g->images->map(function($i){
                        return ['img' => secure_url($i->src),];
                        })->pluck('img')->toArray():[],*/
            "created_at" => (int) strtotime($g->created_at),
            "item_type" => $type
        ]);
    }
}
