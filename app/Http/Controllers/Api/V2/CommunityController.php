<?php

namespace App\Http\Controllers\Api\V2;

use Validator;
use Storage;
use App\V2\BusinessType;
use App\V2\CommunityPost;
use App\V2\DirectoryMember;
use App\V2\District;
use Illuminate\Http\Request;
use App\Http\Traits\FileTrait;
use App\Http\Traits\ResponseTrait;
use App\Http\Controllers\Controller;

class CommunityController extends Controller
{
    use ResponseTrait;
    use FileTrait;

    /**
     * The Android emulator's virtual networking can lose its route to the
     * real internet while still reaching the host machine fine, which
     * breaks direct S3 image URLs during local dev/demos. When running
     * locally, route images through this server (which does have real
     * internet access) instead of pointing the client straight at S3.
     */
    private function imageUrl($key)
    {
        // config('app.env') (not env('APP_ENV') — under this WAMP/mod_fcgid
        // setup, raw env() calls inside application code have been observed
        // to intermittently return null for some request-serving worker
        // processes even though the bootstrapped config is always correct;
        // this is also why Laravel's own convention is env() only inside
        // config files).
        if (config('app.env') === 'local') {
            // Use the incoming request's own host/port rather than the
            // configured APP_URL (which is "localhost" — inside the
            // Android emulator that resolves to the emulator itself, not
            // this machine, and would break the proxy URL).
            return request()->root() . '/api/v2/image-proxy?key=' . urlencode($key);
        }

        return Storage::disk('s3')->url($key);
    }

    public function imageProxy(Request $request)
    {
        $key = request('key');
        $prefix = config('app.aws_bucket_project_name') . '/storage/images/';

        if (!$key || !str_starts_with($key, $prefix)) {
            abort(404);
        }

        // Cache the bytes on local disk after the first fetch so repeat
        // requests (the emulator retries a lot on its flaky virtual
        // network) don't each pay for a fresh, slow S3 round trip — the
        // Android emulator's connection to this proxy handles a fast local
        // read far more reliably than a chunked transfer that's itself
        // waiting on a slow upstream S3 fetch.
        $cachePath = 'image_proxy_cache/' . md5($key);
        $localDisk = Storage::disk('local');

        if ($localDisk->exists($cachePath)) {
            $contents = $localDisk->get($cachePath);
            $mime = $localDisk->mimeType($localDisk->path($cachePath));
        } else {
            if (!Storage::disk('s3')->exists($key)) {
                abort(404);
            }
            $contents = Storage::disk('s3')->get($key);
            $mime = Storage::disk('s3')->mimeType($key);
            $localDisk->put($cachePath, $contents);
        }

        return response($contents, 200)
            ->header('Content-Type', $mime)
            ->header('Content-Length', (string) strlen($contents))
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function postToArray(CommunityPost $post)
    {
        return [
            'id' => $post->id,
            'first_name' => $post->first_name,
            'last_name' => $post->last_name,
            'full_name' => trim($post->first_name . ' ' . $post->last_name),
            'age' => $post->age,
            'email' => $post->email,
            'phone' => $post->phone,
            'address' => $post->address,
            'business_type_id' => $post->business_type_id,
            'business_type' => $post->businessType
                ? $post->businessType->getTranslation('name', $post->language ?: 'ar')
                : null,
            'language' => $post->language,
            'business_description' => $post->business_description,
            'image' => $post->image
                ? $this->imageUrl(config('app.aws_bucket_project_name') . '/storage/images/community_posts/' . $post->image)
                : null,
            'status' => $post->status,
            'created_at' => $post->created_at,
        ];
    }

    public function getBusinessTypes(Request $request)
    {
        $types = BusinessType::get()->sortBy(fn($t) => $t->getTranslation('name', 'ar'))->values();

        return response()->json($types->map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->getTranslation('name', 'ar'),
                'name_en' => $t->getTranslation('name', 'en'),
                'icon' => $t->icon
                    ? $this->imageUrl(config('app.aws_bucket_project_name') . '/storage/images/business_type_icons/' . $t->icon)
                    : null,
            ];
        }));
    }

    public function getDistricts(Request $request)
    {
        // id=1 is a placeholder ("إختر محافظة") used for CMS dropdown
        // defaults, not a real district — exclude it here.
        $districts = District::where('id', '!=', 1)->orderBy('name')->get(['id', 'name']);

        return response()->json($districts);
    }

    public function getDirectoryMembers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_type_id' => 'required|exists:business_types,id',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $members = DirectoryMember::where('business_type_id', request('business_type_id'))
            ->orderBy('order')
            ->get();

        return response()->json($members->map(function ($m) {
            return [
                'id' => $m->id,
                'name' => $m->name,
                'specialty' => $m->specialty,
                'phone' => $m->phone,
                'country' => $m->country,
                'governorate' => $m->governorate,
                'district' => $m->district,
                'town' => $m->town,
                'latitude' => $m->latitude,
                'longitude' => $m->longitude,
                'image' => $m->image
                    ? $this->imageUrl(config('app.aws_bucket_project_name') . '/storage/images/directory_members/' . $m->image)
                    : null,
            ];
        }));
    }

    public function getCommunityPosts(Request $request)
    {
        $query = CommunityPost::where('status', 'approved');

        if (request('business_type_id')) {
            $query->where('business_type_id', request('business_type_id'));
        }

        $posts = $query->latest()->get();

        return response()->json($posts->map(fn($p) => $this->postToArray($p)));
    }

    public function getMyCommunityPosts(Request $request)
    {
        $user = request('user');

        $posts = CommunityPost::where('user_id', $user->id)->latest()->get();

        return response()->json($posts->map(fn($p) => $this->postToArray($p)));
    }

    public function createCommunityPost(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required',
            'last_name' => 'required',
            'business_type_id' => 'required|exists:business_types,id',
            'language' => 'required|in:ar,en',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'age' => 'nullable|integer',
            'image' => 'nullable|image|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $user = request('user');

        $post = new CommunityPost();
        $post->user_id = $user->id;
        $post->first_name = request('first_name');
        $post->last_name = request('last_name');
        $post->age = request('age');
        $post->email = request('email');
        $post->phone = request('phone');
        $post->address = request('address');
        $post->business_type_id = request('business_type_id');
        $post->language = request('language');
        $post->business_description = request('business_description');
        $post->status = 'pending';

        if (request('image')) {
            $post->image = $this->moveFile(request('image'), 'images/community_posts');
        }

        $post->save();

        return response()->json([
            'message' => 'Post submitted for review.',
            'post' => $this->postToArray($post),
        ]);
    }

    public function updateCommunityPost(Request $request, $id)
    {
        $post = CommunityPost::find($id);

        if (!$post) {
            return $this->api_error_response('not_found', 104, 'Post not found');
        }

        $user = request('user');

        if ($post->user_id != $user->id) {
            return $this->api_error_response('forbidden', 103, 'You cannot edit this post');
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required',
            'last_name' => 'required',
            'business_type_id' => 'required|exists:business_types,id',
            'language' => 'required|in:ar,en',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'age' => 'nullable|integer',
            'image' => 'nullable|image|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $post->first_name = request('first_name');
        $post->last_name = request('last_name');
        $post->age = request('age');
        $post->email = request('email');
        $post->phone = request('phone');
        $post->address = request('address');
        $post->business_type_id = request('business_type_id');
        $post->language = request('language');
        $post->business_description = request('business_description');
        $post->status = 'pending';

        if (request('image')) {
            $this->removeFile('images/community_posts/' . $post->image);
            $post->image = $this->moveFile(request('image'), 'images/community_posts');
        }

        $post->save();

        return response()->json([
            'message' => 'Post updated and submitted for review.',
            'post' => $this->postToArray($post),
        ]);
    }
}
