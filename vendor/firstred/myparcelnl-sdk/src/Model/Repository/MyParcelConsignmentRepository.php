<?php

/**
 * The repository of a MyParcel consignment
 *
 * If you want to add improvements, please create a fork in our GitHub:
 * https://github.com/myparcelnl
 *
 * @author      Reindert Vetter <reindert@myparcel.nl>
 * @copyright   2010-2017 MyParcel
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US  CC BY-NC-ND 3.0 NL
 * @link        https://github.com/myparcelnl/sdk
 * @since       File available since Release v0.1.0
 */
namespace MyParcelModule\MyParcelNL\Sdk\src\Model\Repository;

use MyParcelModule\MyParcelNL\Sdk\src\Model\MyParcelConsignment;
use MyParcelModule\MyParcelNL\Sdk\src\Model\MyParcelCustomsItem;
/**
 * The repository of a MyParcel consignment
 *
 * Class MyParcelConsignmentRepository
 *
 * @package MyParcelNL\Sdk\Model\Repository
 */
class MyParcelConsignmentRepository extends \MyParcelModule\MyParcelNL\Sdk\src\Model\MyParcelConsignment
{
    /**
     * Regular expression used to split street name from house number.
     *
     * This regex goes from right to left
     * Contains php keys to store the data in an array
     */
    const SPLIT_STREET_REGEX = '~(?P<street>.*?)\\s?(?P<number>\\d{1,4})[/\\s\\-]{0,2}(?P<number_suffix>[a-zA-Z]{1}\\d{1,3}|-\\d{1,4}|\\d{2}\\w{1,2}|[a-zA-Z]{1}[a-zA-Z\\s]*)?$~';
    /**
     * Regular expression used to split street name from house number.
     */
    const SPLIT_STREET_REGEX_BE = '~^(?P<street>.*?)\\s(?P<street_suffix>(?P<number>[^\\s#]{1,8})\\s*(?P<box_separator>(bus|Bus|boîte|Boîte|boite|Boite|box|Box|bte|Bte|app|App|appt|Appt|/|\\\\|#)?)?\\s*(?P<box_number>\\d{0,8}$))$~';
    /**
     * Consignment types
     */
    const DELIVERY_TYPE_MORNING = 1;
    const DELIVERY_TYPE_STANDARD = 2;
    const DELIVERY_TYPE_NIGHT = 3;
    const DELIVERY_TYPE_RETAIL = 4;
    const DELIVERY_TYPE_RETAIL_EXPRESS = 5;
    const DEFAULT_DELIVERY_TYPE = self::DELIVERY_TYPE_STANDARD;
    const PACKAGE_TYPE_NORMAL = 1;
    const PACKAGE_TYPE_MAILBOX_PACKAGE = 2;
    const PACKAGE_TYPE_UNSTAMPED = 3;
    const PACKAGE_TYPE_DIGITAL_STAMP = 4;
    const DEFAULT_PACKAGE_TYPE = self::PACKAGE_TYPE_NORMAL;
    public static $euCountries = array('NL', 'BE', 'AT', 'BG', 'CZ', 'CY', 'DK', 'EE', 'FI', 'FR', 'DE', 'GB', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'UK', 'XK');
    /**
     * @var array
     */
    private $consignmentEncoded = array();
    /**
     * Splitting a full NL address and save it in this object
     *
     * Required: Yes or use setStreet()
     *
     * @param $fullStreet
     *
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \InvalidArgumentException
     */
    public function setFullStreet($fullStreet)
    {
        if ($this->getCountry() === null) {
            throw new \InvalidArgumentException('First set the country code with setCountry() before running setFullStreet()');
        }
        if ($this->getCountry() == \MyParcelModule\MyParcelNL\Sdk\src\Model\MyParcelConsignment::CC_NL) {
            $streetData = $this->splitStreet($fullStreet);
            $this->setStreet($streetData['street']);
            $this->setNumber($streetData['number']);
            $this->setNumberSuffix($streetData['number_suffix']);
        } else {
            $this->setStreet($fullStreet);
        }
        return $this;
    }
    /**
     * Splits street data into separate parts for street name, house number and extension.
     * Only for Dutch addresses
     *
     * @param string $fullStreet The full street name including all parts
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    private function splitStreet($fullStreet)
    {
        $street = '';
        $number = '';
        $number_suffix = '';
        $fullStreet = trim(preg_replace('/(\\r\\n)|\\n|\\r/', ' ', $fullStreet));
        $result = preg_match(static::SPLIT_STREET_REGEX, $fullStreet, $matches);
        if (!$result || !is_array($matches)) {
            // Invalid full street supplied
            throw new \InvalidArgumentException('Invalid full street supplied: ' . $fullStreet);
        }
        if ($fullStreet != $matches[0]) {
            // Characters are gone by preg_match
            throw new \InvalidArgumentException('Something went wrong with splitting up address ' . $fullStreet);
        }
        if (isset($matches['street'])) {
            $street = $matches['street'];
        }
        if (isset($matches['number'])) {
            $number = $matches['number'];
        }
        if (isset($matches['number_suffix'])) {
            $number_suffix = trim($matches['number_suffix'], '-');
        }
        $streetData = array('street' => $street, 'number' => $number, 'number_suffix' => $number_suffix);
        return $streetData;
    }
    /**
     * Encode all the data before sending it to MyParcel
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    public function apiEncode()
    {
        $this->encodeBaseOptions()->encodeStreet()->encodeExtraOptions()->encodeCdCountry();
        return $this->consignmentEncoded;
    }
    /**
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    private function encodeCdCountry()
    {
        if ($this->isEuCountry()) {
            return $this;
        }
        $items = $this->getItems();
        if (empty($items)) {
            throw new \InvalidArgumentException('Product data must be set for international MyParcel shipments. Use addItem().');
        }
        if (!$this->getPackageType() === 1) {
            throw new \InvalidArgumentException('For international shipments, package_type must be 1 (normal package).');
        }
        $labelDescription = $this->getLabelDescription();
        if (empty($labelDescription)) {
            throw new \InvalidArgumentException('Label description/invoice id is required for international shipments. Use getLabelDescription().');
        }
        $items = array();
        foreach ($this->getItems() as $item) {
            $items[] = $this->encodeCdCountryItem($item);
        }
        $this->consignmentEncoded = array_merge_recursive($this->consignmentEncoded, array('customs_declaration' => array('contents' => 1, 'weight' => $this->getTotalWeight(), 'items' => $items, 'invoice' => $this->getLabelDescription()), 'physical_properties' => $this->getPhysicalProperties() + array('weight' => $this->getTotalWeight())));
        return $this;
    }
    /**
     * Check if the address is inside the EU
     *
     * @return bool
     */
    public function isEuCountry()
    {
        return in_array($this->getCountry(), static::$euCountries);
    }
    /**
     * Encode product for the request
     *
     * @var MyParcelCustomsItem $customsItem
     * @var string              $currency
     * @return array
     */
    private function encodeCdCountryItem($customsItem, $currency = 'EUR')
    {
        $item = array('description' => $customsItem->getDescription(), 'amount' => $customsItem->getAmount(), 'weight' => $customsItem->getWeight(), 'classification' => $customsItem->getClassification(), 'country' => $customsItem->getCountry(), 'item_value' => array('amount' => $customsItem->getItemValue(), 'currency' => $currency));
        return $item;
    }
    /**
     * The total weight for all items in whole grams
     *
     * @return int
     */
    public function getTotalWeight()
    {
        $weight = 0;
        foreach ($this->getItems() as $item) {
            $weight += $item->getWeight();
        }
        if ($weight == 0) {
            $weight = 1;
        }
        return $weight;
    }
    /**
     * @return $this
     */
    private function encodeExtraOptions()
    {
        if ($this->getCountry() == static::CC_NL || $this->getCountry() == static::CC_BE) {
            $this->consignmentEncoded = array_merge_recursive($this->consignmentEncoded, array('options' => array('only_recipient' => $this->isOnlyRecipient() ? 1 : 0, 'signature' => $this->isSignature() ? 1 : 0, 'return' => $this->isReturn() ? 1 : 0, 'delivery_type' => $this->getDeliveryType(), 'age_check' => $this->hasAgeCheck() ? 1 : 0, 'cooled_delivery' => $this->isCooledDelivery() ? 1 : 0)));
            $this->encodePickup()->encodeInsurance()->encodePhysicalProperties();
        }
        if ($this->isEuCountry()) {
            $this->consignmentEncoded['options']['large_format'] = $this->isLargeFormat() ? 1 : 0;
        }
        if ($this->getDeliveryDate()) {
            $this->consignmentEncoded['options']['delivery_date'] = $this->getDeliveryDate();
        }
        return $this;
    }
    /**
     * @return $this
     */
    private function encodePhysicalProperties()
    {
        $physicalProperties = $this->getPhysicalProperties();
        if (empty($physicalProperties) && $this->getPackageType() != static::PACKAGE_TYPE_DIGITAL_STAMP) {
            return $this;
        }
        if ($this->getPackageType() == static::PACKAGE_TYPE_DIGITAL_STAMP && !isset($physicalProperties['weight'])) {
            throw new \InvalidArgumentException('Weight in physical properties must be set for digital stamp shipments.');
        }
        $this->consignmentEncoded['physical_properties'] = $this->getPhysicalProperties();
        return $this;
    }
    /**
     * @return $this
     */
    private function encodeInsurance()
    {
        // Set insurance
        if ($this->getInsurance() > 1) {
            $this->consignmentEncoded['options']['insurance'] = array('amount' => (int) $this->getInsurance() * 100, 'currency' => 'EUR');
        }
        return $this;
    }
    private function encodePickup()
    {
        // Set pickup address
        if ($this->getPickupPostalCode() !== null && $this->getPickupStreet() !== null && $this->getPickupCity() !== null && $this->getPickupNumber() !== null && $this->getPickupLocationName() !== null) {
            $this->consignmentEncoded['pickup'] = array('postal_code' => $this->getPickupPostalCode(), 'street' => $this->getPickupStreet(), 'city' => $this->getPickupCity(), 'number' => $this->getPickupNumber(), 'location_name' => $this->getPickupLocationName(), 'location_code' => $this->getPickupLocationCode(), 'retail_network_id' => $this->getPickupNetworkId());
        }
        return $this;
    }
    /**
     * @return $this
     */
    private function encodeStreet()
    {
        if ($this->getCountry() == \MyParcelModule\MyParcelNL\Sdk\src\Model\MyParcelConsignment::CC_NL) {
            $this->consignmentEncoded = array_merge_recursive($this->consignmentEncoded, array('recipient' => array('street' => $this->getStreet(true), 'street_additional_info' => $this->getStreetAdditionalInfo(), 'number' => $this->getNumber(), 'number_suffix' => $this->getNumberSuffix())));
        } else {
            $this->consignmentEncoded['recipient']['street'] = $this->getFullStreet(true);
            $this->consignmentEncoded['recipient']['street_additional_info'] = $this->getStreetAdditionalInfo();
        }
        return $this;
    }
    /**
     * Get entire street
     *
     * @var bool
     *
     * @return string Entire street
     */
    public function getFullStreet($useStreetAdditionalInfo = false)
    {
        $fullStreet = $this->getStreet($useStreetAdditionalInfo);
        if ($this->getNumber()) {
            $fullStreet .= ' ' . $this->getNumber();
        }
        if ($this->getNumberSuffix()) {
            $fullStreet .= ' ' . $this->getNumberSuffix();
        }
        return trim($fullStreet);
    }
    /**
     * @return $this
     */
    private function encodeBaseOptions()
    {
        $packageType = $this->getPackageType();
        if ($packageType == null) {
            $packageType = static::DEFAULT_PACKAGE_TYPE;
        }
        $this->consignmentEncoded = array('recipient' => array('cc' => $this->getCountry(), 'person' => $this->getPerson(), 'postal_code' => $this->getPostalCode(), 'city' => (string) $this->getCity(), 'email' => (string) $this->getEmail(), 'phone' => (string) $this->getPhone()), 'options' => array('package_type' => $packageType, 'label_description' => $this->getLabelDescription()), 'carrier' => 1);
        if ($this->getReferenceId()) {
            $this->consignmentEncoded['reference_identifier'] = $this->getReferenceId();
        }
        if ($this->getCompany()) {
            $this->consignmentEncoded['recipient']['company'] = $this->getCompany();
        }
        return $this;
    }
    /**
     * Decode all the data after the request with the API
     *
     * @param $data
     *
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function apiDecode($data)
    {
        $this->decodeBaseOptions($data)->decodeExtraOptions($data)->decodeRecipient($data)->decodePickup($data);
        return $this;
    }
    /**
     * @param array $data
     *
     * @return $this
     */
    private function decodePickup($data)
    {
        // Set pickup
        if (key_exists('pickup', $data) && $data['pickup'] !== null) {
            $methods = array('PickupPostalCode' => 'pickup_postal_code', 'PickupStreet' => 'pickup_street', 'PickupCity' => 'pickup_city', 'PickupNumber' => 'pickup_number', 'PickupLocationName' => 'pickup_location_name', 'PickupLocationCode' => 'pickup_location_code', 'PickupNetworkId' => 'pickup_network_id');
            /** @noinspection PhpInternalEntityUsedInspection */
            $this->setByMethods($data['pickup'], $methods);
        } else {
            $fields = array('pickup_postal_code' => null, 'pickup_street' => null, 'pickup_city' => null, 'pickup_number' => null, 'pickup_location_name' => null, 'pickup_location_code' => '', 'pickup_network_id' => '');
            /** @noinspection PhpInternalEntityUsedInspection */
            $this->clearFields($fields);
        }
        return $this;
    }
    /**
     * @param array $data
     *
     * @return $this
     * @throws \InvalidArgumentException
     */
    private function decodeRecipient($data)
    {
        $recipient = $data['recipient'];
        $fields = array('company' => '', 'number' => null, 'number_suffix' => '');
        /** @noinspection PhpInternalEntityUsedInspection */
        $this->clearFields($fields);
        $methods = array('Company' => 'company', 'Number' => 'number', 'NumberSuffix' => 'number_suffix');
        /** @noinspection PhpInternalEntityUsedInspection */
        $this->setByMethods($recipient, $methods);
        return $this;
    }
    /**
     * @param array $data
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    private function decodeExtraOptions($data)
    {
        $options = $data['options'];
        $fields = array('only_recipient' => false, 'large_format' => false, 'age_check' => false, 'signature' => false, 'cooled_delivery' => false, 'return' => false, 'delivery_date' => null, 'delivery_type' => static::DEFAULT_DELIVERY_TYPE);
        /** @noinspection PhpInternalEntityUsedInspection */
        $this->clearFields($fields);
        $methods = array('OnlyRecipient' => 'only_recipient', 'LargeFormat' => 'large_format', 'AgeCheck' => 'age_check', 'Signature' => 'signature', 'CooledDelivery' => 'cooled_delivery', 'Return' => 'return', 'DeliveryDate' => 'delivery_date');
        /** @noinspection PhpInternalEntityUsedInspection */
        $this->setByMethods($options, $methods);
        if (key_exists('insurance', $options)) {
            $insuranceAmount = $options['insurance']['amount'];
            $this->setInsurance($insuranceAmount / 100);
        }
        if (isset($options['delivery_type'])) {
            $this->setDeliveryType($options['delivery_type'], false);
        }
        return $this;
    }
    /**
     * @param array $data
     *
     * @return $this
     */
    private function decodeBaseOptions($data)
    {
        $recipient = $data['recipient'];
        $options = $data['options'];
        $this->setMyParcelConsignmentId($data['id'])->setReferenceId($data['reference_identifier'])->setBarcode($data['barcode'])->setStatus($data['status'])->setCountry($recipient['cc'])->setPerson($recipient['person'])->setPostalCode($recipient['postal_code'])->setStreet($recipient['street'])->setCity($recipient['city'])->setEmail($recipient['email'])->setPhone($recipient['phone'])->setPackageType($options['package_type'])->setLabelDescription(isset($options['label_description']) ? $options['label_description'] : '');
        return $this;
    }
    /**
     * Get delivery type from checkout
     *
     * You can use this if you use the following code in your checkout: https://github.com/myparcelnl/checkout
     *
     * @param string $checkoutData
     *
     * @return int
     *
     * @throws \InvalidArgumentException
     */
    public function getDeliveryTypeFromCheckout($checkoutData)
    {
        if ($checkoutData === null) {
            return static::DELIVERY_TYPE_STANDARD;
        }
        $aCheckoutData = json_decode($checkoutData, true);
        $deliveryType = static::DELIVERY_TYPE_STANDARD;
        if (key_exists('time', $aCheckoutData) && key_exists('price_comment', $aCheckoutData['time'][0]) && $aCheckoutData['time'][0]['price_comment'] !== null) {
            switch ($aCheckoutData['time'][0]['price_comment']) {
                case 'morning':
                    $deliveryType = static::DELIVERY_TYPE_MORNING;
                    break;
                case 'standard':
                    $deliveryType = static::DELIVERY_TYPE_STANDARD;
                    break;
                case 'night':
                case 'avond':
                    $deliveryType = static::DELIVERY_TYPE_NIGHT;
                    break;
            }
        } elseif (key_exists('price_comment', $aCheckoutData) && $aCheckoutData['price_comment'] !== null) {
            switch ($aCheckoutData['price_comment']) {
                case 'retail':
                    $deliveryType = static::DELIVERY_TYPE_RETAIL;
                    break;
                case 'retailexpress':
                    $deliveryType = static::DELIVERY_TYPE_RETAIL_EXPRESS;
                    break;
            }
        }
        return $deliveryType;
    }
    /**
     * Convert delivery date from checkout
     *
     * You can use this if you use the following code in your checkout: https://github.com/myparcelnl/checkout
     *
     * @param string $checkoutData
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function setDeliveryDateFromCheckout($checkoutData)
    {
        $aCheckoutData = json_decode($checkoutData, true);
        if (!is_array($aCheckoutData) || !key_exists('date', $aCheckoutData)) {
            return $this;
        }
        if ($this->getDeliveryDate() == null) {
            $this->setDeliveryDate($aCheckoutData['date']);
        }
        return $this;
    }
    /**
     * Convert pickup data from checkout
     *
     * You can use this if you use the following code in your checkout: https://github.com/myparcelnl/checkout
     *
     * @param string $checkoutData
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function setPickupAddressFromCheckout($checkoutData)
    {
        if ($this->getCountry() !== \MyParcelModule\MyParcelNL\Sdk\src\Model\MyParcelConsignment::CC_NL && $this->getCountry() !== \MyParcelModule\MyParcelNL\Sdk\src\Model\MyParcelConsignment::CC_BE) {
            return $this;
        }
        $aCheckoutData = json_decode($checkoutData, true);
        if (!is_array($aCheckoutData) || !key_exists('location', $aCheckoutData)) {
            return $this;
        }
        if ($this->getDeliveryDate() == null) {
            $this->setDeliveryDate($aCheckoutData['date']);
        }
        if ($aCheckoutData['price_comment'] == 'retail') {
            $this->setDeliveryType(4);
        } else {
            if ($aCheckoutData['price_comment'] == 'retailexpress') {
                $this->setDeliveryType(5);
            } else {
                throw new \InvalidArgumentException('No PostNL location found in checkout data: ' . $checkoutData);
            }
        }
        $this->setPickupPostalCode($aCheckoutData['postal_code'])->setPickupStreet($aCheckoutData['street'])->setPickupCity($aCheckoutData['city'])->setPickupNumber($aCheckoutData['number'])->setPickupLocationName($aCheckoutData['location'])->setPickupLocationCode($aCheckoutData['location_code']);
        if (isset($aCheckoutData['retail_network_id'])) {
            $this->setPickupNetworkId($aCheckoutData['retail_network_id']);
        }
        return $this;
    }
    /**
     * Get ReturnShipment Object to send to MyParcel
     *
     * @return array
     */
    public function encodeReturnShipment()
    {
        $data = array('parent' => $this->getMyParcelConsignmentId(), 'carrier' => 1, 'email' => $this->getEmail(), 'name' => $this->getPerson());
        return $data;
    }
    /**
     * Check if address is correct
     * Only for Dutch addresses
     *
     * @param $fullStreet
     *
     * @return bool
     */
    public function isCorrectAddress($fullStreet)
    {
        $result = preg_match(static::SPLIT_STREET_REGEX, $fullStreet, $matches);
        if (!$result || !is_array($matches)) {
            // Invalid full street supplied
            return false;
        }
        $fullStreet = str_replace('\\n', ' ', $fullStreet);
        if ($fullStreet != $matches[0]) {
            // Characters are gone by preg_match
            return false;
        }
        return (bool) $result;
    }
    /**
     * Check if the address is outside the EU
     *
     * @return bool
     */
    public function isCdCountry()
    {
        return false == $this->isEuCountry();
    }
}
