<?php
/**
 * Created by Gayan Hewa
 * User: Gayan
 * Date: 7/17/13
 * Time: 2:30 AM
 */

class Collinsharper_Loomis_Model_Rate extends Mage_Core_Model_Abstract
{
    protected $_helper;
    protected $_constraints;

    public function __construct()
    {

        $this->_helper = Mage::helper('loomismodule');
        $this->_type = $this->_helper->getServiceType();

        $this->_constraints = Mage::helper('loomismodule')->getServiceConstraints();
    }

    public function createRateRequest($request)
    {
        //Debugging information

        //Create the request object
        $rate_request = array();
        $rate_request["delivery_country"] = $request["dest_country_id"];
        $rate_request["delivery_postal_code"] = $request["dest_postcode"];
        $rate_request["pickup_postal_code"] = $request["postcode"];
        $current_date = gmDate("Ymd");
        $rate_request["shipping_date"] = $current_date;
        $rate_request["shipper_num"] = $this->_helper->getConfig('shippingaccount');
        $rate_request["user_id"] = $this->_helper->getConfig('email');
        $rate_request["password"] = $this->_helper->getConfig('password');

        $client = $this->_helper->getClient('rating');

        $response = $client->getAvailableServices(array("request" => $rate_request));

        /*$this->_helper->log(__METHOD__ . __LINE__ . print_r($rate_request,1));
        $this->_helper->log(__METHOD__ . __LINE__ . print_r($response,1));

        $this->_helper->log(__METHOD__ . __LINE__ .  "====== REQUEST HEADERS =====");
        $this->_helper->log(__METHOD__ . __LINE__ . print_r($client->__getLastRequestHeaders(),1));
        $this->_helper->log(__METHOD__ . __LINE__ .  "========= REQUEST ==========");
        $this->_helper->log(__METHOD__ . __LINE__ . print_r($client->__getLastRequest(),1));
        $this->_helper->log(__METHOD__ . __LINE__ .  "========= RESPONSE =========");
        $this->_helper->log(__METHOD__ . __LINE__ . print_r($client->__getLastResponse(),1));
        $this->_helper->log(__METHOD__ . __LINE__ . print_r($response,1));*/

        if ($response->return->error != "") {

//            Mage::getSingleton('core/session')->addError("Error in create Rate Request %s " , Mage::helper('loomismodule')->__($response->return->error));
            $this->_helper->log("Error occured when calling getAvailableServices method.");
            $this->_helper->log(__METHOD__ . print_r($response,1));
            return false;

        }

        return $response;
    }


    public function getRate($request, $type, $weight)
    {

        $session = Mage::getSingleton('core/session');

        if (Mage::app()->getStore()->isAdmin()) {
            $session = Mage::getSingleton('admin/session');
        }

        $options = unserialize($session->getData('loomis_options'));
        $nsr = 0;
        $res = 0;
        $xc = 0;

        $selectedrate = isset($options['selectedrate']) && $options['selectedrate'] == (string)$type->type ? $type->type : false;

        if ($selectedrate) {

            if (isset($options["nsr"]) && $options["nsr"] == true) {

                $nsr = 2;

            }

            if (isset($options["residential"]) && $options["residential"] == true) {

                $res = true;

            }

            if (isset($options["xc"]) && $options["xc"] == true) {

                $xc = true;

            }

        }


        $rate_request = array();

        //$rate_request["apply_association_discount"] = $this->_helper->getConfigFlag('association_disc');
        //$rate_request["apply_individual_discount"] = $this->_helper->getConfigFlag('individual_disc');
        //$rate_request["apply_invoice_discount"] = $this->_helper->getConfigFlag('invoice_disc');
        $rate_request["password"] = $this->_helper->getConfig('password');
        $rate_request["user_id"] = $this->_helper->getConfig('email');
        $current_date = gmDate("Ymd");

        //get customer
        $quote = Mage::helper('loomismodule')->getQuote();
        $origin = Mage::getStoreConfig('shipping/origin', $this->getStore());
        $region = Mage::getModel('directory/region')->load($origin["region_id"]); //$this->getStore());

        //Shopping cart page , Estimates hack - checkout/cart/. The default string can be any random string
        $address_line_1 = "address line one";
        if ($this->_helper->chop($request["dest_street"], 0, 40) != ""){
            $address_line_1 = $this->_helper->chop($request["dest_street"], 0, 40);
        }

        //Shipment related information
        $rate_request["shipment"] = array(
            "courier" => 'L', // MUST BE Loomis
            'dimention_unit' => $this->_helper->getConfig('default_dimentional_unit'),
            "reported_weight_unit" => $this->_helper->getConfig('default_weight_unit'),
            "service_type" => $type->type,
            "shipper_num" => $this->_helper->getConfig('shippingaccount'),
            "shipping_date" => $current_date,
            "nsr" => $nsr,//$this->_helper->getConfig('nsr'),
            "dangerous_goods" => $this->_helper->getConfigFlag('dg'),
            "instruction" => $this->_helper->getConfig('instruction'),

            "delivery_address_line_1" => $address_line_1,
            "delivery_city" => $this->_helper->chop($request["dest_city"], 0, 30),
            "delivery_country" => $request["dest_country_id"],
            "delivery_email" => $this->_helper->chop($quote->getData('customer_email'), 0, 256),
            "delivery_name" => $this->_helper->chop($quote->getData('customer_firstname') ? ($quote->getData('customer_firstname') . " " . $quote->getData('customer_lastname')) : 'John Doe', 0, 40),
            "delivery_postal_code" => str_replace(' ', '', $request["dest_postcode"]),
            "delivery_province" => $request["dest_region_code"],
            "delivery_residential" => $res,//false,

            "pickup_address_line_1" => $origin["street_line1"],
            "pickup_address_line_2" => $origin["street_line2"],
            "pickup_city" => $this->_helper->chop($origin["city"], 0, 40),
            "pickup_country" => $origin["country_id"],
            "pickup_email" => $this->_helper->chop(Mage::getStoreConfig('trans_email/ident_general/email'), 0, 40),
            "pickup_name" => $this->_helper->chop(Mage::getStoreConfig('trans_email/ident_general/name'), 0, 40),
            "pickup_postal_code" => str_replace(' ', '', $origin["postcode"]),
            "pickup_province" => $region->getCode(),
            "pickup_residential" => $this->_helper->getConfigFlag('pickup_is_residential'),
        );
		
        $rate_request["shipment"]["packages"] = $this->createPackages($request, $type->type);

        $req = array();
        $req["request"] = $rate_request;
        $client = $this->_helper->getClient('rating');
        //Save the last request rate request object
        
        Mage::getSingleton('core/session')->setRaterequest($req);

        //$response = $client->rateShipment($req); //NOT IMPLEMENTED AS PER DOCUMENTATION
        $response = $client->getRates($req);

        $this->_helper->log(__METHOD__ . __LINE__ . print_r($req,1));
        $this->_helper->log(__METHOD__ . __LINE__ . print_r($response,1));

        /*$this->_helper->log(__METHOD__ . __LINE__ .  "====== REQUEST HEADERS =====");
        $this->_helper->log(__METHOD__ . __LINE__ . print_r($client->__getLastRequestHeaders(),1));
        $this->_helper->log(__METHOD__ . __LINE__ .  "========= REQUEST ==========");
        $this->_helper->log(__METHOD__ . __LINE__ . print_r($client->__getLastRequest(),1));
        $this->_helper->log(__METHOD__ . __LINE__ .  "========= RESPONSE =========");
        $this->_helper->log(__METHOD__ . __LINE__ . print_r($client->__getLastResponse(),1));
        $this->_helper->log(__METHOD__ . __LINE__ . print_r($response,1)); */

        if ($response->return->error != "") {

          //  Mage::getSingleton('core/session')->addError(Mage::helper('loomismodule')->__($response->return->error));
          $this->_helper->log("Error occured when calling  getRates method.");
          $this->_helper->log(__METHOD__ . print_r($response,1));
            return false;

        }

        return $response;

    }

    /*
     *  TODO : For Boxing for Shipments
     */
    public function getTheBoxDetails($request) {

    }

    /*
     *	$request can be Mage::helper('loomismodule')->getQuote() or Mage::getModel('sales/order')->load($shipment->getOrderId()); Both objects have method getAllItems;
     */
    public function createPackages($request, $shipmentType)
    {

		$chunit = Mage::helper('chunit');
        $default_weight_unit = Mage::getStoreConfig('catalog/measure_units/weight');
        $default_dim_unit = Mage::getStoreConfig('catalog/measure_units/length');

        //Apply constraints. Reset the max_box_weight to the constraints if it is over the constraint. Change all unit to lb (consist with the _constraint array)

        $max_box_weight = $chunit->getConvertedWeight($this->_helper->getConfig('max_box_weight'), strtolower($this->_helper->getConfig('default_weight_unit')), 'lb');

        if ($max_box_weight > $this->_constraints[$shipmentType]['max']){
            $max_box_weight = $this->_constraints[$shipmentType]['max'];
        }elseif($max_box_weight < $this->_constraints[$shipmentType]["min"]){
            $max_box_weight = $this->_constraints[$shipmentType]["max"];
        }elseif($max_box_weight == 0){
			$max_box_weight = $this->_constraints[$shipmentType]["max"];
		}
		
		//Make sure there is no item in the order heavier than the max box weight
		$maxItemWeight = 0;
		foreach($request->getAllItems() as $item) {
					$product_id = $item->getProductId();
					$product = Mage::getModel('catalog/product')->load($product_id);
					
					// make sure each item has the weight in lb
					$pWeight = $chunit->getConvertedWeight($product->getWeight(), Mage::getStoreConfig('catalog/measure_units/weight'), "lb");

			if($pWeight > $maxItemWeight) {
				$maxItemWeight = $pWeight;
			}
		}

		// Calculate number of boxes needed
		$weight_in_box = 0;
		$num_boxes = 1;
		foreach ($request->getAllItems() as $item){
			
			// Skip the configurable item itself. Only take its associate product.
			if ($item->getProductType() == "configurable"){
				continue;
			}
			
			$product_id = $item->getProductId();
			$product = Mage::getModel('catalog/product')->load($product_id);
			$pWeight = $chunit->getConvertedWeight($product->getWeight(), Mage::getStoreConfig('catalog/measure_units/weight'), "lb");
			$itemCount = $item->getQty();

			for($idx = 0; $idx < $itemCount; $idx++){
				$weight_in_box += $pWeight;
				
				if($weight_in_box > $max_box_weight){
										
					$num_boxes++;
					$weight_in_box = 0;
				}               
			}            
		}
		
		$height = $this->_helper->getConfig('height');
		$width = $this->_helper->getConfig('width');
		$length = $this->_helper->getConfig('length');

		//Create a package array for each box
		for($inx = 0; $inx < $num_boxes; $inx++) {
			$package{$inx} = array(
				"declared_value" => 0,
				"height" => $chunit->getConvertedLength($height, $this->_helper->getConfig('default_dimentional_unit'), $default_dim_unit),
				"length" => $chunit->getConvertedLength($length, $this->_helper->getConfig('default_dimentional_unit'), $default_dim_unit),
				"width" => $chunit->getConvertedLength($width, $this->_helper->getConfig('default_dimentional_unit'), $default_dim_unit),
				"reported_weight" => 0,
				"store_num" => $this->getStore(),
				//"xc" => $xc,//$this->_helper->getConfig('xc'),
			);			
		}

		// Start putting the products in the boxes
		$weight_in_box = 0;
		$box_index = 0;
		// Note: configurable product is considered as two items (configurable product itself and its associate product(simple product)), and the configurable itself has declared value whereas its associate product has no declared value ($item->getPrice() = 0), so the follow code will skip the configurable product but save its declared value in the varialbe $declaredValueForConfigurable for the next product (its associate product).
		$declaredValueForConfigurable = 0;
		foreach($request->getAllItems() as $item) {
			
			$declared_value = $item->getPrice();
	
			// don't ship configurable item itself. Ship only the simple item instead.
			if ($item->getProductType() == "configurable"){
				// save the declared value for the next item (its associate product);
				$declaredValueForConfigurable = $item->getPrice();
				continue;
			}elseif($declaredValueForConfigurable != 0){
				// if it is associate product ($declaredValueForConfigurable != 0), retrieve the declared value from $declaredValueForConfigurable and reset $declaredValueForConfigurable to 0
				$declared_value = $declaredValueForConfigurable;
				$declaredValueForConfigurable = 0;
			}

			$product_id = $item->getProductId();
			$product = Mage::getModel('catalog/product')->load($product_id);
			$pWeight = $chunit->getConvertedWeight($product->getWeight(), Mage::getStoreConfig('catalog/measure_units/weight'), "lb");
				
			//Get the quantity of each item. Since the returned object of Mage::helper('loomismodule')->getQuote() and Mage::getModel('sales/order')->load($shipment->getOrderId()) have different methods for getting qty, we have to check which method we are going to use first.
			$itemCount = 0;
			if (method_exists($item, "getQty")){

				$itemCount = $item->getQty();
				
			}else{
				
				$itemCount = $item->getQtyOrdered();

			}

			//Start putting items in boxes
			for($idx = 0; $idx < $itemCount; $idx++) {
			
				$weight_in_box += $pWeight;

				if ($weight_in_box > $max_box_weight){
					$box_index++;
					$weight_in_box = $pWeight;
	 
				}
					
				$package{$box_index}['reported_weight'] += $pWeight;
				$package{$box_index}['declared_value'] += $declared_value;
						
			}
		}

		// initialize the array "$package"
		$packages = array();		
		//Add our packages to the array "$packages"
		for($inx = 0; $inx < $num_boxes; $inx++) {
			array_push($packages, $package{$inx});
		}		

		return $packages;
    }

    /*
     *  Submit Manifest
     */
    public function submitManifest($request) {

    }

    public function createShipments($shipment_ids) {

        foreach ($shipment_ids as $id) {

            $rec = Mage::getModel('loomismodule/shipment')->load($id, 'magento_shipment_id')->getData();
            if (empty($rec)) {

                $response = $this->createShipment($id);
                if ($response != false) {

                    // TODO: must loop over each package to get the barcode and ad each as a seperate tracking number.
                    $shipment = Mage::getModel('loomismodule/shipment');
                    $shipment->setShipmentId($response->return->processShipmentResult->shipment->id);
                    $shipment->setMagentoShipmentId($id);
                    $shipment->setCreatedDate(date('Y-m-d'));
                    $shipment->setTrackingCode((string)$response->return->processShipmentResult->shipment->packages->barcode);
                    $shipment->save();

                    $shipment = Mage::getModel('sales/order_shipment')->load($id);
                    $track = Mage::getModel('sales/order_shipment_track')
                        ->setNumber((string)$response->return->processShipmentResult->shipment->packages->barcode)
                        ->setCarrierCode('loomismodule')
                        ->setTitle('Loomis Shipping');
                    $shipment->addTrack($track)
                        ->save();

                }
            } else {

                Mage::getSingleton('core/session')->addError(Mage::helper('loomismodule')->__('Shipment #'.$id.' alerady exists.'));

            }
        }

        Mage::getSingleton('core/session')->addSuccess(Mage::helper('loomismodule')->__('Loomis Shipments Created.'));

        return true;
    }

    public function isTrackingAvailable()
    {
        return true;
    }

    public function createShipment($id) {

        $chunit = Mage::helper('chunit');
        $default_weight_unit = Mage::getStoreConfig('catalog/measure_units/weight');
        $default_dim_unit = Mage::getStoreConfig('catalog/measure_units/length');

        $shipment = Mage::getModel('sales/order_shipment')->load($id);
        $order = $order = Mage::getModel('sales/order')->load($shipment->getOrderId());

        $ops = unserialize($order->getLoomisShippingOptions());
        $nsr = 0;
        $res = 0;
        $xc = 0;

        //TODO: allow admin forced values for these

        if ($ops != false && is_array($ops)) {

            if (isset($ops["nsr"]) && $ops["nsr"] == true) {

                $nsr = 2;

            }

            if (isset($ops["residential"]) && $ops["residential"] == true) {

                $res = true;

            }

            if (isset($ops["xc"]) && $ops["xc"] == true) {

                $xc = true;

            }

        }


        $origin = Mage::getStoreConfig('shipping/origin', $this->getStore());
        $region = Mage::getModel('directory/region')->load($origin["region_id"]); //$this->getStore());
        $weight = Mage::helper('loomismodule')->getPackageWeightLb($order);
        //Minimum weight will always be 1lb if it falls below.

        if ($weight < 1) {

            $weight = 1;

        }

        $current_date = gmDate('Ymd');
        $shipping_method = explode('_', $order->getShippingMethod());

        //TODO: handle a way to use order comments for delivery instructions?
        $request = array();
        $request["password"] =  $this->_helper->getConfig('password');
        $request["user_id"] =  $this->_helper->getConfig('email');

        $request["shipment"] = array(
            "cod_type" => "N",
            "collect" => true,
//            "description" => "Description",
            "dg" => $this->_helper->getConfigFlag('dg'),
            "handling" => $this->_helper->getConfig('handling'),
            "handling_type" => $this->_helper->getConfig('handling_type'),
            "instruction" => $this->_helper->getConfig('instruction'),
            "nsr" => $nsr,
            "premium" => "N",
            "reported_weight_unit" => $this->_helper->getConfig('default_weight_unit'),
            "send_email_to_delivery" => true,
            "send_email_to_pickup" => true,
            "service_type" => $shipping_method[1],
            "shipper_num" => $this->_helper->getConfig('shippingaccount'),
            "shipping_date" => $current_date,
            'dimention_unit' => $this->_helper->getConfig('default_dimentional_unit')
        );

        $_shippingAddress = $order->getShippingAddress();
        //Shopping cart page , Estimates hack - checkout/cart/. The default string can be any random string
        $address_line_1 = "address line one";

        if($_shippingAddress->getStreetFull()) {
            $address_line_1 = substr($_shippingAddress->getStreetFull(), 0, 40);

            if (strlen($_shippingAddress->getStreetFull()) > 40) {

                $address_line_1 = trim(substr($_shippingAddress->getStreetFull(), 0, 40));

                $address_line_2 = trim(substr($_shippingAddress->getStreetFull(), 40, 80));

                if(strlen($_shippingAddress->getStreetFull()) > 80) {

                    $address_line_3 = trim(substr($_shippingAddress->getStreetFull(), 80, 40));

                }

            }

        }

        $request["shipment"] = array_merge( $request["shipment"], array(
            "delivery_address_line_1" => $address_line_1,
            //"delivery_attention" => $this->_helper->chop($order->getData('customer_firstname') ? $order->getData('customer_firstname') : 'John Doe', 0, 40),
            "delivery_city" => $this->_helper->chop($_shippingAddress->getCity(), 0, 30),
            "delivery_country" => $_shippingAddress->getCountryId(),
            "delivery_email" => $this->_helper->chop($order->getData('customer_email'), 0, 256),
            "delivery_name" => $this->_helper->chop($order->getData('customer_firstname') ? ($order->getData('customer_firstname') . " " . $order->getData('customer_lastname')) : 'John Doe', 0, 40),
            "delivery_postal_code" => str_replace(' ', '', $_shippingAddress->getPostcode()),
            "delivery_province" => $_shippingAddress->getRegionCode() ? $_shippingAddress->getRegionCode() : $_shippingAddress->getRegion(),
            "delivery_residential" => $res,//false,
        ));

        if(isset($address_line_2) && $address_line_2 != '') {

            $request["shipment"]["delivery_address_line_2"] = $address_line_2;

        }

        if(isset($address_line_3) && $address_line_3 != '') {

            $request["shipment"]["delivery_address_line_3"] = $address_line_3;

        }

        $request["shipment"] = array_merge( $request["shipment"], array(
            "pickup_address_line_1" => $origin["street_line1"],
            "pickup_address_line_2" => $origin["street_line2"],
            "pickup_city" => $this->_helper->chop($origin["city"], 0, 40),
            "pickup_country" => $origin["country_id"],
            "pickup_email" => $this->_helper->chop(Mage::getStoreConfig('trans_email/ident_general/email'), 0, 40),
            "pickup_name" => $this->_helper->chop(Mage::getStoreConfig('trans_email/ident_general/name'), 0, 40),
            "pickup_postal_code" => str_replace(' ', '', $origin["postcode"]),
            "pickup_province" => $region->getCode(),
            //"residential" => $this->_helper->getConfigFlag('pickup_is_residential'),
        ));

		$request["shipment"]["packages"] = $this->createPackages($order, $request["shipment"]['service_type']);


        $client = $this->_helper->getClient('business');
        $response = $client->processShipment(array('request'=>$request));

        $this->_helper->log(__METHOD__ . print_r($request,1));

        $this->_helper->log(__METHOD__ . print_r($response,1));

        if ($response->return->error != "") {

            Mage::getSingleton('core/session')->addError("Error  in create shipment %s", Mage::helper('loomismodule')->__($response->return->error));
            $this->_helper->log("Error occured when calling  processShipment method.");
            
            return false;

        }

        return $response;
    }

    public function endOfDay()
    {
        $request = array();
        $request["date"] = gmDate("Ymd");
        $request["password"] =  $this->_helper->getConfig('password');
        $request["user_id"] =  $this->_helper->getConfig('email');
        $request["shipper_num"] = $this->_helper->getConfig('shippingaccount');

        $client = $this->_helper->getClient('business');
        $response = $client->endOfDay(array('request'=>$request));

        $this->_helper->log(__METHOD__ . __LINE__ . print_r($request,1));
        $this->_helper->log(__METHOD__ . __LINE__ . print_r( $response,1));

        if ($response->return->error != "") {
            Mage::getSingleton('core/session')->addError(Mage::helper('loomismodule')->__("Error in End of Day %s" , $response->return->error));
            $this->_helper->log("Error occured when calling  processShipment method.");
            return false;
        }
        //Check ...
        $this->updateManifestShipments($response->return->manifest_num);
        return $response;
    }

    public function getManifestPdfById($mid, $type = 'F')
    {
        /*
        * S = SUMMARY
          D = DETAIL
          F = FULL DETAIL
        */
        $manifest = Mage::getModel('loomismodule/manifest')->load($mid);

        $man_types = array('S', 'D', 'F');

        if(!isset($man_types[$type])) {

            $type = 'F';

        }

        $client = $this->_helper->getClient('business');

        $request = array();

        $request["manifest_num"] = $manifest->getLoomisManifestNum();
        $request["type"] = $type;
        $request["user_id"] =  $this->_helper->getConfig('email');
        $request["password"] =  $this->_helper->getConfig('password');
        $request["shipper_num"] = $this->_helper->getConfig('shippingaccount');

        $response = $client->getManifest(array('request'=>$request));

        if ($response->return->error != "" || !$response->return->manifest) {
            Mage::getSingleton('core/session')->addError(Mage::helper('loomismodule')->__("Error in Gettinng manifest %s", $response->return->error));
            $this->_helper->log("Error occured when calling  processShipment method.");
            return false;
        }

        return base64_decode($response->return->manifest);
    }

    public function getManifestShipments($manifest)
    {

        if(!is_object($manifest)) {
            $date = date('Y-m-d');
            $manifest_id = $manifest;
            $manifest = Mage::getModel('loomismodule/manifest')->load($manifest);
        }

        if(is_object($manifest) && $manifest->getcreatedDate()) {

            $date = $manifest->getcreatedDate();

        }

        $client = $this->_helper->getClient('business');

        $request = array();

        $this->_helper->log(__METHOD__  . __LINE__ );

        $request["manifest_num"] = $manifest_id;
        $request["user_id"] =  $this->_helper->getConfig('email');
        $request["password"] =  $this->_helper->getConfig('password');
        $request["shipper_num"] = $this->_helper->getConfig('shippingaccount');


        $this->_helper->log(__METHOD__  . __LINE__ );

        $request["from_shipping_date"] = date('Y-m-d\T00:00:00\Z', strtotime($date)-172800);
        $request["to_shipping_date"] = date('Y-m-d\T23:59:59\Z', strtotime($date)+172800);

        $this->_helper->log(__METHOD__  . __LINE__ );

        $response = $client->searchShipmentsByManifestNum(array('request'=>$request));

        $this->_helper->log(__METHOD__  . __LINE__ );

        $this->_helper->log(__METHOD__ . __LINE__ .  print_r($request,1));
        $this->_helper->log(__METHOD__ . __LINE__ .  print_r($response,1));

        if ($response->return->error != "" || !$response->return->shipment) {
            Mage::getSingleton('core/session')->addError(Mage::helper('loomismodule')->__("Error in finding shipments by manifest %s", $response->return->error));
            $this->_helper->log("Error occured when calling  getManifestShipments method.");
            return false;
        }
            return $response;
    }

    public function getShipmentDetails($shipment)
    {

        $rec = Mage::getModel('loomismodule/shipment')->load($shipment->getId(), 'magento_shipment_id');
        $timezone = new DateTimeZone('GMT');
        //reset the date to now
        $datetime = new DateTime(strtotime($shipment->getCreateAt()), $timezone);
        $datetimeto = new DateTime(strtotime($shipment->getCreateAt()), $timezone);

        $request = array();
        $datetime->modify('-2 days');
        $datetimeto->modify('+5 days');
        $request["from_shipping_date"] = $datetime->format('Y-m-d\TH:i:s\Z');
        $request["to_shipping_date"] = $datetimeto->format('Y-m-d\TH:i:s\Z');

        $request["barcode"] = $rec->getTrackingCode();
        $request["password"] =  $this->_helper->getConfig('password');
        $request["user_id"] =  $this->_helper->getConfig('email');
        $request["shipper_num"] = $this->_helper->getConfig('shippingaccount');


        $client = $this->_helper->getClient('addson');

        $response = $client->TrackByBarcode(array('request'=>$request));

        $this->_helper->log(__METHOD__ . print_r($request,1));
        $this->_helper->log(__METHOD__ . print_r($response,1));

        if ($response->return->error != "") {
            Mage::getSingleton('core/session')->addError("Error in get shipment details %s", Mage::helper('loomismodule')->__($response->return->error));
            $this->_helper->log("Error occured when calling  processShipment method.");
            $this->_helper->log(__METHOD__ . print_r($response,1));
            return false;
        } else {

            foreach($response->return->shipment as $shipment) {
                $shipment = Mage::getModel('loomismodule/shipment');
                $rec = Mage::getModel('loomismodule/shipment')->load($shipment->id, 'shipment_id');
                $rec->setManifestId($request["manifest_num"]);
                $rec->save();
            }

        }

        return $response;
    }

    public function updateManifestShipments($manifest_id)
    {
         $response = $this->getManifestShipments($manifest_id);

        $this->_helper->log(__METHOD__ . __LINE__ . print_r($manifest_id,1));
        $this->_helper->log(__METHOD__ . __LINE__ . print_r($response,1));

        if (!$response ||  $response->return->error != "") {

            Mage::getSingleton('core/session')->addError( Mage::helper('loomismodule')->__("Error in update shipments with manifest %s", $response->return->error));
            $this->_helper->log("Error occured when calling getManifestShipments.");
            return false;

        } else {

            $shipments = array();
            if(!is_array($response->return->shipment)) {

                $shipments[] = $response->return->shipment;

            } else {

                $shipments = $response->return->shipment;

            }

            foreach($shipments as $shipment) {

                $rec = Mage::getModel('loomismodule/shipment')->load($shipment->id, 'shipment_id');

                if(!$shipment->id || !$rec->getId()) {

                    $this->_helper->log(__METHOD__ . __LINE__ . "Missing information ($manifest_id) ");
                    continue;

                }

                $rec->setData('manifest_id',$manifest_id);
                $rec->save();

            }

        }

        return $response;
    }

    public function voidShipment($shipment_ids)
    {
        foreach($shipment_ids as $id) {

            $shipment = Mage::getModel('sales/order_shipment')->load($id);

            $rec = Mage::getModel('loomismodule/shipment')->load($id, 'magento_shipment_id');

            $request = array();
            $errors = false;
            $request["password"] =  $this->_helper->getConfig('password');
            $request["user_id"] =  $this->_helper->getConfig('email');
            $request["id"] = $rec->getShipmentId();
            $bcode = $rec->getTrackingCode();

            $client = $this->_helper->getClient('business');
            $response = $client->voidShipment(array('request'=>$request));
            $this->_helper->log(__METHOD__ . print_r($request,1));
            $this->_helper->log(__METHOD__ . print_r($response,1));

            if ($response->return->error != "") {

                $this->_helper->log("Error occured when calling  processShipment method.");
                $this->_helper->log(__METHOD__ . print_r($response,1));
                Mage::getSingleton('core/session')->addError(Mage::helper('loomismodule')->__("Error in voiding shipment (%s) (%s)", $shipment->getIcrementId(), $response->return->error));
                $errors = true;

            } else {

                $rec->delete();

                // remove the corresponding tracking number.
                foreach($shipment->getAllTracks() as $track) {
                    if($track->getTrackNumber() == $bcode) {
                        $track->delete();
                    }
                }
            }

        }

        if(!$errors) {

            $msg = "The shipment was voided.";

            if(count($shipment_ids) > 1) {

                $msg = "All shipments were voided";

            }

            Mage::getSingleton('core/session')->addSuccess(Mage::helper('loomismodule')->__($msg));

        }

    }

    public function getLabel($magentoShipmentId)
    {
        $shipment = Mage::getModel('loomismodule/shipment');
        $rec = Mage::getModel('loomismodule/shipment')->load($magentoShipmentId, 'magento_shipment_id');

        $request = array();

        $request["id"] = $rec->getShipmentId();
        $request["password"] =  $this->_helper->getConfig('password');
        $request["user_id"] =  $this->_helper->getConfig('email');
        $request["thermal"] =  (bool)Mage::app()->getRequest()->getParam('_thermal', false); //$this->_helper->getConfig('is_thermal');

        $client = $this->_helper->getClient('business');
        $response = $client->getLabels(array('request'=>$request));

        if ($response->return->error != "") {
            $this->_helper->log("Error occured when calling processShipment method.");
            $this->_helper->log(__METHOD__ . print_r($response,1));
            throw new Exception(Mage::helper('loomismodule')->__('Error getting shipping label: %s', $response->return->error));
        }

        return $response;
    }

}
