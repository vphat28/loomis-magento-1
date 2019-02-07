<?php


class Collinsharper_Loomis_Model_Order_Observer
{
    public function save_loomis_options($observer) {

        $order = $observer->getEvent()->getOrder();
        $loomisOptions = $this->getStoredLoomisOptions();

        if($loomisOptions) {

            $order->setLoomisShippingOptions(serialize($loomisOptions));

            $order->save();

        }

         return $this;

    }

    function getStoredLoomisOptions() {

        return unserialize($this->_getSession()->getData('loomis_options'));

    }

    function _getSession() {

        if (Mage::app()->getStore()->isAdmin()) {
            return Mage::getSingleton('admin/session');
        }

        return mage::getSingleton('core/session');

    }

}