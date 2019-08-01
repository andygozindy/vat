<?php

/*
 * Auth process
 * 1. Send user to grant auth to this app. This is a link from client website - GET  https://test-api.service.hmrc.gov.uk/oauth/authorize?response_type=code&client_id=[YOUR-CLIENT-ID]&scope=[REQUESTED-SCOPE]&state=[STATE]&redirect_uri=[YOUR-REDIRECT-URI]
 * 2. HMRC website returns an access code to to the redirect uri callback()
 * 3. callback() asks accessToken() to exchange access code
 * 4. if successfull save credentials to DB with storeData()
 * 5. if not successfull redirect back to client with get params (TODO)
 * 6. For each call to a method, first check expiry of access_token getCredentials()
 * 7. If expired refresh then make the call refreshToken()
 * 8. If not expired just make the call -> whatever the original call was for
*/

//OAuth Methods
//-------
//callback()
//accessToken()
//storeData()
//refreshToken()
//getCredentials()
//connect() - make sure data is json_encoded

//ToDo
/*
 * Validate params from HMRC
 * Redirect if there is an error
 */
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\RedirectResponse;
use App\Token;
use Log;

class MTDController extends Controller
{
    private $local_domain;
    private $remote_domain_test;
    private $remote_domain_live;
    private $remote_domain;
    private $debug;
    private $redirect;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->local_domain = config('extra_config.domain');//set this in your .env file
        $this->remote_domain_live = 'https://api.service.hmrc.gov.uk';
        $this->remote_domain_test = 'https://test-api.service.hmrc.gov.uk';
        $this->remote_domain = $this->remote_domain_live;
        $this->redirect = config('extra_config.redirect_domain');//where to redirect after callback success
	    $this->debug = array();
    }

    public function connect($url, $method, $credentials = null, $extraParams = null)
    {
    	if(isset($credentials->access_token)){$token = $credentials->access_token;}else{$token = '';}
    	if(isset($extraParams->test)){$test = $extraParams->test;$this->debug['test'] = $extraParams->test;}else{$test = '-';$this->debug['test'] = 'empty';}

    	$client = new \GuzzleHttp\Client(
    		['headers' => [
			'Authorization' => 'Bearer '.$token,
			'Accept' => 'application/vnd.hmrc.1.0+json',
			'Content-Type' => 'application/json',
			'Gov-Test-Scenario' => $test
	       	]]);
    	$res = $client->request($method,$url,[\GuzzleHttp\RequestOptions::JSON => $extraParams, 'http_errors' => false]);
        $this->debug['http_code'] = $res->getStatusCode();
        $this->debug['headers'] = $res->getHeaders();
        $this->debug['reason'] = $res->getReasonPhrase(); 
	    $this->debug['body'] = $res->getBody()->getContents();
    }


    /*
     * This method is only used to accept the authorization code and 
     * exchange for access token when client signs into HMRC to give 
     * permission to this API
     */
    public function callback(Request $request)
    {
        //THIS NEEDS VALIDATING
        $code = $request->get('code');
        $state = $request->get('state');
        $error = $request->get('error');
        $error_description = $request->get('error_description');
        $error_code = $request->get('error_code');

        if(!isset($error)){
            Log::info('no error');
            $this->accessToken($code);
            return redirect($this->redirect);
        }else{//Fail getting auth code
            //return "failed";
        }
    }

    public function accessToken($code)
    {
        //callback url needs to be set in the gov website
        //client secret and client id is obtained from the gov website
        $extraParams = new \stdClass();
        $extraParams->client_secret = config('extra_config.client_secret');
        $extraParams->client_id = config('extra_config.client_id');
        $extraParams->grant_type = 'authorization_code';
        $extraParams->redirect_uri = $this->local_domain.'/callback';
        $extraParams->code = $code;
        $this->connect($this->remote_domain."/oauth/token", 'POST', 'null', $extraParams);
        $data = json_decode($this->debug['body']);

        if(isset($data->error)){
            $this->debug['accessToken'] = 'error';
        }else{
            $this->storeData($data);
        }
    }

    public function refreshToken($code)
    {
        $extraParams = new \stdClass();
        $extraParams->client_secret = config('extra_config.client_secret');
        $extraParams->client_id = config('extra_config.client_id');
        $extraParams->grant_type = 'refresh_token';
    	$extraParams->refresh_token = $code;
    	$this->connect($this->remote_domain."/oauth/token", 'POST', null, $extraParams);
    	$data = json_decode($this->debug['body']);

        if($this->debug['http_code'] == 400){
            return json_encode(array('Renew Auth' => 'User needs to Re Authorize this App. Go to sign in again'));
    	}else{
            $this->storeData($data);
    	}
    }

    public function storeData($data)
    {
        Token::truncate();
        $t = new Token;
        $t->access_token = $data->access_token;
        $t->refresh_token = $data->refresh_token;
        $t->expires_in = $data->expires_in;
        $t->scope = $data->scope;
        $t->token_type = $data->token_type;
        $t->save();
    }

    public function getCredentials()
    {
        $token = Token::find(1);        
        $now = strtotime(date('Y-m-d H:s:i'));
        $created_at = strtotime($token->created_at);
        $expire = $created_at + $token->expires_in;
 
        if($expire < $now){
            $this->refreshToken($token->refresh_token);
            $token = Token::find(1);
        }
        return $token;
    }

/*ABOVE THIS LINE ARE THE FUNCTIONS FOR CONNECTING AND STORING DATA - BELOW ARE THE METHODS FOR THE ROUTES*/

    public function validateParams($params)
    {
        $passOrFail = array();
        $paramsToValidate = array(
            '_token' => 'ignore',
            'from' => 'date',
            'to' => 'date',
            'vrn' => 'integer',
            'test' => 'string',//no spaces
            'periodKey' => 'string',//no spaces
            'status' => 'string',//no spaces
            'vatDueSales' => 'integer',
            'vatDueAcquisitions' => 'integer',
            'totalVatDue' => 'integer',
            'vatReclaimedCurrPeriod' => 'integer',
            'netVatDue' => 'integer',
            'totalValueSalesExVAT' => 'integer',
            'totalValuePurchasesExVAT' => 'integer',
            'totalValueGoodsSuppliedExVAT' => 'integer',
            'totalAcquisitionsExVAT' => 'integer',
        );

        foreach ($params as $key => $param) {
            foreach($paramsToValidate as $k => $v){
                if($key == $k){
                    if($v == 'date'){
                        $dateExploded = explode('-', $param);
                        $day = $dateExploded[2];
                        $month = $dateExploded[1];
                        $year = $dateExploded[0];
                        if(!checkdate($month, $day, $year)){
                            $passOrFail[$key] = 'fail';
                        }
                    }
                    if($v == 'integer'){
                        if(!is_numeric($param)){
                            $passOrFail[$key] = 'fail';
                        }
                    }
                    if($v == 'string'){
                        if(!preg_match("/^[A-Za-z0-9\\_\#]+$/",$param)){
                            $passOrFail[$key] = 'fail';
                        }
                    }
                }
            }
        }

        if(in_array('fail', $passOrFail)){
            return json_encode(array('validation_result' => $passOrFail));
        }
    }

/*****************************/

    public function index(){return "nothing to see here";}
    public function helloWorld(){return $this->connect($this->remote_domain."/hello/world", 'GET');}
    public function helloApplication(){return $this->connect($this->remote_domain."/hello/application", 'GET');}

    public function obligations(Request $request)
    {
        if($validate = $this->validateParams($request->all())){
            return $validate;
        }

        $extraParams = new \stdClass();
        $extraParams->test = $request->get('test');
        $status = $request->get('status');

        if($credentials = $this->getCredentials()){
            $vrn = $request->get('vrn');
            $query_string = http_build_query(array('from' => $request->get('from'), 'to' => $request->get('to'), 'status' => $status));
            $this->connect($this->remote_domain."/organisations/vat/".$vrn."/obligations?".$query_string, 'GET', $credentials, $extraParams);
        } else {
            $this->debug['getCredentials'] = 'failed';
        }
        return json_encode($this->debug);
    }

    public function viewReturn(Request $request)
    {
        if($validate = $this->validateParams($request->all())){
            return $validate;
        }

        $periodKey = $request->get('periodKey');
        $extraParams = new \stdClass();
        $extraParams->test = $request->get('test');

        if($credentials = $this->getCredentials()){
            $vrn = $request->get('vrn');
            $this->connect($this->remote_domain."/organisations/vat/".$vrn."/returns/".$periodKey, 'GET', $credentials, $extraParams);
        } else {
            $this->debug['getCredentials'] = 'failed';
        }
        return json_encode($this->debug);
    }

    public function liabilities(Request $request)
    {
        if($validate = $this->validateParams($request->all())){
            return $validate;
        }

    	$extraParams = new \stdClass();
    	$extraParams->test = $request->get('test');
        if($credentials = $this->getCredentials()){
            $vrn = $request->get('vrn');
            $query = array('from' => $request->get('from'), 'to' => $request->get('to'));
            $query_string = http_build_query($query);
            $this->connect($this->remote_domain."/organisations/vat/".$vrn."/liabilities?".$query_string, 'GET', $credentials, $extraParams);
        } else {
            $this->debug['getCredentials'] = 'failed';
        }
        return json_encode($this->debug);
    }

    public function payments(Request $request)
    {
        if($validate = $this->validateParams($request->all())){
            return $validate;
        }

        $extraParams = new \stdClass();
        $extraParams->test = $request->get('test');
        if($credentials = $this->getCredentials()){
            $vrn = $request->get('vrn');
            $query = array('from' => $request->get('from'), 'to' => $request->get('to'));
            $query_string = http_build_query($query);
            $this->connect($this->remote_domain."/organisations/vat/".$vrn."/payments?".$query_string, 'GET', $credentials, $extraParams);
        } else {
            $this->debug['getCredentials'] = 'failed';
        }
        return json_encode($this->debug);
    }

    public function submitReturn(Request $request)
    {
        if($validate = $this->validateParams($request->all())){
            return $validate;
        }

        $extraParams = new \stdClass();
        $extraParams->periodKey = $request->get('periodKey');
        $extraParams->vatDueSales = $request->get('vatDueSales');
        $extraParams->vatDueAcquisitions = $request->get('vatDueAcquisitions');
        $extraParams->totalVatDue = $request->get('totalVatDue');
        $extraParams->vatReclaimedCurrPeriod = $request->get('vatReclaimedCurrPeriod');
        $extraParams->netVatDue = $request->get('netVatDue');
        $extraParams->totalValueSalesExVAT = $request->get('totalValueSalesExVAT');
        $extraParams->totalValuePurchasesExVAT = $request->get('totalValuePurchasesExVAT');
        $extraParams->totalValueGoodsSuppliedExVAT = $request->get('totalValueGoodsSuppliedExVAT');
        $extraParams->totalAcquisitionsExVAT = $request->get('totalAcquisitionsExVAT');
        $extraParams->finalised = true;

        if($credentials = $this->getCredentials()){
            $vrn = $request->get('vrn');
            $this->connect($this->remote_domain."/organisations/vat/".$vrn."/returns", 'POST', $credentials, $extraParams);
        } else {
            $this->debug['getCredentials'] = 'failed';
        }
        return json_encode($this->debug);
    }
}