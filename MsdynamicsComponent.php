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



class MsdynamicsComponent extends Component {
var $components = array('Flash','Auth');
private $serviceSettings;


 public function startup(){
	$this->Users = TableRegistry::get('Users');
	Configure::load('siteconfig');
	$gederoffice=Configure::read('gederoffice');
	$options = [
    'serverUrl' => $gederoffice['serverUrl'],
    'username' => $gederoffice['username'],
    'password' => $gederoffice['password'],
    'authMode' => $gederoffice['authMode'],
	
];
    try{
	$this->serviceSettings = new Settings( $options );
	//$this->service = new OrganizationService( $serviceSettings ); //makes site slow
	}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['message']=$e->getMessage();
	return response;
	}
 }

function contactExists($email){
 $response=[];
 $contact=[];
try{	 
	 $service =new OrganizationService( $this->serviceSettings );
	 $metadata       = MetadataCollection::instance( $service );
	// $contacts = $service->retrieveMultipleEntities("contact", $allPages = false, $pagingCookie = null, $limitCount = 10, $pageNumber = 1, $simpleMode = false);
	
	$fetchxml = "<fetch version='1.0' output-format='xml-platform' mapping='logical' distinct='false'>
  <entity name='contact'>
    <attribute name='accountid' />
    <attribute name='firstname' />
    <attribute name='lastname' />
    <attribute name='emailaddress1' />
    <attribute name='mobilephone' />
    <order attribute='firstname' descending='false' />
    <filter type='and'>
      <condition attribute='emailaddress1' operator='eq' value='".trim($email)."' />
    </filter>
  </entity>
</fetch>";

$contacts = $service->retrieveMultiple($fetchxml, $allPages = false, $pagingCookie = null, $limitCount = 1, $pageNumber = 1, $simpleMode = false);
$rowcount=($contacts->Count)?$contacts->Count:0;

if($rowcount>0){
   
				$contact=[
				        'accountid'=>$contacts->Entities[0]->accountid,
						'contactid'=>$contacts->Entities[0]->contactid,
						'email'=>$contacts->Entities[0]->emailaddress1,
						'phonenumber'=>$contacts->Entities[0]->mobilephone,
						];
			 $response['status']='success';
			 $response['count']=$rowcount;
			 $response['contact']=$contact;
			 $response['message']=__('Record Exists');
	
}else{
			 $response['status']='success';
			 $response['count']=$rowcount;
			 $response['contact']=$contact;
			 $response['message']=__('No such record exists');
	
}

}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['count']=0;
			 $response['contact']=$contact;
			 $response['message']=$e->getMessage();
	
	}
return $response;
}
function operateContact($accountinfo,$existingaccountinfo=[]){
	$response=[];
	try{
		if(empty($existingaccountinfo['contact'])){
		    $response=$this->createContact($accountinfo);
		}else{
			$response=$this->updateContact($accountinfo,$existingaccountinfo['contact']);
		}
		
	}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['contactId']=null;
			 $response['message']=$e->getMessage();
	
	}
return $response;
	
}
function createContact($accountinfo){
	 $response=[];
	 try{
	
				$service =new OrganizationService( $this->serviceSettings );
				$contact = $service->entity( 'contact' );
				$contact->emailaddress1 = $accountinfo['email'];
				$contact->description=(isset($accountinfo['companyname']) && !empty($accountinfo['companyname']))?'Company name: '.trim($accountinfo['companyname']):'';
				$contact->firstname = $accountinfo['firstname'];
				$contact->lastname = $accountinfo['lastname']; 
				
				
				$contact->address1_line1 =(isset($accountinfo['address1']) && strlen($accountinfo['address1'])>0)?$accountinfo['address1']:''; 
				$contact->address1_line2 = (isset($accountinfo['address2']) && strlen($accountinfo['address2'])>0)?$accountinfo['address2']:''; 
				$contact->address1_city = (isset($accountinfo['city']) && strlen($accountinfo['city'])>0)?$accountinfo['city']:'';
				$contact->address1_stateorprovince=(isset($accountinfo['state']) && strlen($accountinfo['state'])>0)?$accountinfo['state']:'';
				$contact->address1_postalcode = (isset($accountinfo['zip']) && strlen($accountinfo['zip'])>0)?$accountinfo['zip']:''; 
				
				
				$contact->jobtitle = ucfirst($accountinfo['signuptype']).' Account';
				$contact->mobilephone = $accountinfo['phonenumber'];
				$contactId = $contact->create();
				
				
				if($contactId){
					$response['status']='success';
					$response['contactId']=$contactId;
					$response['message']=__('Contact created');
				}else{
					$response['status']='error';
					$response['contactId']=null;
					$response['message']=__('Unable to create contact');
				}
	 }catch (\Exception $e){ 
	         $response['status']='error';
			 $response['contactId']=null;
			 $response['message']=$e->getMessage();
			
	}	
	return $response; 
 }
 
 function updateContact($accountinfo,$existingaccountinfo){
	$response=[];
	 try{
 	            $contactId=(isset($existingaccountinfo['contactid']))?trim($existingaccountinfo['contactid']):'';
				$service =new OrganizationService( $this->serviceSettings );
				$contact = $service->entity( 'contact', $contactId );
				
				if(isset($accountinfo['accountid']) && !empty($accountinfo['accountid'])){
				$contact->description=trim($accountinfo['accountid']);
				}
				
				
				if(isset($accountinfo['companyname']) && !empty($accountinfo['companyname'])){
				$contact->description='Company name: '.trim($accountinfo['companyname']);
				}
				
				if(isset($accountinfo['firstname']) && !empty($accountinfo['firstname'])){
				$contact->firstname = $accountinfo['firstname'];
				
				}
				
				if(isset($accountinfo['lastname']) && !empty($accountinfo['lastname'])){
				$contact->lastname = $accountinfo['lastname']; 
				}
				
				if(isset($accountinfo['address1']) && !empty($accountinfo['address1'])){
				$contact->address1_line1 =$accountinfo['address1']; 
				}
				
				if(isset($accountinfo['address2']) && !empty($accountinfo['address2'])){
				$contact->address1_line2 = $accountinfo['address2'];
				}
				
				if(isset($accountinfo['address3']) && !empty($accountinfo['address3'])){
				$contact->address1_line3 = $accountinfo['address3'];
				}

				if(isset($accountinfo['city']) && !empty($accountinfo['city'])){
				$contact->address1_city = $accountinfo['city'];
				}				
				
				if(isset($accountinfo['state']) && !empty($accountinfo['state'])){
				$contact->address1_stateorprovince=$accountinfo['state']; 
				}
				
				if(isset($accountinfo['zip']) && !empty($accountinfo['zip'])){
				$contact->address1_postalcode =$accountinfo['zip'];
				}
				if(isset($accountinfo['country']) && !empty($accountinfo['country'])){
				$contact->address1_country =$accountinfo['country'];
				}
				
				
				if(isset($accountinfo['signuptype']) && !empty($accountinfo['signuptype'])){
				$contact->jobtitle = $accountinfo['signuptype'].' Account';
				}
				
				if(isset($accountinfo['phonenumber']) && !empty($accountinfo['phonenumber'])){
				$contact->mobilephone = $accountinfo['phonenumber'];
				}
				
				if($contact->update()){
					$response['status']='success';
					$response['contactId']=$contactId;
					$response['message']=__('Contact updated');
				}else{
					$response['status']='error';
					$response['contactId']=$contactId;
					$response['message']=__('Unable to update contact');
				}

	 }catch (\Exception $e){ 
	         $response['status']='error';
			 $response['contactId']=null;
			 $response['message']=$e->getMessage();
			
	}	
	return $response; 
 }
 
 function deleteContact($contactId){
	 $service =new OrganizationService( $this->serviceSettings );
	 $contact = $service->entity( 'contact', $contactId );
	 $contact->delete();
 }



function operateAccount($accountinfo,$existingaccountinfo=[]){
	$response=[];
	try{
		if(empty($existingaccountinfo['account'])){
		    $response=$this->createAccount($accountinfo);
		}else{
			$response=$this->updateAccount($accountinfo,$existingaccountinfo['account']);
		}
		
	}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['contactId']=null;
			 $response['message']=$e->getMessage();
	
	}
return $response;
	
}

 function createAccount($accountinfo){
				$response=[];
				
				//pr($accountinfo);
				//die();
				try{         
					$service =new OrganizationService( $this->serviceSettings );
					$account = $service->entity( 'account' );
					$account->name =preg_replace("/[^0-9]/", "",$accountinfo['phonenumber']);
					
					$account->new_businessunit = new EntityReference('businessunit',$accountinfo['location']);
					$account->new_business=(isset($accountinfo['signuptype']) && !empty($accountinfo['signuptype']) && ($accountinfo['signuptype']=='enterprise' || $accountinfo['signuptype']=='business'))?'100000000':'100000001';
					$account->new_firstname = $accountinfo['firstname'];
					$account->new_lastname = $accountinfo['lastname'];
					$account->new_company=(isset($accountinfo['companyname']) && !empty($accountinfo['companyname']))?trim($accountinfo['companyname']):'';
					
					$account->address1_line1 = (isset($accountinfo['address1']) && strlen($accountinfo['address1'])>0)?$accountinfo['address1']:''; 
					$account->address1_line2 = (isset($accountinfo['address2']) && strlen($accountinfo['address2'])>0)?$accountinfo['address2']:''; 
					$account->address1_city = (isset($accountinfo['city']) && strlen($accountinfo['city'])>0)?$accountinfo['city']:''; 
					$account->address1_stateorprovince=(isset($accountinfo['state']) && strlen($accountinfo['state'])>0)?$accountinfo['state']:''; 
					$account->address1_postalcode = (isset($accountinfo['zip']) && strlen($accountinfo['zip'])>0)?$accountinfo['zip']:'';
					
					
					$account->telephone1 = $accountinfo['phonenumber'];
					$account->emailaddress1 = $accountinfo['email'];
					//$account->new_business = '100000001';
					$accountid = $account->create();
					if($accountid){
					$response['status']='success';
					$response['accountid']=$accountid;
					$response['message']=__('Account created');
				}else{
					$response['status']='error';
					$response['accountid']=null;
					$response['message']=__('Unable to create account');
				}

		}catch (\Exception $e){ 
	          $response['status']='error';
			  $response['accountid']=null;
			  $response['message']=$e->getMessage();
			
	}			
	 return $response;
 }
 
 
 function updateAccount($accountinfo,$existingaccountinfo){
				$response=[];
				
				//pr($accountinfo);
				//die();
				try{   
					$accountid=(isset($existingaccountinfo['accountid']))?trim($existingaccountinfo['accountid']):'';	

					$service =new OrganizationService( $this->serviceSettings );
					$account = $service->entity( 'account' ,$accountid);
					
					if(isset($accountinfo['phonenumber']) && !empty($accountinfo['phonenumber'])){
					$account->name = preg_replace("/[^0-9]/", "",$accountinfo['phonenumber']);;
					}
					
					if(isset($accountinfo['location']) && !empty($accountinfo['location'])){
					$account->new_businessunit = new EntityReference('businessunit',$accountinfo['location']);
					}
					
					if(isset($accountinfo['signuptype']) && !empty($accountinfo['signuptype'])){
					$account->new_business=($accountinfo['signuptype']=='business' || $accountinfo['signuptype']=='enterprise')?100000000:100000001;
					}
					if(isset($accountinfo['firstname']) && !empty($accountinfo['firstname'])){
					$account->new_firstname = $accountinfo['firstname'];
					}
					
					if(isset($accountinfo['lastname']) && !empty($accountinfo['lastname'])){
					$account->new_lastname = $accountinfo['lastname'];
					}
					
					if(isset($accountinfo['companyname']) && !empty($accountinfo['companyname'])){
					$account->new_company=trim($accountinfo['companyname']);
					}
					
					if(isset($accountinfo['address1']) && !empty($accountinfo['address1'])){
					$account->address1_line1 = $accountinfo['address1']; 
					}
					
					if(isset($accountinfo['address2']) && !empty($accountinfo['address2'])){
					$account->address1_line2 = $accountinfo['address2']; 
					}
					
					if(isset($accountinfo['address3']) && !empty($accountinfo['address3'])){
					$account->address1_line3 = $accountinfo['address3']; 
					}
					
					
					if(isset($accountinfo['city']) && !empty($accountinfo['city'])){
					$account->address1_city = $accountinfo['city']; 
					}
					if(isset($accountinfo['state']) && !empty($accountinfo['state'])){
					$account->address1_stateorprovince=$accountinfo['state']; 
					}
					if(isset($accountinfo['zip']) && !empty($accountinfo['zip'])){
					$account->address1_postalcode = $accountinfo['zip'];
					}
					
					if(isset($accountinfo['country']) && !empty($accountinfo['country'])){
					$account->address1_country =$accountinfo['country'];
					}
					
					
					if(isset($accountinfo['phonenumber']) && !empty($accountinfo['phonenumber'])){
					$account->telephone1 = $accountinfo['phonenumber'];
					}
					if(isset($accountinfo['email']) && !empty($accountinfo['email'])){
					$account->emailaddress1 = $accountinfo['email'];
					}
					if(isset($accountinfo['new_accountnotes']) && !empty($accountinfo['new_accountnotes'])){
						$account->new_accountnotes = $accountinfo['new_accountnotes'];
					}

					if($account->update()){
					$response['status']='success';
					$response['accountid']=$accountid;
					$response['message']=__('Account updated');
				}else{
					$response['status']='error';
					$response['accountid']=null;
					$response['message']=__('Unable to update account');
				}

		}catch (\Exception $e){ 
	          $response['status']='error';
			  $response['accountid']=null;
			  $response['message']=$e->getMessage();
			
	}			
	 return $response;
 }
  function deleteAccount($accountId){
	 $service =new OrganizationService( $this->serviceSettings );
	 $account = $service->entity( 'account', $accountId );
	 $account->delete();
 }
   function deletePaymentmethod($methodId){
	 $service =new OrganizationService( $this->serviceSettings );
	 $method = $service->entity( 'po_creditcard', $methodId );
	 //po_creditcardstatus
	 $method->delete();
 }
 function setdefaultPaymentmethod($paymentmethodid,$accountid,$defaultcardid=null){
	// echo $paymentmethodid;
	// echo "<br/>";
	// echo $accountid;
	// echo "<br/>";
	// echo $defaultcardid;
	 
	 //die();
	  $response=[];
				try{         
					$service =new OrganizationService( $this->serviceSettings );
					$account = $service->entity( 'account',$accountid);
					$account->new_defaultcreditcard = new EntityReference('po_creditcard',$paymentmethodid);
					
					$card=$service->entity( 'po_creditcard',$paymentmethodid);
					$card->po_default=1;
				
					
					if($account->update() && $card->update()){
					if($defaultcardid!='null'){
					$defaultcard=$service->entity( 'po_creditcard',$defaultcardid);
					$defaultcard->po_default=0;
					$defaultcard->update();
					}
					
					$response['status']='success';
					$response['paymentmethodid']=$paymentmethodid;
					$response['message']=__('Credit card updated');
				}else{
					$response['status']='error';
					$response['paymentmethodid']=$paymentmethodid;
					$response['message']=__('Unable to update credit card');
				}
			}catch (\Exception $e){ 
	          $response['status']='error';
			  $response['paymentmethodid']=null;
			  $response['message']=$e->getMessage();
			
	}	
//pr($response);

//die();	
	 return $response;		
 }
  /*****************************************************************************************************************************/
   /*****************************************************************************************************************************/
 function registerAmount($accountId,$amount,$type='100000000'){ //100000000=Invoice 100000002=payment
	 try{
		// echo $accountId;
		// echo '<br/>';
		// echo $amount;
		// echo '<br/>';
	
	  $service =new OrganizationService( $this->serviceSettings );
	  $register = $service->entity( 'new_register');
	  //$register->new_name= 
	  $register->new_account = new EntityReference('account',$accountId);
	  $register->new_date = (new \DateTIme())->getTimeStamp();
	  $register->new_type=$type;
	  $register->new_amount = $amount;
	 //$register->new_type = 100000003;
	  $register->new_reccuring= 0;
	  //print_r($register);
	  $registerid = $register->create();
	  
	  if($registerid){
					$response['status']='success';
					$response['registerid']=$registerid;
					$response['message']=__('Register created');
				}else{
					$response['status']='error';
					$response['registerid']=null;
					$response['message']=__('Unable to create register');
				}
		}catch (\Exception $e){ 
	          $response['status']='error';
			  $response['registerid']=null;
			  $response['message']=$e->getMessage();
			
	}			
	 return $response;
 }
 /*****************************************************************************************************************************/
 function processCreditcard($data){
	 
		 try{
				   $service =new OrganizationService( $this->serviceSettings );
			       $cardresponse=$this->cardExists($service,$data['number'],$data['contactid'],$data['accountid']);
				   if($cardresponse['count']>0){
					   $data['paymentmethodid']=$cardresponse['paymentmethodid'];
					   return $this->updateCreditcard($service,$data);
				   }else{
					   
					   return $this->createCreditcard($service,$data);
				   }
				    
	 }catch (\Exception $e){ 
	         $response['status']='error';
			 $response['paymentmethodid']=null;
			 $response['message']=$e->getMessage();
			
	}
	 return $response;
 }
 function isActiveModel($modelid){
	 
	 
 }
 function cardExists($service,$number,$contactid,$accountid){
 $response=[];
 $contact=[];
try{	 
	 
	$fetchxml = "<fetch version='1.0' output-format='xml-platform' mapping='logical' distinct='false'>
  <entity name='po_creditcard'>
    <filter type='and'>
      <condition attribute='po_contactid' operator='eq' value='".trim($contactid)."' />
	  <condition attribute='po_accountid' operator='eq' value='".trim($accountid)."' />
	  <condition attribute='po_number' operator='eq' value='".trim($number)."' />
    </filter>
  </entity>
</fetch>";

$paymentmethods = $service->retrieveMultiple($fetchxml, $allPages = false, $pagingCookie = null, $limitCount = 1, $pageNumber = 1, $simpleMode = false);
$rowcount=($paymentmethods->Count)?$paymentmethods->Count:0;

if($rowcount>0){
			 $response['status']='success';
			 $response['count']=$rowcount;
			 $response['paymentmethodid']=$paymentmethods->Entities[0]->ID;
			 $response['message']=__('Record Exists');
	
}else{
			 $response['status']='success';
			 $response['count']=$rowcount;
			 $response['paymentmethodid']=null;
			 $response['message']=__('No such record exists');
	
}

}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['count']=0;
			 $response['paymentmethodid']=null;
			 $response['message']=$e->getMessage();
	
	}
return $response;
}
function updateCreditcard($service,$data){
	 $response=[];
	try{
		           $paymentmethodid=(isset($data['paymentmethodid']))?trim($data['paymentmethodid']):'';
				    $cc = $service->entity( 'po_creditcard', $paymentmethodid);
				    $cc->po_type = $data['cardtype'];
				 	$cc->po_name = $data['name'];
					$cc->po_accountid = new EntityReference('account',$data['accountid']);
					$cc->po_billtoname = $data['name'];
					$cc->po_street1 =  $data['street1'];
					$cc->po_street2 = $data['street2'];
					$cc->po_city = $data['city'];
					$cc->po_state = $data['state'];
					$cc->po_zip = $data['zip'];
					$cc->po_country = $data['country'];
				if($cc->update()){
					$response['status']='success';
					$response['paymentmethodid']=$paymentmethodid;
					$response['message']=__('Credit card updated');
				}else{
					$response['status']='error';
					$response['paymentmethodid']=$paymentmethodid;
					$response['message']=__('Unable to update credit card');
				}
	 }catch (\Exception $e){ 
	         $response['status']='error';
			 $response['paymentmethodid']=null;
			 $response['message']=$e->getMessage();
			
	}
	 return $response;
}
function createCreditcard($service,$data){
	 $response=[];
		 try{
					$cc = $service->entity( 'po_creditcard' );
					$cc->po_contactid =new EntityReference('contact',$data['contactid']);
					$cc->po_type = $data['cardtype'];
					$cc->po_name = $data['name']."-".substr($data['number'],-4);
					$cc->po_number = $data['number']; 
					$cc->po_expmonth = $data['expmonth'];
					$cc->po_expyear = ((strlen($data['expyear'])>2)?substr($data['expyear'],-2):$data['expyear']);
					$cc->po_ccv = $data['ccv'];
					$cc->po_accountid = new EntityReference('account',$data['accountid']);
					$cc->po_billtoname = $data['name'];
					$cc->po_street1 =  $data['street1'];
					$cc->po_street2 = $data['street2'];
					$cc->po_city = $data['city'];
					$cc->po_state = $data['state'];
					$cc->po_zip = $data['zip'];
					$cc->po_country = $data['country'];
					$ccid = $cc->create();
					if($ccid){
					   $response['status']='success';
					   $response['paymentmethodid']=$ccid;
					   $response['message']=__('Payment method created');
					}else{
						$response['status']='error';
						$response['paymentmethodid']=null;
						$response['message']=__('Unable to create payment method');
						
					}
					//po_default
					//po_creditcardstatus
					//new_dayofmonthtocharge
					//po_street1, po_street2, po_billtoname, po_city, po_zip, po_state, po_country
	 }catch (\Exception $e){ 
	         $response['status']='error';
			 $response['paymentmethodid']=null;
			 $response['message']=$e->getMessage();
			
	}
	 return $response;
 }
function processDevice($data){
	   try{
				   $service =new OrganizationService( $this->serviceSettings );
			       $deviceid=(isset($data['deviceid']) && !empty($data['deviceid']))?trim($data['deviceid']):'';
				   if($deviceid==''){
						$deviceresponse=[
						'count'=>0,
						];
				   }else{
					    $deviceresponse=$this->deviceExists($service,$data['deviceid'],$data['devicecontact']);
					   
				   }
				   if($deviceresponse['count']>0){
					   $data['deviceid']=$deviceresponse['deviceid'];
					   return $this->updateDevice($service,$data);
				   }else{
					   
					   return $this->createDevice($service,$data);
				   }
				    
	 }catch (\Exception $e){ 
	         $response['status']='error';
			 $response['paymentmethodid']=null;
			 $response['message']=$e->getMessage();
			
	}
	 return $response;
	   
   }
function deviceExists($service,$deviceid,$contactid){
 $response=[];
try{	 
	 
	$fetchxml = "<fetch version='1.0' output-format='xml-platform' mapping='logical' distinct='false'>
  <entity name='new_devices'>
    <filter type='and'>
      <condition attribute='new_devicecontact' operator='eq' value='".trim($contactid)."' />
	  <condition attribute='new_devicesid' operator='eq' value='".trim($deviceid)."' />
    </filter>
  </entity>
</fetch>";

$devices = $service->retrieveMultiple($fetchxml, $allPages = false, $pagingCookie = null, $limitCount = 1, $pageNumber = 1, $simpleMode = false);
$rowcount=($devices->Count)?$devices->Count:0;

if($rowcount>0){
			 $response['status']='success';
			 $response['count']=$rowcount;
			 $response['deviceid']=$devices->Entities[0]->ID;
			 $response['message']=__('Device Exists');
	
}else{
			 $response['status']='success';
			 $response['count']=$rowcount;
			 $response['deviceid']=null;
			 $response['message']=__('No such device exists');
	
}

}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['count']=0;
			 $response['deviceid']=null;
			 $response['message']=$e->getMessage();
	
	}
return $response;
}

function createDevice($service,$data){
	   $response=[];
		try{         
  					$device = $service->entity( 'new_devices' );
					$device->new_name='User id #'.$data['user_id'];
					$device->new_friendlyname=$data['name'];
					$device->new_deviceid=new EntityReference('account',$data['accountid']);
					$device->new_linkedaccount=new EntityReference('account',$data['accountid']);
					$device->new_devicecontact=new EntityReference('contact',$data['devicecontact']);	
					$device->new_model = new EntityReference('new_devicemodel',$data['model']);
					$device->new_belongsto = '100000009';//"Gederweb";
					$device->emailaddress = $data['email'];
					$device->new_notes = 'Created using api on'.date('Y-m-d H:i:s');
					$device->new_phonenumber =$data['phonenumber'];
					
					$device->new_devicestatus = isset($data['status'])?$data['status']:'100000000';//Active
					//$device->statecode = 1;
					$deviceId = $device->create();
					if($deviceId){
					   $response['status']='success';
					   $response['deviceid']=$deviceId;
					   $response['message']=__('Device added Successfully');
					}else{
						$response['status']='error';
						$response['deviceid']=null;
						$response['message']=__('Unable to add device');
						
					}
			}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['deviceid']=null;
			 $response['message']=$e->getMessage();
			
	}		
	 return $response;
	 
 }
   function updateDevice($service,$data){
	 $response=[];
	try{
		            $deviceid=(isset($data['deviceid']))?trim($data['deviceid']):'';
				    $device = $service->entity( 'new_devices', $deviceid);
					$device->new_name='User id #'.$data['user_id'];
					$device->new_friendlyname=$data['name'];
					//$device->new_deviceid=new EntityReference('account',$data['accountid']);
					//$device->new_linkedaccount=new EntityReference('account',$data['accountid']);	
					$device->new_devicecontact=new EntityReference('contact',$data['devicecontact']);	
					$device->new_model = new EntityReference('new_devicemodel',$data['model']);
					$device->new_belongsto = '100000009';//"Gederweb";
					$device->new_phonenumber = $data['phonenumber'];
					$device->emailaddress = $data['email'];
					$device->new_devicestatus = isset($data['status'])?$data['status']:'100000000';
					
				if($device->update()){
					$response['status']='success';
					$response['deviceid']=$deviceid;
					$response['message']=__('Device updated');
				}else{
					$response['status']='error';
					$response['deviceid']=$deviceid;
					$response['message']=__('Unable to update device');
				}
	 }catch (\Exception $e){ 
	         $response['status']='error';
			 $response['deviceid']=null;
			 $response['message']=$e->getMessage();
			
	}
	 return $response;
}
   
   

function processService($data){
		 try{
				   $service =new OrganizationService( $this->serviceSettings );
			       $serviceresponse=$this->serviceExists($service,$data['deviceid'],$data['planid'],$data['accountid']);
				   if($serviceresponse['count']>0){
					   $data['serviceid']=$serviceresponse['serviceid'];
					   return $this->updateService($service,$data);
				   }else{
					   
					   return $this->createService($service,$data);
				   }
				    
	 }catch (\Exception $e){ 
	         $response['status']='error';
			 $response['serviceid']=null;
			 $response['message']=$e->getMessage();
			
	}
	 return $response;
 }
 function serviceExists($service,$deviceid,$planid,$accountid){
 $response=[];
try{	 
	 /*<condition attribute='new_deviceserviceid' operator='eq' value='".trim($deviceid)."' />*/
	$fetchxml = "<fetch version='1.0' output-format='xml-platform' mapping='logical' distinct='false'>
  <entity name='new_deviceservice'>
    <filter type='and'>
      <condition attribute='new_device' operator='eq' value='".trim($deviceid)."' />
	  <condition attribute='new_servicetype' operator='eq' value='".trim($planid)."' />
	  <condition attribute='new_account' operator='eq' value='".trim($accountid)."' />
    </filter>
  </entity>
</fetch>";
$services = $service->retrieveMultiple($fetchxml, $allPages = false, $pagingCookie = null, $limitCount = 1, $pageNumber = 1, $simpleMode = false);
$rowcount=($services->Count)?$services->Count:0;

if($rowcount>0){
			 $response['status']='success';
			 $response['count']=$rowcount;
			 $response['serviceid']=$services->Entities[0]->ID;
			 $response['message']=__('Service Exists');
	
}else{
			 $response['status']='success';
			 $response['count']=$rowcount;
			 $response['serviceid']=null;
			 $response['message']=__('No such service exists');
	
}

}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['count']=0;
			 $response['serviceid']=null;
			 $response['message']=$e->getMessage();
	
	}
return $response;
}
function contactExistsMsdynamics($email,$phone){
	$response=[];
 	$emailcount=0;
	$phonecount=0;
	$emailcontact=[];
	$phonecontact=[];
try{	 
     if($email!='' || $phone!=''){
	 $service =new OrganizationService( $this->serviceSettings );
	 $metadata       = MetadataCollection::instance( $service );
	// $contacts = $service->retrieveMultipleEntities("contact", $allPages = false, $pagingCookie = null, $limitCount = 10, $pageNumber = 1, $simpleMode = false);
	
	$fetchxml = "<fetch version='1.0' output-format='xml-platform' mapping='logical' distinct='false'>
  <entity name='contact'>
    <attribute name='accountid' />
    <attribute name='firstname' />
    <attribute name='lastname' />
    <attribute name='emailaddress1' />
    <attribute name='mobilephone' />
    <order attribute='firstname' descending='false' />
    <filter type='or'>";
	if($email!=''){
      $fetchxml .="<condition attribute='emailaddress1' operator='eq' value='".trim($email)."' />";
	}
	if($phone!=''){
	  $fetchxml .="<condition attribute='mobilephone' operator='eq' value='".trim($phone)."' />";
	}
    $fetchxml .="</filter>
  </entity>
</fetch>";

$contacts = $service->retrieveMultiple($fetchxml, False);
	$rowcount=($contacts->Count)?$contacts->Count:0;

if($rowcount>0){

	foreach($contacts->Entities AS $entity){
		if($entity->emailaddress1==$email){
			if($emailcount==0){
				$emailcontact=[
				        //'accountid'=>$entity->accountid,
						'contactid'=>$entity->contactid,
						'email'=>$entity->emailaddress1,
						'phonenumber'=>$entity->mobilephone,
						];
		      }
     		  $emailcount++;	
		}
		
		if($entity->mobilephone==$phone){
			if($phonecount==0){
				$phonecontact=[
				        //'accountid'=>$entity->accountid,
						'contactid'=>$entity->contactid,
						'email'=>$entity->emailaddress1,
						'phonenumber'=>$entity->mobilephone,
						];
				}
				$phonecount++;
			}
		}
	
		

			 $response['status']='error';
			 $response['count']=$rowcount;
			 $response['emailcount']=$emailcount;
			 $response['contact']=$emailcontact;
			 $response['phonecount']=$phonecount;
			 $response['phonecontact']=$phonecontact;
			 $response['message']=__('Record Exists');
	
}else{
			 $response['status']='success';
			 $response['count']=$rowcount;
			 $response['emailcount']=$emailcount;
			 $response['contact']=$emailcontact;
			 $response['phonecount']=$phonecount;
			 $response['phonecontact']=$phonecontact;
			 $response['message']=__('No such record exists');
	
}
	 }else{
			 $response['status']='error';
			 $response['count']=0;
			 $response['emailcount']=$emailcount;
			 $response['contact']=$emailcontact;
			 $response['phonecount']=$phonecount;
			 $response['phonecontact']=$phonecontact;
			 $response['message']='';
	 }

}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['count']=0;
			 $response['emailcount']=$emailcount;
			 $response['contact']=$emailcontact;
			 $response['phonecount']=$phonecount;
			 $response['phonecontact']=$phonecontact;
			 $response['message']=$e->getMessage();
	
	}
	
return $response;
}
 function createService($service,$data){
	   $response=[];
		try{        		
//echo "***************";
//pr($data);
//echo "***************";		
					$notes='';
					$notes.=(isset($data['hardwaresetup']) && !empty($data['hardwaresetup']))?'Hardware Setup: '.ucfirst($data['hardwaresetup']):'';
					$notes.="\n\r";
					$notes.=(isset($data['devicecount']) && !empty($data['devicecount']))?'Devices: '.ucfirst($data['devicecount']):'';
					$notes.="\n\r";
		            $notes.=(isset($data['emi']) && !empty($data['emi']))?'Emi: '.ucfirst($data['emi']):'';
					$notes.="\n\r";
					$notes.=(isset($data['emiduration']) && !empty($data['emiduration']))?'Duration: '.$data['emiduration']:'';
					$notes.="\n\r";
					if(isset($data['network']) && !empty($data['network'])){
						foreach($data['network'] AS $key => $value){
							$notes.=ucfirst($key).': '.$value;
							$notes.="\n\r";
						}
					}
					$dservice = $service->entity( 'new_deviceservice' );
				    $dservice->new_account=new EntityReference('account',$data['accountid']);	
					$dservice->new_name='Service for '.$data['devicename'];
					//$dservice->new_servicestatus=100000010;
                    $dservice->new_device = new EntityReference('new_devices',$data['deviceid']);					
					$dservice->new_devicemodel = new EntityReference('new_devicemodel',$data['modelid']);
					$dservice->new_servicetype = new EntityReference('new_servicetype',$data['planid']);
					$dservice->new_email = $data['email'];
					$dservice->new_activationfees=$data['activationfees'];
					$dservice->new_billingduration=$data['duration'];
					$dservice->new_price=$data['price'];
					$dservice->new_recuring=1;
					$dservice->new_note=$notes;
					$dservice->new_recurcycle=(isset($data['duration']) && $data['duration']=='annually')?100000002:100000000;  
					$dservice->new_servicestatus=isset($data['status'])?$data['status']:'100000010';//WEB
					$dservice->statecode=isset($data['statecode'])?$data['statecode']:0;
					$serviceid = $dservice->create();
					if($serviceid){
					   $response['status']='success';
					   $response['serviceid']=$serviceid;
					   $response['message']=__('Service created');
					}else{
						$response['status']='error';
						$response['serviceid']=null;
						$response['message']=__('Unable to create service');
						
					}
			}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['serviceid']=null;
			 $response['message']=$e->getMessage();
			
	}		
	 return $response;
	 
 }
function updateService($service,$data){
	 $response=[];
	try{            
	                $notes='';
					$notes.=(isset($data['hardwaresetup']) && !empty($data['hardwaresetup']))?'Hardware Setup: '.ucfirst($data['hardwaresetup']):'';
					$notes.="\n\r";
		            $notes.=(isset($data['emi']) && !empty($data['emi']))?'Emi: '.ucfirst($data['emi']):'';
					$notes.="\n\r";
					$notes.=(isset($data['emiduration']) && !empty($data['emiduration']))?'Duration: '.$data['emiduration']:'';
					$notes.="\n\r";
					if(isset($data['network']) && !empty($data['network'])){
						foreach($data['network'] AS $key => $value){
							$notes.=ucfirst($key).': '.$value;
							$notes.="\n\r";
						}
					}
		            $serviceid=(isset($data['serviceid']))?trim($data['serviceid']):'';
					//$dservice->new_account=new EntityReference('account',$data['accountid']);
				    $dservice = $service->entity( 'new_deviceservice', $serviceid);
				    $dservice->new_name='Service for '.$data['devicename'];
					//$dservice->new_servicestatus=100000010;
                    $dservice->new_device = new EntityReference('new_devices',$data['deviceid']);					
					$dservice->new_devicemodel = new EntityReference('new_devicemodel',$data['modelid']);
					$dservice->new_servicetype = new EntityReference('new_servicetype',$data['planid']);
					$dservice->new_email = $data['email'];
					$dservice->new_activationfees=$data['activationfees'];
					$dservice->new_billingduration=$data['duration'];
					$dservice->new_price=$data['price'];
					$dservice->new_recuring=1;
					$dservice->new_note=$notes;
					$dservice->new_recurcycle=(isset($data['duration']) && $data['duration']=='annually')?100000002:100000000; 
					$dservice->new_servicestatus=isset($data['status'])?$data['status']:'100000010';//WEB		
					$dservice->statecode=isset($data['statecode'])?$data['statecode']:0;					
				if($dservice->update()){
					$response['status']='success';
					$response['serviceid']=$serviceid;
					$response['message']=__('Service updated');
				}else{
					$response['status']='error';
					$response['serviceid']=$serviceid;
					$response['message']=__('Unable to update service');
				}
	 }catch (\Exception $e){ 
	         $response['status']='error';
			 $response['serviceid']=null;
			 $response['message']=$e->getMessage();
			
	}
	 return $response;
}
 /**********************************************************************************************************************/
 /**********************************************************************************************************************/
 /**********************************************************************************************************************/
 /**********************************************************************************************************************/
 
  function createIncidence($data){
	  try{
					$incident = $service->entity('incident');
					//echo '<pre>';print_r($incident);echo '</pre>';
					$incident->title = 'Test Created With Proxy';
					$incident->description = 'This is a test incident';
					$incident->customerid = new EntityReference( 'contact', $guid );
					//$incident->ID = $guid;//contactid responsiblecontactid primarycontactid
					$incidentId = $incident->create();
		}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['message']=$e->getMessage();
			return response;
	}			
	 
 }
  /* function createAccount($data){
	   try{
					$account = $service->entity( 'account' );
					//echo "<pre>";
					//print_r( $service);
					//print_r($account);

					//echo "</pre>";

					//die('check this');
					$account->name = 'Brooklyn 66';
					$account->new_firstname = "Jake";
					$account->new_lastname = "Prelta";
					$account->address1_line1 = "Address";
					$account->address1_city = "City";
					$account->address1_stateorprovince="Eureka";
					$account->address1_postalcode = "1312312";
					$account->telephone1 = "444444444444444";
					$account->emailaddress1 = 'rsharma.me.19@gmail.com';
					$account->new_business = '100000001';
					echo "Account id is ".$account = $account->create();
					echo "<br/>";
					//"account id: 444444444444444 : 0ec164d1-668f-ea11-a811-000d3a378f47 "
					//$contact->new_produit_demande = new EntityReference('new_produituic',$guid);
		}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['message']=$e->getMessage();
			return response;
	}			
	 
	 
 }*/

     
      function createTransaction($data){
		  try{
						 echo "<pre>";
					$cct = $service->entity( 'po_creditcardtransaction' );
					print_r($cct);
					$cct->subject = "Test transaction via api";
					//$cct->regardingobjectid=new EntityReference('contact',"927CF57C-5E8F-EA11-A811-000D3A3788D7");
					$cct->new_recurring = 0; //1 or 0 
					$cct->po_amount = 200.00;
					//$cct->po_currency = new EntityReference('transactioncurrency',"934C4E27-4275-E411-80D2-FC15B4286D2C"); //usd
					$cct->description = 'Test transaction via api';
					$cct->po_creditcardid = new EntityReference('po_creditcard',"375E8149-7B8F-EA11-A811-000D3A378C4B");
					$cct->po_action = 1; //1 or 0
					echo "</pre>";
					echo "</pre>";
					echo "Transaction id is ".$cct = $cct->create();
					echo "<br/>";
		}catch (\Exception $e){ 
	         $response['status']='error';
			 $response['message']=$e->getMessage();
			return response;
	}				 
	 
 }
 function createCCTransaction($data){
	 $response=[
		 'status'=>'error',
		 'message'=>__('Unable to create transaction on MS Dynamics')
	 ];
	try{
		$service =new OrganizationService( $this->serviceSettings );
		$cct = $service->entity( 'po_creditcardtransaction' );
		$cct->subject = "Device added to account";
		$cct->regardingobjectid=new EntityReference('account',$data['accountid']);
		$cct->new_recurring = 0; //1 or 0 
		$cct->po_amount = $data['amount'];
		//$cct->po_currency = new EntityReference('transactioncurrency',"934C4E27-4275-E411-80D2-FC15B4286D2C"); //usd
		$cct->description = 'Device added to account';
		$cct->po_creditcardid = new EntityReference('po_creditcard',$data['paymentid']);
		$cct->po_action = 1; //1 or 0
		$tid = $cct->create();
		if($tid!=""){
			$response['status']='success';
			$response['tid'] = $tid;
			$response['message']='Transaction successful';
	 
		}
	}catch (\Exception $e){ 
	   $response['status']='error';
	   $response['message']=$e->getMessage();
	  return $response;
	}	
	return $response;	
}
 
}//component class ends
