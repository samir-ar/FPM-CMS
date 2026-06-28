<?php


namespace App\Http\Repositories\V2;

use GuzzleHttp\Client;
use Validator;
use App\V2\FpmUser;
use App\Http\Traits\ResponseTrait;

class FpmApisRepository
{
    use ResponseTrait;

    public function getToken(){
        $validator = Validator::make(request()->all(), [
            'member_id' => 'required',
            'phone_number' => 'required',
        ]);
        
        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        $user = FpmUser::where([
            ['MemberId', request('member_id')],
            ['MobileNumber', str_replace('+', '', request('phone_number'))]
        ])->with('fpmUsersActions')->first();

        return $user;
    }
    
    public function getTokenOld($data){
        //$endpoint = "https://mobappcms.twh-lb.org/TWHEPartyService.svc/VerifyUser";
        $endpoint = "https://twhsystem.org/TWHMembersAPI/TWHEPartyService.svc/VerifyUser";

        $client = new Client();

        $response = $client->request('GET', $endpoint, ['query' => [
            'mobile' => $data['phone_number'],
            'memberId' => $data['member_id'],
            'buildnumber' => $data['build_number'],
        ]]);


        $statusCode = $response->getStatusCode();
        $content = $response->getBody()->getContents();

        $content = json_decode($content);

        //dd($content);

        return $content;
    }

    public function verifyToken($data)
    {


        $endpoint = "https://mobappcms.twh-lb.org/TWHEPartyService.svc/VerifyToken";
        $endpoint = "https://twhsystem.org/TWHMembersAPI/TWHEPartyService.svc/VerifyToken";

        $client = new Client();

        $response = $client->request('GET', $endpoint, ['query' =>[
            'tokenId' => $data['token'],
            'buildnumber' => $data['build_number'],
        ]]);

        $statusCode = $response->getStatusCode();
        $content = $response->getBody()->getContents();


        return json_decode($content);
    }

    public function sendSMS($data)
    {
        $endpoint = "https://globesms.net/smshub/api.php";

        $client = new Client();

        $response = $client->request('GET', $endpoint, ['query' =>[
            'username' => 'I T',
            'password' => 'Fpm@Mob',
            'action' => 'sendsms',
            'from' => 'FPM-IT.Mob',
            'to' => $data['phone_number'],
            'text' => $data['message'],
        ],'verify' => false]);

        $statusCode = $response->getStatusCode();
        $content = $response->getBody()->getContents();


        return json_decode($content);
    }

    public function getGroups()
    {
        $endpoint = "https://mobappcms.twh-lb.org/TWHEPartyService.svc/CMS_GetServiceGroups?AccessToken=test1&serviceid=4";
        $endpoint = "https://twhsystem.org/TWHMembersAPI/TWHEPartyService.svc/CMS_GetServiceGroups?AccessToken=test1&serviceid=4";

        $client = new Client();

        $response = $client->request('GET', $endpoint, ['query' => [
            'AccessToken' => 'test1',
            'serviceid' => 4
        ]]);

        $statusCode = $response->getStatusCode();
        $content = $response->getBody()->getContents();

        return json_decode($content);
    }

    public function getCMSGroupsMembers($data)
    {
        $endpoint = "https://mobappcms.twh-lb.org/TWHEPartyService.svc/getgroupsmembers";
        $endpoint = "https://twhsystem.org/TWHMembersAPI/TWHEPartyService.svc/getgroupsmembers";

        $client = new Client();

        $response = $client->request('GET', $endpoint, ['query' => [
            'accesstoken' => isset($data['accesstoken']) ? $data['accesstoken'] : 'bebc011b78',
            'groupids' => $data['group_ids'],
        ]]);

        $statusCode = $response->getStatusCode();
        $content = $response->getBody()->getContents();

        return json_decode($content);
    }

}