<?php

class Livedata_Trans_Model_System_Config_Source_Contactlists 
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $contactlist = Mage::helper('livedata_trans/contactlist')->getAllLists();
        $arrayList   = array();

        foreach ($contactlist as $list) {
            $arrayList[] = array('value' => $list['id'], 'label' => $list['name']);
        }
        $arrayList[] = array('value' => 0, 'label' => 'Select List');

        return $arrayList;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $contactlist  = Mage::helper('livedata_trans/contactlist')->getAllLists();
        $arrayList    = array();

        foreach ($contactlist as $list) {
            $arrayList[$list['id']] = $list['name'];
        }
        $arrayList[0] = 'Select List';

        return $arrayList;
    }
}