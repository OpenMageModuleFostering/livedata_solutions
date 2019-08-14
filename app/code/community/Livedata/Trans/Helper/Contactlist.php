<?php
/**
 * Contact list helper
 *
 * @author Livedata
 */
class Livedata_Trans_Helper_Contactlist extends Mage_Core_Helper_Abstract
{
    /**
     * get the contact list availables
     *
     * @return array
     */
    public function getAllLists()
    {
        $environment  = Mage::getStoreConfig('trans/view/api_url');
        $baseId       = Mage::getStoreConfig('trans/view/api_key');
        $username     = Mage::getStoreConfig('trans/view/from_user');
        $password     = Mage::getStoreConfig('trans/view/from_password');
        $httpMethod   = 'GET'; 
        $result       = array();
        // check that all the fields are not empty
        if(!empty($environment) && !empty($baseId) && !empty($username) && !empty($password)) {
            $ch      = curl_init();
            $headers = $this->generateWSSEHeader($username, $password);
            curl_setopt($ch, CURLOPT_URL, $environment.$baseId.'/contact_lists');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);

            $response = curl_exec($ch);
            curl_close($ch);
            $contactLists = json_decode($response, true);

            if ($contactLists['statusCode'] == 200)
                $result = $contactLists['result']['data'];
        }

        return $result;
    }

    /**
     * create new contact list
     *
     * @return array
     */
    public function createNewList()
    {
        $listName     = Mage::getStoreConfig('trans/list/from_list_name');
        $environment  = Mage::getStoreConfig('trans/view/api_url');
        $baseId       = Mage::getStoreConfig('trans/view/api_key');
        $username     = Mage::getStoreConfig('trans/view/from_user');
        $password     = Mage::getStoreConfig('trans/view/from_password');
        $contact      = Mage::getSingleton('admin/session')->getUser()->getEmail();
        $fieldsString = '';
        $httpMethod   = 'PUT';
        $result       = array('code' => 400, 'message' => 'Some mandatory parameters are not defined');
        // check that all the fields are not empty
        if(!empty($environment) && !empty($baseId) && !empty($username) && !empty($password)) {
            $ch      = curl_init();
            $headers = $this->generateWSSEHeader($username, $password);
            curl_setopt($ch, CURLOPT_URL, $environment.$baseId.'/contact_lists');
            $fields = array('name'        => urlencode($listName),
                            'description' => urlencode($username.' created this list from Magento'),
                            'emaillist'   => urlencode($contact),
                            'batList'     => urlencode('false')
                            );
            foreach($fields as $key=>$value) { $fieldsString .= $key.'='.$value.'&'; }
            rtrim($fieldsString, '&');

            curl_setopt($ch, CURLOPT_POST, count($fields));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);

            $response = curl_exec($ch);
            curl_close($ch);
            $result   = json_decode($response, true);
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
    private function generateWSSEHeader($username, $password)
    {
        $created = date('c');
        $nonce   = substr(md5(uniqid('nonce_', true)),0,16);
        $nonce64 = base64_encode($nonce);
        $passwordDigest = base64_encode(sha1($nonce . $created . $password, true));
        
        return array('X-WSSE: UsernameToken Username="' . $username . '", PasswordDigest="' . $passwordDigest . '", Nonce="' . $nonce64 . '", Created="'. $created . '"');
    }
}