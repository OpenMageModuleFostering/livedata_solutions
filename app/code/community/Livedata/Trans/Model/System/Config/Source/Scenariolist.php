<?php

class Livedata_Trans_Model_System_Config_Source_Scenariolist 
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $scenariolist = Mage::helper('livedata_trans/scenario')->getAllPrograms();
        $arrayList    = array();

        foreach ($scenariolist as $program) {
            if($program['status'] == 'active')
                $arrayList[] = array('value' => $program['id'], 'label' => $program['name']);
        }
        $arrayList[] = array('value' => 0, 'label' => 'Select Program');

        return $arrayList;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $scenariolist = Mage::helper('livedata_trans/scenario')->getAllPrograms();
        $arrayList    = array();

        foreach ($scenariolist as $program) {
            if($program['status'] == 'active')
                $arrayList[$program['id']] = $program['name'];
        }
        $arrayList[0] = 'Select Program';

        return $arrayList;
    }
}