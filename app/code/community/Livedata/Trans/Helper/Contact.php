<?php

/**
 * Contact helper
 *
 * @author Livedata
 */
class Livedata_Trans_Helper_Contact extends Mage_Core_Helper_Abstract
{
    const ATTR_CREATION_CONTACT_DATE = 'custom_magentocreationcontactdate';
    const ATTR_CREATION_ORDER_DATE   = 'custom_magentocreationorderdate';
    const ATTR_IMPORT                = 'custom_magentoimport';
    const ATTR_TOTAL_IMPORT          = 'custom_magentototalimport';

    /**
     * add or update contact in database
     *
     * @param  array    $contact
     * @return array
     */
    public function addOrUpdateContact($contact)
    {
        $environment  = Mage::getStoreConfig('trans/view/api_url');
        $baseId       = Mage::getStoreConfig('trans/view/api_key');
        $username     = Mage::getStoreConfig('trans/view/from_user');
        $password     = Mage::getStoreConfig('trans/view/from_password');
        $result       = array();
        // input fields validation
        $validation   = $this->validateInputValues($environment, $baseId, $username, $password);
        if($validation['code'] == 200) {
            $headers  = $this->generateWSSEHeader($username, $password);
            // prepare contact fields
            if(!is_null($contact->getAddressesCollection()->getFirstitem()->getPostcode()))
                $zipcode = '&zipcode='.$contact->getAddressesCollection()->getFirstitem()->getPostcode();
            else
                $zipcode = "";
            // change date of birtday format
            if ($contact->getDob() != "")
                $birthdate = '&birthdate='.date("Y-m-d", strtotime($contact->getDob()));
            else
                $birthdate = "";
            // check if it's a new contact or updated
            $ch           = curl_init();
            $httpMethod   = 'GET';
            curl_setopt($ch, CURLOPT_URL, $environment.$baseId.'/contacts/'.$contact->getEmail());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
            $response     = curl_exec($ch);
            curl_close($ch);
            $getContact   = json_decode($response, true);
            // initializace custom attributes
            if($getContact['statusCode'] == 200) {
                // updated contact
                $customAttrImport       = '';
                $customAttrTotalImport  = '';
                $created                = ''; 
                $result['contactExist'] = true;
            } else {
                // new contact
                $customAttrImport       = '&'.self::ATTR_IMPORT.'= 0.00';
                $customAttrTotalImport  = '&'.self::ATTR_TOTAL_IMPORT.'= 0.00';
                $created                = '&'.self::ATTR_CREATION_CONTACT_DATE.'='.date("Y-m-d"); 
                $result['contactExist'] = false;
            }

            // add or update contact
            $ch           = curl_init();
            $httpMethod   = 'PUT';
            // insert or update standar attributes
            $fieldsString = 'firstname='.$contact->getFirstname().'&lastname='.$contact->getLastname()./*'&title='.$contact->getPrefix().*/$birthdate.$zipcode.$created.$customAttrImport.$customAttrTotalImport;
            curl_setopt($ch, CURLOPT_URL, $environment.$baseId.'/contacts/'.$contact->getEmail());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);
            $response          = curl_exec($ch);
            curl_close($ch);
            $contactupdated    = json_decode($response, true);
            $result['code']    = $contactupdated['statusCode'];
            $result['message'] = $contactupdated['message'];
        } else {
            $result['code']    = $validation['code'];
            $result['message'] = $validation['message'];
        }

        return $result;
    }

    /**
     * function to validate input fields are not blank
     *
     * @param string    $environment
     * @param string    $baseId
     * @param string    $username
     * @param string    $password
     * @return array
     */
    public function validateInputValues($environment, $baseId, $username, $password)
    {
        $result = array('code' => 200, 'message' => 'ok');
        // validate username
        if("" == $environment) {
            Mage::getSingleton('core/session')->addError('API url field is empty and it is mandatory');
            $result['code']    = 401;
            $result['message'] = 'API url field is empty and it is mandatory';
        }
        if("" == $baseId) {
            Mage::getSingleton('core/session')->addError('API key field is empty and it is mandatory');
            $result['code']    = 401;
            $result['message'] = 'API key field is empty and it is mandatory';
        }
        if("" == $username) {
            Mage::getSingleton('core/session')->addError('Username field is empty and it is mandatory');
            $result['code']    = 401;
            $result['message'] = 'Username field is empty and it is mandatory';
        }
        if("" == $password) {
            Mage::getSingleton('core/session')->addError('Password field is empty and it is mandatory');
            $result['code']    = 401;
            $result['message'] = 'Password field is empty and it is mandatory';
        }
        
        return $result;
    }

    /**
     * function to generate wsseheader
     *
     * @param  $username
     * @param  $password
     * @return array
     */
    public function generateWSSEHeader($username, $password)
    {
        $created = date('c');
        $nonce   = substr(md5(uniqid('nonce_', true)),0,16);
        $nonce64 = base64_encode($nonce);
        $passwordDigest = base64_encode(sha1($nonce . $created . $password, true));
        
        return array('X-WSSE: UsernameToken Username="' . $username . '", PasswordDigest="' . $passwordDigest . '", Nonce="' . $nonce64 . '", Created="'. $created . '"');
    }

    /**
     * function to add a contact in a contact list
     *
     * @param string    $email
     * @param integer   $listId
     * @return array
     */
    public function insertContactToList($email, $listId)
    {
        $environment  = Mage::getStoreConfig('trans/view/api_url');
        $baseId       = Mage::getStoreConfig('trans/view/api_key');
        $username     = Mage::getStoreConfig('trans/view/from_user');
        $password     = Mage::getStoreConfig('trans/view/from_password');
        $result       = array();
        // insert contact in list
        $httpMethod   = 'PUT';
        $ch           = curl_init();
        $headers      = $this->generateWSSEHeader($username, $password);
        curl_setopt($ch, CURLOPT_URL, $environment.$baseId.'/contact_lists/'.$listId.'/contact/'.$email);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
        $response     = curl_exec($ch);
        curl_close($ch);
        $result       = json_decode($response, true);

        return $result;
    }

    /**
     * function to unsubscribe a contact
     *
     * @param  $email
     * @return array
     */
    public function unsubscribeContact($email)
    {
        $environment  = Mage::getStoreConfig('trans/view/api_url');
        $baseId       = Mage::getStoreConfig('trans/view/api_key');
        $username     = Mage::getStoreConfig('trans/view/from_user');
        $password     = Mage::getStoreConfig('trans/view/from_password');
        $httpMethod   = 'PUT';
        $headers      = $this->generateWSSEHeader($username, $password);

        $ch           = curl_init();
        curl_setopt($ch, CURLOPT_URL, $environment.$baseId.'/contacts/'.$email.'/unsubscription');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
        $response     = curl_exec($ch);
        curl_close($ch);
        $res          = json_decode($response, true);

        return $res;
    }

    /**
     * function to resubscribe a contact
     *
     * @param  $email
     * @return array
     */
    public function resubscribeContact($email)
    {
        $environment  = Mage::getStoreConfig('trans/view/api_url');
        $baseId       = Mage::getStoreConfig('trans/view/api_key');
        $username     = Mage::getStoreConfig('trans/view/from_user');
        $password     = Mage::getStoreConfig('trans/view/from_password');
        $httpMethod   = 'PUT';
        $headers      = $this->generateWSSEHeader($username, $password);

        $ch           = curl_init();
        curl_setopt($ch, CURLOPT_URL, $environment.$baseId.'/contacts/'.$email.'/resubscription');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
        $response     = curl_exec($ch);
        curl_close($ch);
        $res          = json_decode($response, true);

        return $res;
    }

    /**
     * function to update magento custom attributes when a payment has been done
     *
     * @param  $email
     * @param  $import
     * @return array
     */
    public function updateMagentoPaymentAttr($email, $import)
    {
        $environment  = Mage::getStoreConfig('trans/view/api_url');
        $baseId       = Mage::getStoreConfig('trans/view/api_key');
        $username     = Mage::getStoreConfig('trans/view/from_user');
        $password     = Mage::getStoreConfig('trans/view/from_password');
        $httpMethod   = 'PUT';
        $headers      = $this->generateWSSEHeader($username, $password);
        $created      = date("Y-m-d");
        $ch           = curl_init();
        // get the value of total import from attribute
        $currentTV    = $this->getCurrentTotalImport($email,$import);
        $fieldsString = self::ATTR_CREATION_ORDER_DATE.'='.$created.'&'.self::ATTR_IMPORT.'='.number_format($import, 2).'&'.self::ATTR_TOTAL_IMPORT.'='.number_format($currentTV, 2);
        curl_setopt($ch, CURLOPT_URL, $environment.$baseId.'/contacts/'.$email);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);
        $response     = curl_exec($ch);
        curl_close($ch);
        $res          = json_decode($response, true);

        return $res;
    }

    /**
     * add the import in the current total import
     *
     * @param  $email
     * @param  $import
     * @return array
     */
    private function getCurrentTotalImport($email, $import)
    {
        $environment  = Mage::getStoreConfig('trans/view/api_url');
        $baseId       = Mage::getStoreConfig('trans/view/api_key');
        $username     = Mage::getStoreConfig('trans/view/from_user');
        $password     = Mage::getStoreConfig('trans/view/from_password');
        $httpMethod   = 'GET';
        $headers      = $this->generateWSSEHeader($username, $password);
        $ch           = curl_init();
        $totalimport  = floatval($import);
        $total        = "";

        curl_setopt($ch, CURLOPT_URL, $environment.$baseId.'/contacts/'.$email);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
        $response     = curl_exec($ch);
        curl_close($ch);
        $res          = json_decode($response, true);

        if($res['statusCode'] == 200) {
            if(array_key_exists('CUSTOM_MAGENTOTOTALIMPORT', $res['result'][0])) {
                $val1       = $res['result'][0]['CUSTOM_MAGENTOTOTALIMPORT'];
                $totalvalue = floatval($val1) + $totalimport;
                $total      = strval($totalvalue);
            } else
                $total = $totalimport;
        }
        return $total;
    }
}