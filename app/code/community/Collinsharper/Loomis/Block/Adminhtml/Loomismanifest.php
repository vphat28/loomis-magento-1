<?php
/**
 * Created by Gayan Hewa
 * User: Gayan
 * Date: 9/15/13
 * Time: 8:45 PM
 */

class Collinsharper_Loomis_Block_Adminhtml_Loomismanifest extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {

        $this->_blockGroup = 'loomismodule';
        $this->_controller = 'adminhtml_loomismanifest';
        $this->_headerText = Mage::helper('sales')->__('Loomis Manifests');
        parent::__construct();
        $this->_removeButton('add');
        $this->_addButton('add', array(
            'label'     => Mage::helper('loomismodule')->__('Run End Of Day'),
            'onclick'   => 'setLocation(\''.$this->getUrl('*/*/create').'\');',
            'class'     => 'add',
        ));
    }
}