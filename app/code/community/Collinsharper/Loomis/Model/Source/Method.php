<?php

class Collinsharper_Loomis_Model_Source_Method
{
    public function toOptionArray()
    {
        $model = Mage::getSingleton('loomismodule/Carrier_Shippingmethod');
        $arr = array();
        foreach ($model->getCode('method') as $v => $l)
        {
            $arr[] = array('value' => $v, 'label' => $l);
        }
        return $arr;
    }
}
