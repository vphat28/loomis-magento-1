<?php
/**
 * Created by Gayan Hewa
 * User: Gayan
 * Date: 9/15/13
 * Time: 10:21 PM
 */

class Collinsharper_Loomis_Model_Mysql4_Shipment_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    protected function _contruct()
    {
        $this->_init('loomismodule/shipment');
    }
}