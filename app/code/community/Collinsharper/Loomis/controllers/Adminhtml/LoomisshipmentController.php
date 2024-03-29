<?php
/**
 *
 * @category    Collinsharper
 * @package     Collinsharper_Loomis
 * @author      Maxim Nulman
 */
class Collinsharper_Loomis_Adminhtml_LoomisshipmentController extends Mage_Adminhtml_Controller_Action
{

    public function indexAction()
    {

        $this->loadLayout();

        $block = $this->getLayout()->createBlock(
            'Collinsharper_Loomis_Block_Adminhtml_Loomisshipment',
            'shipment'
        );

        $this->_title('Manage Shipments');

        $this->getLayout()->getBlock('content')->append($block);

        $this->renderLayout();

    }


    public function createAction()
    {

        $this->_title(Mage::helper('loomismodule')->__('Manifest'))
            ->_title(Mage::helper('loomismodule')->__('Create Manifest'));

        $this->loadLayout();

        $block = $this->getLayout()->createBlock(
            'Collinsharper_Loomis_Block_Adminhtml_Loomismanifest_Shipment',
            'cpmanifest'
        );

        $this->getLayout()->getBlock('content')->append($block);

        $this->renderLayout();

    }


    public function viewAction()
    {

        $this->_title(Mage::helper('loomismodule')->__('Manifest'))
            ->_title(Mage::helper('loomismodule')->__('View Manifest'));

        $this->loadLayout();

        $block = $this->getLayout()->createBlock(
            'Collinsharper_Loomis_Block_Adminhtml_Manifest_Shipment',
            'manifest'
        );

        $this->getLayout()->getBlock('content')->append($block);

        $this->renderLayout();

    }


    public function massCreateAction()
    {

        $shipment_ids = $this->getRequest()->getParam('shipment_ids');

        $rate = Mage::getModel('loomismodule/rate');
        $rate->createShipments($shipment_ids);
        $this->_redirect('*/*/index');


    }

    public function massVoidAction()
    {
        $shipment_ids = $this->getRequest()->getParam('shipment_ids');
        $rate = Mage::getModel('loomismodule/rate');
        $rate->voidShipment($shipment_ids);
        $this->_redirect('*/*/index');
    }


    /**
     * Mass label action
     *
     * @throws Exception
     *
     * @return boolean
     */
    public function massLabelsAction()
    {
        try {
            $magentoShipmentIds = $this->getRequest()->getParam('shipment_ids');
            $shipmentModel = Mage::getSingleton('loomismodule/shipment');
            $rate = Mage::getModel('loomismodule/rate');
            $outputContent = null;

            $pdf = new Zend_Pdf();

            foreach ($magentoShipmentIds as $magentoShipmentId) {
                $response = $rate->getLabel($magentoShipmentId);

                if (!$response || !isset($response->return) || !isset($response->return->labels)) {
                    throw new Exception("Expected label response from Loomis" . (isset($response->return->error) ? ': ' . $response->return->error : ''));
                }

                $labels = $response->return->labels;
                if(!is_array($labels)) {
                    $labels = array($labels);
                }

                foreach($labels as $label) {


                    $content = base64_decode($label);

                    if ($content == false) {
                        throw new Exception("File content empty! Please try again.");
                    }

                    // DEBUG
                    //file_put_contents(BP . '/loomislabel.png',$content);
                    if ((bool)Mage::app()->getRequest()->getParam('_thermal', false)) {
                        $outputContent .= $content;
                    } else {
                        $pdf = $this->addPage($pdf, $content, $magentoShipmentId);
                    }
                }

            }

            if ((bool)Mage::app()->getRequest()->getParam('_thermal', false)) {
                $filename = 'loomis-labels-' . Mage::getSingleton('core/date')->date('Y-m-d_H-i-s') . '.txt';
                return $this->_prepareDownloadResponse($filename, $outputContent, 'text/html');
            } else {
                $filename = 'loomis-labels-' . Mage::getSingleton('core/date')->date('Y-m-d_H-i-s') . '.pdf';
                return $this->_prepareDownloadResponse($filename, $pdf->render(), 'application/pdf');
            }


        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError(Mage::helper('adminhtml')->__($e->getMessage()));
        }

        $this->_redirect('*/*');
    }

    /**
     * Add page
     *
     * @param type $pdf       PDF File
     * @param type $pdfString PDF Page content
     *
     * @return Object
     */
    public function addPage($pdf, $imageString, $id)
    {
        try {
            // http://office.collinsharper.com/label.png
            $file =  sys_get_temp_dir() . DS . $id . rand() . '.png';
            file_put_contents($file, $imageString);
            $image = Zend_Pdf_Image::imageWithPath($file);
            unlink($file);

            if($this->_isThermal()) {
                //$page = $pdf->newPage(Zend_Pdf_Page::SIZE_LETTER);
                $page = $pdf->newPage('288:432:');
                // small x margin, looks nicer
                $x1 = 2;
                $y1 = 2;
            } else {
                $page = $pdf->newPage(Zend_Pdf_Page::SIZE_LETTER);
                $x1 = 6;
                $y1 = 6;
            }

            $imageWidth = $image->getPixelWidth();
            $imageHeight = $image->getPixelHeight();

            // scale image to page width
            $pageWidth = $page->getWidth();
            $pageHeight = $page->getHeight();
            $scale = ($pageHeight-$y1) / $imageHeight;

            $y2 = $pageHeight;
            $x2 = $x1 + ($imageWidth * $scale);


            if(0 && !$this->_isThermal()) {

                $scale = $pageWidth / $imageWidth;
                $x2 = $pageWidth;
                $y2 = $y1 + ($imageHeight * $scale);

            }


            $page->drawImage($image, $x1, $y1, $x2, $y2);

            $pdf->pages[] = $page;

        } catch(Exception $e) {
            Mage::logException($e);
        }

        return $pdf;
    }

    function _isThermal() {

        return $this->getRequest()->getParam('_thermal');

    }



}
