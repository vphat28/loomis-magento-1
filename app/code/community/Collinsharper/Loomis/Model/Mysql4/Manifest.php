<?php
/**
 * Created by Gayan Hewa
 * User: Gayan
 * Date: 9/15/13
 * Time: 10:22 PM
 */

class Collinsharper_Loomis_Model_Mysql4_Manifest extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init('loomismodule/manifest', 'manifest_id');
    }
}