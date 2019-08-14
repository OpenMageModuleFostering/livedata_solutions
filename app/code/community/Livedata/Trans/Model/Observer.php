<?php

class Livedata_Trans_Model_Observer
{
    const ATTR_CREATION_CONTACT_DATE = 'custom_magentocreationcontactdate';
    const ATTR_CREATION_ORDER_DATE   = 'custom_magentocreationorderdate';
    const ATTR_IMPORT                = 'custom_magentoimport';
    const ATTR_TOTAL_IMPORT          = 'custom_magentototalimport';

    /**
     * event to sync the existing contacts to livedata database
     *
     */
    public function adminSystemConfigChangedSection()
    {
        $customExist = false;
        // check if enable send transactionals is allowed
        if (Mage::getStoreConfig('trans/view/sendtrans')) {
            // validate that the contact attributes are not created
            $customExist = $this->checkCustomAttr();
            // if some custome does not exist, create them
            if(!$customExist) {
                // create custom attributes
                $customCreated = $this->createCustomAttr();
                if($customCreated['code'] != 200)
                    Mage::getSingleton('core/session')->addError('An error ocurred while creating some custom attributes: ' . $customCreated['message'] . ' Please contact with Livedata support team.');
                else
                    $customExist = true;
            }
        }
        
        // check if enable syncronize all contacts pluggin is selected
        if (Mage::getStoreConfig('trans/view/enabled')) {
            // validate that the contact attributes are created
            if($customExist) {
                // get the magento contacts
                $contacts = $this->getCustomerContacts();
                $updated  = true;
                foreach ($contacts as $user) {
                    $contactdb = $user->getData();
                    // get only the active contacts
                    if($contactdb['is_active'] == '1') {
                        // get customer postcode
                        $contactdb['postcode'] = $this->getCustomerAddress($contactdb['email']);
                        // get cutomer grand total
                        $customerAmount = $this->getCustomerGrandTotal($contactdb['email']);
                        $contactdb['totalAmount'] = $customerAmount['total'];
                        $contactdb['lastAmount']  = $customerAmount['last'];
                        // sync contact: add/update contact with standard attr in database
                        $contact = $this->addOrUpdateContact($contactdb);
                        if($contact['code'] == 200 || $contact['code'] == 201) {
                            // if the contact has been inserted or updated, and there is a contact list selected and allowed, insert it in the list
                            $listId = Mage::getStoreConfig('trans/selectlist/from_list');
                            if($listId != 0)
                                Mage::helper('livedata_trans/contact')->insertContactToList($contactdb['email'], $listId);  
                        } else
                            $updated = false;
                        // if it is a new contact, unsubscribe it if it is not subscribed in the newsletter
                        if($contact['code'] == 201) {
                            //unsubscribe contact
                            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($contactdb['email']);
                            if (array_key_exists('subscriber_status', $subscriber) && $subscriber['subscriber_status'] == 3) {
                                $unsubscribeContact = Mage::helper('livedata_trans/contact')->unsubscribeContact($contactdb['email']);
                                if($unsubscribeContact['statusCode'] != 200)
                                    $updated = false;
                            } elseif (!array_key_exists('subscriber_status', $subscriber)) {
                                $unsubscribeContact = Mage::helper('livedata_trans/contact')->unsubscribeContact($contactdb['email']);
                                if($unsubscribeContact['statusCode'] != 200)
                                    $updated = false;
                            }
                        }
                    }
                }
                // if some contact has not been inserted or included in the list or unsubscribe it show a notice
                if(!$updated)
                    Mage::getSingleton('core/session')->addNotice('Some contact has not been inserted correctly in the synchronization process');
            } else
                Mage::getSingleton('core/session')->addError('Custom Attributes are not already created. Enable the "Enable send Transactionals with Livedata" option.');
        } else
            Mage::getSingleton('core/session')->addNotice('You have not allowed the Livedata synchronization settings. Check it if you want to syncronize all the existing contacts.');

        // create new contact list
        if (Mage::getStoreConfig('trans/list/enabled_list')) {
            $contactlist = Mage::helper('livedata_trans/contactlist')->createNewList();
            if($contactlist['statusCode'] != 200)
                Mage::getSingleton('core/session')->addError('An error ocurred while creating the contact list: ' . $contactlist['message'] . ' Please contact with Livedata support team.');
        }
    }

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
        $httpMethod   = 'PUT'; 
        $result       = array();
        // input fields validation
        $validation   = Mage::helper('livedata_trans/contact')->validateInputValues($environment, $baseId, $username, $password);
        if($validation['code'] == 200) {
            // change date of birtday format
            if (array_key_exists('dob', $contact) && $contact['dob'] != "")
                $birthdate = '&birthdate='.date("Y-m-d", strtotime($contact['dob']));
            else
                $birthdate = "";
            if(array_key_exists('prefix', $contact))
                $prefix    = $contact['prefix'];
            else
                $prefix    = "";
            $contactCreated = date("Y-m-d", strtotime($contact['created_at']));
            // add or update contact
            $ch       = curl_init();
            $headers  = Mage::helper('livedata_trans/contact')->generateWSSEHeader($username, $password);
            // insert or update standar attributes
            $fieldsString = 'firstname='.$contact['firstname'].'&lastname='.$contact['lastname'].'&title='.$prefix.$birthdate.'&zipcode='.$contact['postcode'].'&'.self::ATTR_CREATION_CONTACT_DATE.'='.$contactCreated.'&'.self::ATTR_IMPORT.'='.$contact['lastAmount'].'&'.self::ATTR_TOTAL_IMPORT.'='.$contact['totalAmount'];
            curl_setopt($ch, CURLOPT_URL, $environment.$baseId.'/contacts/'.$contact['email']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);
            $response          = curl_exec($ch);
            curl_close($ch);
            $contact           = json_decode($response, true);
            $result['code']    = $contact['statusCode'];
            $result['message'] = $contact['message'];
        } else {
            $result['code']    = $validation['code'];
            $result['message'] = $validation['message'];
        }

        return $result;
    }

    /**
     * function to create magento custom attributes
     *
     * @return array
     */
    private function createCustomAttr()
    {
        $environment  = Mage::getStoreConfig('trans/view/api_url');
        $baseId       = Mage::getStoreConfig('trans/view/api_key');
        $username     = Mage::getStoreConfig('trans/view/from_user');
        $password     = Mage::getStoreConfig('trans/view/from_password');
        $result       = array('code' => 200, 'message' => 'ok');
        $customAttr   = array();
        $customAttr[] = 'name=magento_creation_contact_date&type=date';
        $customAttr[] = 'name=magento_creation_order_date&type=date';
        $customAttr[] = 'name=magento_import&type=string';
        $customAttr[] = 'name=magento_total_import&type=string';
        // insert contact in list
        $httpMethod   = 'PUT';
        $headers      = Mage::helper('livedata_trans/contact')->generateWSSEHeader($username, $password);
        foreach ($customAttr as $fieldsString) {
            $ch       = curl_init();
            curl_setopt($ch, CURLOPT_URL, $environment.$baseId.'/attributes/customs');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);
            $response = curl_exec($ch);
            curl_close($ch);
            $res      = json_decode($response, true);
            if($res['statusCode'] != 201) {
                $result['code']    = $res['statusCode'];
                $result['message'] = $res['message'];
            }
        }

        return $result;
    }

    /**
     * function to check if contact attributes are already created
     *
     * @return boolean
     */
    private function checkCustomAttr()
    {
        $result       = false;
        $environment  = Mage::getStoreConfig('trans/view/api_url');
        $baseId       = Mage::getStoreConfig('trans/view/api_key');
        $username     = Mage::getStoreConfig('trans/view/from_user');
        $password     = Mage::getStoreConfig('trans/view/from_password');
        $httpMethod   = 'GET';
        $headers      = Mage::helper('livedata_trans/contact')->generateWSSEHeader($username, $password);
        $ch           = curl_init();
        curl_setopt($ch, CURLOPT_URL, $environment.$baseId.'/attributes');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
        $response     = curl_exec($ch);
        curl_close($ch);
        $res          = json_decode($response, true);
        // search the attributes in all lists
        if(200 == $res['statusCode']) {
            if($this->searchForName('magento_creation_contact_date',$res['result']['data']) && $this->searchForName('magento_creation_order_date',$res['result']['data']) && $this->searchForName('magento_import',$res['result']['data']) && $this->searchForName('magento_total_import',$res['result']['data']))
                $result = true;
        }

        return $result;
    }

    /**
     * function to search in array of arrays
     *
     * @param  string
     * @param  array
     * @return boolean
     */
    function searchForName($id, $array) {
       foreach ($array as $key => $val) {
           if ($val['name'] === $id)
               return true;
       }
       return false;
    }

    /**
     * function to get the customer contacts
     *
     * @return array
     */
    private function getCustomerContacts()
    {
        $users = mage::getModel('customer/customer')->getCollection()->addAttributeToSelect('email')->addAttributeToSelect('firstname')->addAttributeToSelect('lastname')->addAttributeToSelect('prefix')->addAttributeToSelect('dob');
        return $users;
    }

    /**
     * function to get the customer addresses by email
     *
     * @param  string   $email
     * @return array
     */
    private function getCustomerAddress($email)
    {
        $contactAddress = Mage::getResourceModel('customer/address_collection')->addAttributeToSelect('*')->joinTable(array('customer'=>'customer/entity'),'entity_id = parent_id',array('email'));
        $postcode       = "";
        foreach ($contactAddress as $address) {
            $addressData = $address->getData();
            if($addressData['email'] == $email && array_key_exists('postcode', $addressData))
                $postcode = $addressData['postcode'];
        }
        return $postcode;
    }

    /**
     * function to get the total amount and the last amount
     *
     * @param  string   $email
     * @return array
     */
    private function getCustomerGrandTotal($email)
    {
        $orders = Mage::getModel('sales/order')->getCollection()->addAttributeToFilter('customer_email', $email);
        $sum    = floatval('0.00');
        if(count($orders) > 0) {
            foreach ($orders as $order) {
                $total      = $order->getGrandTotal();
                $lastAmount = $total;
                $sum+= $total;
            }
        } else {
            $lastAmount = '0.00';
            $sum        = '0.00';
        }

        return array('total' => strval($sum), 'last' => $lastAmount);
    }

    /**
     * event to add registers in livedata db
     *
     */
    public function addRegisterToDB(Varien_Event_Observer $observer)
    {
        // get the post data
        $request      = $observer->getEvent();
        $customerData = $request->getCustomer();
        // add contact to database
        $contact = Mage::helper('livedata_trans/contact')->addOrUpdateContact($customerData);
        // if contact added or updated
        if($contact['code'] == 201 || $contact['code'] == 200) {
            // if the contact has been inserted or updated, and there is a contact list selected and allowed, insert it in the list
            $listId = Mage::getStoreConfig('trans/selectlist/from_list');
            if($listId != '0')
                $insertToList = Mage::helper('livedata_trans/contact')->insertContactToList($customerData->getEmail(), $listId);
            // if it's a new contact, unsubscribe it
            if(!$contact['contactExist']) {
                $unsubscribed = Mage::helper('livedata_trans/contact')->unsubscribeContact($customerData->getEmail());
                if($unsubscribed['statusCode'] != 200)
                    Mage::getSingleton('core/session')->addError('An error ocurred while inserting the contact status: ' . $unsubscribed['message'] . ' Please contact with Livedata support team.');
            }
        } else
            Mage::getSingleton('core/session')->addError('An error ocurred while saving the contact: ' . $contact['message'] . ' Please contact with Livedata support team.');  
    }

    /**
     * event when subscribe in newsletter
     *
     */
    public function subscribedToNewsletter(Varien_Event_Observer $observer)
    {
        $event        = $observer->getEvent();
        $subscriber   = $event->getDataObject();
        $data         = $subscriber->getData();
        $email        = $data['subscriber_email'];
        $statusChange = $subscriber->getIsStatusChanged();
        // Trigger if user is now subscribed and there has been a status change:
        if ($data['subscriber_status'] == "1" && $statusChange == true) {
            //resubscribe contact
            $resubscribeContact = Mage::helper('livedata_trans/contact')->resubscribeContact($email);
            if($resubscribeContact['statusCode'] != 200)
                Mage::getSingleton('core/session')->addNotice('An error ocurred while activating the contact: ' . $resubscribeContact['message']);
        } elseif ($data['subscriber_status'] == "3" && $statusChange == true) {
            //unsubscribe contact
            $unsubscribeContact = Mage::helper('livedata_trans/contact')->unsubscribeContact($email);
            if($unsubscribeContact['statusCode'] != 200)
                Mage::getSingleton('core/session')->addError('An error ocurred while unsubsribing the contact: ' . $unsubscribeContact['message'] . ' Please contact with Livedata support team.');
        }
    }

    /**
     * event when subscribe in newsletter
     *
     */
    public function onCustomerDelete(Varien_Event_Observer $observer)
    {
        $event        = $observer->getEvent();
        $subscriber   = $event->getDataObject();
        $data         = $subscriber->getData();
        $email        = $data['email'];
        //unsubscribe contact
        $unsubscribeContact = Mage::helper('livedata_trans/contact')->unsubscribeContact($email);
        if($unsubscribeContact['statusCode'] != 200)
            Mage::getSingleton('core/session')->addError('An error ocurred while removing the contact: ' . $unsubscribeContact['message'] . ' Please contact with Livedata support team.');
    }

    /**
     * event customer pay an order
     *
     */
    public function onPayOrder(Varien_Event_Observer $observer)
    {
        $event  = $observer->getEvent();
        $order  = $event->getInvoice()->getOrder();
        $data   = $order->getData();
        $import = $data["grand_total"];
        $email  = $order->getCustomerEmail();
        // update contact custom attributes
        $contact = Mage::helper('livedata_trans/contact')->updateMagentoPaymentAttr($email, $import);
        if($contact['statusCode'] != 200)
            Mage::getSingleton('core/session')->addError('Livedata attributes has not been updated: ' . $contact['message'] . ' Please contact with Livedata support team.');
    }

    /**
     * event to add registers in scenario program
     *
     */
    public function addRegisterToScenario(Varien_Event_Observer $observer)
    {
        // get the post data
        $request      = $observer->getEvent();
        $customerData = $request->getCustomer();
        // add contact to database
        $contact = Mage::helper('livedata_trans/scenario')->addContactToProgram($customerData);
        if($contact['statusCode'] != 201)
            Mage::getSingleton('core/session')->addNotice('An error ocurred while inserting the contact in scenario program: ' . $contact['message']);
    }

    /**
     * cron that check the unsubscribe contacts in livedata every day and update it in magento database
     *
     */
    public function livedatatransunsubscribe()
    {
        $environment  = Mage::getStoreConfig('trans/view/api_url');
        $baseId       = Mage::getStoreConfig('trans/view/api_key');
        $username     = Mage::getStoreConfig('trans/view/from_user');
        $password     = Mage::getStoreConfig('trans/view/from_password');
        // get the unsubscribes in last day
        $date         = date('Y-m-j');
        $yesterday    = strtotime ( '-1 day' , strtotime ( $date ) ) ;
        $fromDate     = date ( 'Y-m-j' , $yesterday ).'+00:00:00';
        $toDate       = date ( 'Y-m-j' , $yesterday ).'+23:59:59';
        $contacts     = Mage::helper('livedata_trans/contact')->getLastDayUnsubscribes($fromDate, $toDate);
        if(!empty($contacts['result']['data'])) {
            foreach ($contacts['result']['data'] as $contact) {
                // mark the contact as not subscriber
                $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($contact['email'])->unsubscribe();
            }
        } 
    }
}