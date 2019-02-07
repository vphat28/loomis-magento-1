<?php

class Collinsharper_Loomis_IndexController extends Mage_Core_Controller_Front_Action
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

        Mage::getSingleton('core/session')->setData('loomis_options', serialize($options));

        $cart = Mage::getSingleton('checkout/cart');
        $address = $cart->getQuote()->getShippingAddress();
        $address->setCollectShippingrates(true);
        $cart->getQuote()->setTotalsCollectedFlag(false);
        $cart->getQuote()->collectTotals();
        $cart->getQuote()->save();
        $cart->save();

        $layout = $this->getLayout();
        $update = $layout->getUpdate();
        $update->load('checkout_onepage_shippingmethod');
        $layout->generateXml();

        $layout->generateBlocks();
        $output = $layout->getOutput();

        $result['update_section'] = array(
            'name' => 'shipping-method',
            'html' => $output
        );
        // do not unset session

        $this->getResponse()->setBody($output);
        mage::log(__METHOD__ . " options " . print_r( Mage::getSingleton('core/session')->getData('loomis_options'),1));
    }

    public function getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }

}

