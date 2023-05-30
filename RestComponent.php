<?php
namespace Cake\Controller\Component;

use Cake\Controller\Component;
use App\Controller\AppController;
use Cake\Core\App;
use Cake\Core\Exception\Exception;
use Cake\Event\Event;
use Cake\Event\EventDispatcherTrait;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;
use Cake\Core\Configure; //
use Cake\Controller\Component\FlashComponent;
use Cake\Routing\Router;
use Datetime;
use AlexaCRM\CRMToolkit\Client as OrganizationService;
use AlexaCRM\CRMToolkit\Settings;
use AlexaCRM\CRMToolkit\Entity\EntityReference;
use AlexaCRM\CRMToolkit\Entity\MetadataCollection;

//https://docs.microsoft.com/en-us/powerapps/developer/common-data-service/webapi/query-data-web-api#bkmk_limitResults
//https://www.odata.org/documentation/odata-version-3-0/url-conventions/


class RestComponent extends Component {
var $components = array('Flash','Auth','Logging','Web');
private $serviceSettings;
private $bearer;
private $cookie;
private $isloggedin;
private $config=[];
private $apiendpoint;
private $countrycode;
private $controller;
public function startup(){
	Configure::load('siteconfig');
	$this->Users = TableRegistry::get('Users');
	$this->cookie=TMP.'cookies'.DS.'cookies.txt';
	$this->config=Configure::read('gederoffice');
	$this->countrycode=Configure::read('countrycode');
    $this->apiendpoint=(isset($this->config['apiendpoint']) && strlen($this->config['apiendpoint'])>0)?$this->config['apiendpoint']:'';
	$this->controller = $this->_registry->getController();
    try{
		$bearer=$this->getBearer();
		if($bearer===false){
			$this->bearer=$this->login();
		}else{
			$this->bearer=$bearer;
		}
	}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['message']=$e->getMessage();
	return response;
	}
 }
 
 function getBearer(){
	$bearer=false;
	$bearerArray=[];
	$cookiedata='';
	$cookiedata=@file_get_contents($this->cookie);
	if($cookiedata!='false'){
	$bearerArray=explode('Bearer',$cookiedata);
	$expireson=(isset($bearerArray[0]) && strlen(trim($bearerArray[0]))>0)?trim($bearerArray[0]):false;
		if($expireson!=false && trim($expireson)>=strtotime('now')){
			$bearer=(isset($bearerArray[1]) && strlen(trim($bearerArray[1]))>0)?trim($bearerArray[1]):false;
		}
	}
	return $bearer;
}
 /************************************************************************/
 /************************************************************************/
 /************************************************************************/
function login(){
	$bearer=false;
	$url='https://login.microsoftonline.com/'.$this->config['tenant_id'].'/oauth2/token';
	$postData=[
	'username'=>$this->config['username'],
	'password'=>$this->config['password'],
	 'resource'=>$this->config['serverUrl'],
	'grant_type'=>'password', //client_credentials
	'client_id'=>$this->config['client_id'],
	'client_secret'=>$this->config['client_secret'],
	];
	$loginResponse=$this->sendMSDynamics($url,0,false,'POST',$postData);
	if($loginResponse['status']=='success' &&(isset($loginResponse['data']['access_token']) && strlen($loginResponse['data']['access_token'])>0)){
		$bearer=$loginResponse['data']['access_token'];
		$data=$loginResponse['data']['expires_on'].' Bearer '.$loginResponse['data']['access_token'];
		$fp = fopen($this->cookie,"w");
		fwrite($fp,$data);
		fclose($fp);
		
	}
	return $bearer;
	//write to file;
} 
 /************************************************************************/
 /************************************************************************/
 /************************************************************************/
function isvalidUser($contactid,$accountid=null){
	$response=[];
	try{
	$url=$this->apiendpoint.'/contacts('.$contactid.')';
	$userResponse=$this->sendMSDynamics($url);
	if($userResponse['status']=='error'){
				$response['status']='error';
				$response['user']=[];
				$response['message']=$userResponse['message'];
			//$this->Flash->error($userResponse['message']);	
			//return $this->controller->redirect($this->Auth->logout()); //logout user in production
	}else{
		//$this->controller->set('msuser',$userResponse['data']);
		$response['status']='success';
		$response['user']=$userResponse['data'];
		$response['message']="User exists with contact id ".$contactid;
	}
	}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['user']=[];
			 $response['message']=$e->getMessage();
	}
	
	return  $response;
}
function isvalidAccount($accountid=null){
	$response=[];
	try{
	$url=$this->apiendpoint.'/accounts('.$accountid.')';
	$userResponse=$this->sendMSDynamics($url);
	if($userResponse['status']=='error'){
				$response['status']='error';
				$response['user']=[];
				$response['message']=$userResponse['message'];
			//$this->Flash->error($userResponse['message']);	
			//return $this->controller->redirect($this->Auth->logout()); //logout user in production
	}else{
		//$this->controller->set('msuser',$userResponse['data']);
		$response['status']='success';
		$response['user']=$userResponse['data'];
		$response['message']="User exists with contact id ".$contactid;
	}
	}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['user']=[];
			 $response['message']=$e->getMessage();
	}
	
	return  $response;
}
/*************************************************************************/
function contactExistsMsdynamics($email,$phone){
	$response=[];
	$response['account']=[];
	$response['contact']=[];
	$countaccount=0;
	try{	 
		if($email!='' || $phone!=''){
			$phone=preg_replace("/[^0-9]/", "",$phone); 
			$email = strtolower($email);
			//$urlaccount=$this->apiendpoint.'/accounts?$filter=name%20eq%20\''.trim($phone).'\'';
			$urlaccount=$this->apiendpoint.'/accounts?$filter=name%20eq%20\''.trim($phone).'\'%20or%20telephone1%20eq%20\''.trim($phone).'\'%20or%20new_mobilephone%20eq%20\''.trim($phone).'\'%20or%20emailaddress1%20eq%20\''.urlencode(trim($email)).'\'%20or%20emailaddress2%20eq%20\''.urlencode(trim($email)).'\'';
			$accountResponse=$this->sendMSDynamics($urlaccount);//check in account
			$countaccount=(isset($accountResponse['data']['value']) && !empty($accountResponse['data']['value']))?count($accountResponse['data']['value']):0;
			if($countaccount>0){
				foreach($accountResponse['data']['value'] AS $account){
					$accEmailMatched = false;
					$accPhoneMatched =false;
					if(strtolower($account['emailaddress1'])==$email){
						$accEmailMatched = true;
					}
					if(strtolower($account['emailaddress2'])==$email){
						$accEmailMatched = true;
					}
					if($account['name']==$phone){
						$accPhoneMatched = true;
					}
					if($account['telephone1']==$phone){
						$accPhoneMatched = true;
					}
					if($account['new_mobilephone']==$phone){
						$accPhoneMatched = true;
					}
					if($accEmailMatched && $accPhoneMatched){
						$response['account']=[
							'accountid'=>$account['accountid'],
							'email'=>$account['emailaddress1'],
							'phonenumber'=>$account['telephone1'],
							'address1_line1'=>$account['address1_line1'],
							'address1_line2'=>$account['address1_line2'],
							'address1_line3'=>$account['address1_line3'],
							'address1_city'=>$account['address1_city'],
							'address1_stateorprovince'=>$account['address1_stateorprovince'],
							'address1_county'=>$account['address1_county'],
							'address1_postalcode'=>$account['address1_postalcode'],
						];
						break;
					}else{
						$response['account']=[];
					}
				}
				$response['status']='error';
				$response['message']=__('Record Exists');	

			}else{
				$response['status']='success';
				$response['message']=__('No such record exists');
			}
			
		}else{
				$response['status']='error';
				$response['message']=__('Invalid request Parameters');
		}
	}catch (\Exception $e){ 
			$response['status']='error';
			$response['message']=$e->getMessage();
	}
	return $response;
}



function getDeviceData($accountid,$limit=0){
	$response=[];
	try{
	//$url=$this->apiendpoint.'/new_deviceservices?$orderby=createdon&$expand=new_device,new_servicetype,new_mastermodel,new_devicemodel&$filter=_new_account_value%20eq%20'.$accountid.'%20and%20new_recuring%20eq%20true%20and%20statecode%20eq%200%20and%20(new_servicestatus%20eq%20100000011%20or%20new_servicestatus%20eq%20100000010%20or%20new_servicestatus%20eq%20100000012)'; 
	
	$url=$this->apiendpoint.'/new_deviceservices?$orderby=createdon&$expand=new_device,new_servicetype,new_mastermodel,new_devicemodel&$filter=_new_account_value%20eq%20'.$accountid.'%20and%20new_recuring%20eq%20true%20and%20statecode%20eq%200%20and%20(new_servicestatus%20eq%20100000010%20or%20new_servicestatus%20eq%20100000000%20or%20new_servicestatus%20eq%20100000003)'; 
	
	//statecode=active
	//new_servicestatus=100000010 =web
	//new_servicestatus=100000000 =new
	//new_servicestatus=100000003 =completed
		if($limit>0){
	$url.='&$top='.$limit;	
	}

	$deviceResponse=$this->sendMSDynamics($url);
	
	if($deviceResponse['status']=='error'){
		     $response['status']='error';
			 $response['data']=[];
			 $response['message']=$deviceResponse['message'];
	}else{
		$response['status']='success';
		$response['data']=(isset($deviceResponse['data']['value']) && !empty($deviceResponse['data']['value']))?$deviceResponse['data']['value']:[];
		$response['message']="Device data exists for account id ".$accountid;
	}
	}catch (\Exception $e){ 
	         $response['status']='error';
			  $response['data']=[];
			 $response['message']=$e->getMessage();
	}
	
	return  $response;
	
} 
 
function getAccessRequestData($accountid,$limit=0){
	$response=[];
	try{
	$url=$this->apiendpoint.'/new_accessrequests?$expand=new_device&$orderby=createdon%20desc&$filter=_new_account_value%20eq%20'.$accountid.'';
	//maxpagesize
	//count
	//RetrieveTotalRecordCount
	///api/data/v9.1/accounts/$count
	
	$accessrequestsResponse=$this->sendMSDynamics($url,$limit,true);
	if($accessrequestsResponse['status']=='error'){
		     $response['status']='error';
			 $response['data']=[];
			 $response['message']=$accessrequestsResponse['message'];
	}else{
		$response['status']='success';
		$response['previouslink']=(isset($accessrequestsResponse['previouslink']) && !empty($accessrequestsResponse['previouslink']))?$accessrequestsResponse['previouslink']:'';
		$response['nextlink']=(isset($accessrequestsResponse['nextlink']) && !empty($accessrequestsResponse['nextlink']))?$accessrequestsResponse['nextlink']:'';
		$response['data']=(isset($accessrequestsResponse['data']['value']) && !empty($accessrequestsResponse['data']['value']))?$accessrequestsResponse['data']['value']:[];
		
		$response['message']="Access requests exists for account id ".$accountid;
	}
	}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['data']=[];
			 $response['message']=$e->getMessage();
	}
	
	return  $response;
}

function getPaymentmethodData($accountid,$limit=0,$isDefaultOnly=false){
	$response=[];
	try{
	$url=$this->apiendpoint.'/po_creditcards?$orderby=createdon&$filter=_po_accountid_value%20eq%20'.$accountid.'';
	if($isDefaultOnly){
		$url.='%20and%20po_default%20eq%20true';
	}
	$paymentmethodResponse=$this->sendMSDynamics($url);
	if($paymentmethodResponse['status']=='error'){
		     $response['status']='error';
			 $response['data']=[];
			 $response['message']=$paymentmethodResponse['message'];
	}else{
		$response['status']='success';
		$response['previouslink']=(isset($paymentmethodResponse['previouslink']) && !empty($paymentmethodResponse['previouslink']))?$paymentmethodResponse['previouslink']:'';
		$response['nextlink']=(isset($paymentmethodResponse['nextlink']) && !empty($paymentmethodResponse['nextlink']))?$paymentmethodResponse['nextlink']:'';
		$response['data']=(isset($paymentmethodResponse['data']['value']) && !empty($paymentmethodResponse['data']['value']))?$paymentmethodResponse['data']['value']:[];
		
		$response['message']="Payment methods exists for account id ".$accountid;
	}
	}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['data']=[];
			 $response['message']=$e->getMessage();
	}
	
	return  $response;
} 
function getActivityData($accountid,$limit=0){
	$response=[];
	try{
	$url=$this->apiendpoint.'/new_registers?$orderby=modifiedon&$filter=_new_account_value%20eq%20'.$accountid.'%20and%20new_type%20eq%20100000000';
	$activitiesResponse=$this->sendMSDynamics($url,$limit,true);
	if($activitiesResponse['status']=='error'){
		     $response['status']='error';
			 $response['data']=[];
			 $response['message']=$activitiesResponse['message'];
	}else{
		$response['status']='success';
		$response['previouslink']=(isset($activitiesResponse['previouslink']) && !empty($activitiesResponse['previouslink']))?$activitiesResponse['previouslink']:'';
		$response['nextlink']=(isset($activitiesResponse['nextlink']) && !empty($activitiesResponse['nextlink']))?$activitiesResponse['nextlink']:'';
		$response['data']=(isset($activitiesResponse['data']['value']) && !empty($activitiesResponse['data']['value']))?$activitiesResponse['data']['value']:[];
		
		$response['message']="Activities exists for account id ".$accountid;
	}
	}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['data']=[];
			 $response['message']=$e->getMessage();
	}
	
	return  $response;
}

function getDefaultPaymentmethod($accountid){
	$response=[];
	$paymentmethod=[];
	try{
	$url=$this->apiendpoint.'/po_creditcards?$orderby=createdon&$filter=_po_accountid_value%20eq%20'.$accountid.'%20and%20po_default%20eq%20true';
	$paymentmethodResponse=$this->sendMSDynamics($url);
	
	$paymentmethod=(isset($paymentmethodResponse['data']['value'][0]) && !empty($paymentmethodResponse['data']['value'][0]))?$paymentmethodResponse['data']['value'][0]:[];

	if($paymentmethodResponse['status']=='error' || empty($paymentmethod)){
		     $response['status']='error';
			 $response['data']=[];
			 $response['message']=$paymentmethodResponse['message'];
	}else{
		$response['status']='success';
		$response['data']=$paymentmethod;
		$response['message']="Payment methods exists for account id ".$accountid;
	}
	}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['data']=[];
			 $response['message']=$e->getMessage();
	}
	
	return  $response;
}
function isValidPaymentmethod($accountid,$paymentmethodid){
	$response=[];
	$paymentmethod=[];
	try{
	$url=$this->apiendpoint.'/po_creditcards?$orderby=createdon&$filter=_po_accountid_value%20eq%20'.$accountid.'%20and%20po_creditcardid%20eq%20'.$paymentmethodid;
	$paymentmethodResponse=$this->sendMSDynamics($url);
	
	$paymentmethod=(isset($paymentmethodResponse['data']['value'][0]) && !empty($paymentmethodResponse['data']['value'][0]))?$paymentmethodResponse['data']['value'][0]:[];

	if($paymentmethodResponse['status']=='error' || empty($paymentmethod)){
		     $response['status']='error';
			 $response['data']=[];
			 $response['message']=$paymentmethodResponse['message'];
	}else{
		$response['status']='success';
		$response['data']=$paymentmethod;
		$response['message']="Payment methods exists for account id ".$accountid;
	}
	}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['data']=[];
			 $response['message']=$e->getMessage();
	}
	
	return  $response;
}


function sendMSDynamics($url,$limit=0,$paginate=false,$type='GET',$postData=[]){
$response=[];
$params=[];
$httpcode=400; //error error
try{
$previouslink=$this->request->getRequestTarget();
$params=$this->request->getQueryParams();
$url=($paginate && isset($params['page']) && strlen($params['page'])>0)?urldecode($params['page']):$url;
//echo $url;
$ch = curl_init();	

/*
$this->contactid='00c7d7c4-de83-e511-80e4-3863bb3489e0';
$url='https://gederoffice.api.crm.dynamics.com/api/data/v9.1/accounts?$filter=accountid%20eq%2078F2CBC0-DE83-E511-80E7-3863BB2EF238';
$url='https://gederoffice.api.crm.dynamics.com/api/data/v9.1/contacts?$filter=contactid%20eq%2000c7d7c4-de83-e511-80e4-3863bb3489e0';
$url='https://gederoffice.api.crm.dynamics.com/api/data/v9.1/new_devices?$filter=new_devicecontact%20eq%2000c7d7c4-de83-e511-80e4-3863bb3489e0';
*/
$headers= array("Authorization:Bearer ".$this->bearer."");
if($limit!=0){
	$additionalheaders=['Prefer: odata.maxpagesize='.$limit.''];
	$headers=array_merge($headers,$additionalheaders);
}
//pr($headers);
//$this->Logging->logData($url,'msdynamicsRestLogs');
if($type=='GET'){
curl_setopt_array($ch, array(
CURLOPT_URL => $url,
CURLOPT_RETURNTRANSFER => true,
CURLOPT_ENCODING => "",
CURLOPT_MAXREDIRS => 10,
CURLOPT_TIMEOUT => 0,
CURLOPT_FOLLOWLOCATION => true,
CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
CURLOPT_CUSTOMREQUEST => $type,
CURLOPT_HTTPHEADER => $headers,
));
}
else if($type=='JSON'){
	$headers[]='Content-Type:application/json';
	curl_setopt_array($ch, array(
		CURLOPT_URL => $url,
		CURLOPT_POST=>true,
		CURLOPT_POSTFIELDS=>json_encode($postData),
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_HEADER=>1,
		CURLOPT_HTTPHEADER => $headers,
		));	
}else{
	
curl_setopt_array($ch, array(
CURLOPT_URL => $url,
CURLOPT_POST=>true,
CURLOPT_POSTFIELDS=>http_build_query($postData),
CURLOPT_RETURNTRANSFER => true,
CURLOPT_ENCODING => "",
CURLOPT_MAXREDIRS => 10,
CURLOPT_TIMEOUT => 0,
CURLOPT_FOLLOWLOCATION => true,
CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
CURLOPT_CUSTOMREQUEST => $type,
CURLOPT_HTTPHEADER => $headers,
));	
}





		$responseCurl = curl_exec($ch);

		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$resHeader = substr($responseCurl, 0, $header_size);
		//$this->Logging->logData($responseCurl,'msdynamicsRestLogs'); //input request logging
		
		$responseError= curl_error($ch); 
		
		if(trim($responseError)!=''){
			 $response['status']='error';
			 $response['data']=[];
			 $response['header'] = [];
			 $response['message']=$responseError;
			 return  $response;
		}
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
		//die();
		//curl_close($ch);
		//pr($responseCurl);
		
	//	die();
		$responseData=json_decode($responseCurl,true);
		//$this->Logging->logData($responseData,time().'-requestlogs'); //input request logging
		//echo $responseData;
		
		//die();
		if(json_last_error()!=JSON_ERROR_NONE){
			$response['status']='error';
			$response['statuscode']=$httpcode;
			$response['data']=[];
			$response['header'] = $resHeader;
			$response['message']=__('Empty or Invalid json response');
		    return $response;
		
		}
		
		if(isset($responseData['error']) && !empty($responseData['error'])){
			$response['status']='error';
			$response['statuscode']=$httpcode;
			$response['data']=[];
			$response['header'] = $resHeader;
			$response['message']='Error: '.@$responseData['error']['code'].'. '.@$responseData['error']['message'];
			
		}else{
			$response['status']='success';
			$response['previouslink']=$previouslink;
			$response['data']=$responseData;
			$response['statuscode']=$httpcode;
			$response['header'] = $resHeader;
			$response['nextlink']=(isset($responseData['@odata.nextLink']) && !empty($responseData['@odata.nextLink']))?urlencode($responseData['@odata.nextLink']):'';
			$response['message']=__('Request Successful');
		}
		
}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['statuscode']=$httpcode;
			 $response['header'] = [];
			 $response['message']=$e->getMessage();
	}
return  $response;
}
public function getTEmplateById($ID){
	$templateInfo = $this->sendMSDynamics($this->apiendpoint.'/templates?$filter=templateid%20eq%20'.$ID,0,false,'GET');
	if(isset($templateInfo['data']['value'][0])){
		$template = $templateInfo['data']['value'][0];
		return [
			'body'=>$template['safehtml'],
			'subject'=>$template['subjectsafehtml'],
		];

	}
	return [];
	
}
function sendEmailViaCrm($content,$subject,$to,$bcc=false){
	$postData = [
		'description' => str_replace(["\r","\n","\r\n"],"",$content),
		'subject' => $subject,
		'email_activity_parties' => [
		  0 => [
			'addressused' => 'no-reply@geder.org',
			'participationtypemask' => 1,
		  ],
		  1 => [
			'addressused' => $to,
			'participationtypemask' => 2,
		  ],
		],
	];
	if($bcc!=false){
		$postData['email_activity_parties'][]=[
			'addressused' => $bcc,
			'participationtypemask' => 4,
		];
	}
	
	$response = $this->sendMSDynamics($this->apiendpoint.'/emails',0,false,'JSON',$postData);
	$headers = explode("\r\n",$response['header']);
	$xUrl = "";
	if(!empty($headers)){
		foreach($headers as $header){
			if(strpos($header,"OData-EntityId")!==false){
				$xUrl = str_replace("OData-EntityId:","",$header);
			}
		}
	}
	if($xUrl!=""){
		$data  = $this->sendMSDynamics(trim($xUrl."/Microsoft.Dynamics.CRM.SendEmail?"),0,false,'JSON',["IssueSend"=>true]);
		return true;
	}
	
	return false;
}

function sendEmailUsingTemplateViaCrmToContact($contactID,$tID,$accountID=""){
	
	$postReq =  [
		'TemplateId' => $tID,//Configure::read('LivEmailTemplateId'),//'B93EC757-DABE-E911-A98C-000D3A363879',
		'Regarding' => [
			'accountid' => $accountID,//9282ba68-2d46-eb11-a813-000d3a378c4b',
			'@odata.type' => 'Microsoft.Dynamics.CRM.account',
		],
		'Target' =>   [
			'subject' => '',
			'description' => '',
			'email_activity_parties' =>[
			  '0' => [
					'addressused' => 'no-reply@geder.org',
					'participationtypemask' => 1,
				],
			  '1' => [
					'partyid_contact@odata.bind' => '/contacts('.$contactID.')',
					'participationtypemask' => 2,
				],
			],
			'@odata.type' => 'Microsoft.Dynamics.CRM.email',
		],
	];
	try{
		$data = $this->sendMSDynamics($this->apiendpoint."/SendEmailFromTemplate",0,false,'JSON',$postReq);
		if($data && $data != ''){
			$exData = json_decode($data,1);

		}

	}catch (\Exception $e){ 
			 $response['status']='error';
			 $response['message']=$e->getMessage();
	return response;
	}
	
	
	
}
function getTransactionStatusById($id){
	$responseData = $this->sendMSDynamics($this->apiendpoint.'/po_creditcardtransactions?$filter=activityid%20eq%20'.$id,0,false,'GET');
	if(isset($responseData['data']['value'][0])){
		$chargeData = $responseData['data']['value'][0];
		if(!empty($chargeData) && isset($chargeData['po_transactionresult']) && $chargeData['po_transactionresult']!=""){
			$chargeValues = explode(",",$chargeData['po_transactionresult']);
			if(isset($chargeValues[1])){
				$chargeStatus= explode(":",$chargeValues[1]);
				if($chargeStatus[1]==1){
					return true;
				}
			}
		}
	}
	return false;
}
}//component class ends