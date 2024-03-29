<?php

class Collinsharper_Loomis_Adminhtml_LoomisupdateController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $request = $this->getRequest();

        $params = $request->getParams();
        $options = array();


        $valid_opts = array('residential', 'nsr', 'xc');
        foreach($valid_opts as $k) {

            $options[$k] = isset($params[$k]) && $params[$k] == "true";

        }

        if(isset($params['selectedrate'])) {

            $options['selectedrate'] = trim(str_replace('loomismodule_', '', $params['selectedrate']));

        }

        Mage::getSingleton('admin/session')->setData('loomis_options', serialize($options));


        $result['update_section'] = array(
            'name' => 'shipping-method',
            'html' => false
        );

        $this->getResponse()->setBody('');
    }

    public function getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }

}

