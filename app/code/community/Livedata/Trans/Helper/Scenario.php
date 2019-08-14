<?php
/**
 * Scenario helper
 *
 * @author Livedata
 */
class Livedata_Trans_Helper_Scenario extends Mage_Core_Helper_Abstract
{
    /**
     * get all the scenario program finished
     *
     * @return array
     */
    public function getAllPrograms()
    {
        $environment  = Mage::getStoreConfig('trans/view/api_url');
        $baseId       = Mage::getStoreConfig('trans/view/api_key');
        $username     = Mage::getStoreConfig('trans/view/from_user');
        $password     = Mage::getStoreConfig('trans/view/from_password');
        $httpMethod   = 'GET'; 
        $result       = array();
        // check that all the fields are not empty
        if(!empty($environment) && !empty($baseId) && !empty($username) && !empty($password)) {
            $ch       = curl_init();
            $headers  = $this->generateWSSEHeader($username, $password);
            curl_setopt($ch, CURLOPT_URL, $environment.$baseId.'/scenario/programs/all');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
            $response = curl_exec($ch);
            curl_close($ch);
            $scenarioProgram = json_decode($response, true);

            if ($scenarioProgram['statusCode'] == 200)
                $result = $scenarioProgram['result']['data'];
        }

        return $result;
    }

    /**
     * insert contact to scenario program
     *
     * @param  Object   $contact
     * @return array
     */
    public function addContactToProgram($contact)
    {
        $environment  = Mage::getStoreConfig('trans/view/api_url');
        $baseId       = Mage::getStoreConfig('trans/view/api_key');
        $username     = Mage::getStoreConfig('trans/view/from_user');
        $password     = Mage::getStoreConfig('trans/view/from_password');
        $enabledScn   = Mage::getStoreConfig('trans/scenario/enabled_scn');
        $result       = array('statusCode' => 400, 'message' => 'Some mandatory parameters are not defined');
        // if scenario program is enabled
        if(Mage::getStoreConfig('trans/scenario/enabled_scn')) {
            // get email
            $email      = $contact->getEmail();
            // get scenario id
            $scenarioId = Mage::getStoreConfig('trans/scenario/from_list');
            if($scenarioId != '0') {
                // check all input parameters from config
                if(!empty($environment) && !empty($baseId) && !empty($username) && !empty($password))
                    $result = $this->addContact($email,$scenarioId); // insert contact to program
            }
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

    /**
     * function that call the api function add contact to program
     *
     * @param  string   $email
     * @param  stirng   $programId
     * @return array
     */
    private function addContact($email, $programId)
    {
        $environment  = Mage::getStoreConfig('trans/view/api_url');
        $baseId       = Mage::getStoreConfig('trans/view/api_key');
        $username     = Mage::getStoreConfig('trans/view/from_user');
        $password     = Mage::getStoreConfig('trans/view/from_password');
        $httpMethod   = 'PUT'; 
        $ch           = curl_init();
        $headers      = $this->generateWSSEHeader($username, $password);
        curl_setopt($ch, CURLOPT_URL, $environment.$baseId.'/scenario/programs/'.$programId.'/'.$email);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
        $response     = curl_exec($ch);
        curl_close($ch);
        $result       = json_decode($response, true);

        return $result;
    }
}