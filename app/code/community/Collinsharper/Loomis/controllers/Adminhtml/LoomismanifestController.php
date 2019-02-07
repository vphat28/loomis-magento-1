<?php
/**
 *
 * @category    Collinsharper
 * @package     Collinsharper_Loomis
 * @author      Maxim Nulman
 */
class Collinsharper_Loomis_Adminhtml_LoomismanifestController extends Mage_Adminhtml_Controller_Action
{

    public function indexAction()
    {

        $this->loadLayout();

        $block = $this->getLayout()->createBlock(
            'Collinsharper_Loomis_Block_Adminhtml_Loomismanifest',
            'loomismanifest'
        );

        $this->_title('Manage Manifests');

        $this->getLayout()->getBlock('content')->append($block);

        $this->renderLayout();

    }


    public function createAction()
    {

        $rate = Mage::getModel('loomismodule/rate');
     // TODO: why are we trying to do this first?
        //$rate->getManifestShipments();
        $result = $rate->endOfDay();
        //Manifest created
        if ($result != false) {

            $manifest = Mage::getModel('loomismodule/manifest');
            $manifest->setLoomisManifestNum($result->return->manifest_num);
            $manifest->save();

// we dont need to get anything here
            $rate->updateManifestShipments($result->return->manifest_num);
            $this->_getSession()->addSuccess($this->__('Manifest created. Shipments updated.'));

        } else {

            $this->_getSession()->addError($this->__('Manifest not created. Please review system logs.'));

        }


        $this->_redirect('*/*/index');
    }

    public function getManifestsAction()
    {
        $rate = Mage::getModel('loomismodule/rate');
        $rate->getManifestShipments();
//        $rate = Mage::getModel('loomismodule/rate');
//        $result = $rate->endOfDay();
//        //Manifest created
//        if ($result != false) {
//            $manifest = Mage::getModel('loomismodule/manifest');
//            $manifest->setLoomisManifestNum($result->return->manifest_num);
//            $manifest->save();
//        }
        $this->_redirect('*/*/index');
    }

    public function viewAction()
    {

        $this->_title(Mage::helper('loomismodule')->__('Manifest'))
            ->_title(Mage::helper('loomismodule')->__('View Manifest'));

        $this->loadLayout();

        $block = $this->getLayout()->createBlock(
            'Collinsharper_Loomis_Block_Adminhtml_Manifest_View',
            'manifest'
        );

        $this->getLayout()->getBlock('content')->append($block);

        $this->renderLayout();

    }

    public function printmanifestAction()
    {

        $rate = Mage::getModel('loomismodule/rate');
        $mid = $this->getRequest()->getParam('manifest_id', false);
        $pdf_manifest = $rate->getManifestPdfById($mid);

        if(!$mid || !$pdf_manifest) {

            $this->_getSession()->addError($this->__('Unable to find manifest.'));
            $this->_redirect('*/*/');
            return;

        }

        return $this->_prepareDownloadResponse('manifest'.Mage::getSingleton('core/date')->date('Y-m-d_H-i-s').'.pdf', $pdf_manifest, 'application/pdf');
    }

    public function massPrintAction()
    {
        try {
            $manifestIds = $this->getRequest()->getParam('manifest_ids');

            if(!is_array($manifestIds) && is_numeric($manifestIds)) {
                $manifestIds = array($manifestIds);
            }

            $rate = Mage::getModel('loomismodule/rate');
            $resultPdf = new Zend_Pdf();

            foreach ($manifestIds as $mid) {
                $manifestPdfString = $rate->getManifestPdfById($mid);
                $manifestPdf = new Zend_Pdf($manifestPdfString);

                foreach ($manifestPdf->pages as $page) {
                    $resultPdf->pages[] = clone $page;
                }
            }

            $filename = 'loomis-manifests-' . Mage::getSingleton('core/date')->date('Y-m-d_H-i-s') . '.pdf';
            return $this->_prepareDownloadResponse($filename, $resultPdf->render(), 'application/pdf');

        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError(Mage::helper('adminhtml')->__($e->getMessage()));
        }

        $this->_redirect('*/*');
    }
}
