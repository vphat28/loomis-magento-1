<?php

class Collinsharper_Loomis_Block_Adminhtml_Loomisshipment_Renderer_ManifestId extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $manifestNum = $row->getData($this->getColumn()->getIndex());
        $manifest = Mage::getModel('loomismodule/manifest')->load($manifestNum, 'loomis_manifest_num');
        if ($manifest->getId()) {
            $url = $this->getUrl('*/loomismanifest/massPrint', array('manifest_ids'=>$manifest->getId()));
            $return =  '<a href="' . $url . '">' . $manifestNum . '</a>';
        } else {
            $return = $manifestNum;
        }
        return $return;
    }
}
