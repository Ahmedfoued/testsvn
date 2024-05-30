<?php
/**
 * WC_Etransactions_Config_Get_Options trait file.
 */
trait WC_Etransactions_Config_Get_Options {

    /**
     * Get an option value
     */
    protected function _getOption($name) {

        if (isset($this->_values[$name])) {
            return $this->_values[$name];
        }

        return $this->getDefaultOption($name);
    }

    /**
     * Retrieve the default value for a specific configuration key
     *
     * @param string $name
     * @return mixed
     */
    protected function getDefaultOption($name) {

        if (isset($this->_defaults[$name])) {
            return $this->_defaults[$name];
        }

        return null;
    }

    /**
     * Retrieve the amount
     */
    public function getAmount() {

        $value = $this->_getOption('amount');

        if ( empty($value) ) {
            $value = $this->_getOption('min_amount');
        }

        return empty($value) ? null : floatval($value);
    }

    /**
     * Retrieve the min amount
     */
    public function getMaxAmount() {
            
        $value = $this->_getOption('max_amount');

        return empty($value) ? null : floatval($value);
    }

    /**
     * Retrieve the allowed IPs
     */
    public function getAllowedIps() {

        return explode(',', $this->_getOption('ips'));
    }

    /**
     * Retrieve the defaults values
     */
    public function getDefaults() {

        return $this->_defaults;
    }

    /**
     * Retrieve the delay value
     */
    public function getDelay() {

        return (int)$this->_getOption('delay');
    }

    /**
     * Retrieve the capture order status
     */
    public function getCaptureOrderStatus() {

        return $this->_getOption('capture_order_status');
    }

    /**
     * Retrieve the description
     */
    public function getDescription() {

        return $this->_getOption('description');
    }

    /**
     * Retrieve the Hmac algo
     */
    public function getHmacAlgo() {

        return 'SHA512';
    }

    /**
     * Retrieve the Hmac key
     */
    public function getHmacKey() {

        if (isset($this->_values['hmackey']) && $this->_values['hmackey'] != $this->_defaults['hmackey']) {
            return $this->encryption->decrypt($this->_values['hmackey']);
        }

        return $this->_defaults['hmackey'];
    }

    /**
     * Retrieve the identifier
     */
    public function getIdentifier() {

        return $this->_getOption('identifier');
    }

    /**
     * Retrieve the rank
     */
    public function getRank() {

        return $this->_getOption('rank');
    }

    /**
     * Retrieve the site
     */
    public function getSite() {

        return $this->_getOption('site');
    }

    /**
     * Retrieve the subsrctiption
     */
    public function getSubscription() {

        return $this->_getOption('subscription');
    }

    /**
     * Retrieve the title
     */
    public function getTitle() {

        $fees_management = $this->_getOption('fees_management');

        if ( $fees_management == 'SOF3X' ) {
            return $this->_getOption('title_sof3x');
        }

        if ( $fees_management == 'SOF3XSF' ) {
            return $this->_getOption('title_sof3xsf');
        }

        return $this->_getOption('title');
    }

    /**
     * Retrieve the icon
     */
    public function getIcon() {

        return $this->_getOption('icon');
    }

    /**
     * Retrieve the direct production urls
     */
    public function getDirectProductionUrls() {

        return array(
            'https://ppps.e-transactions.fr/PPPS.php',
            'https://ppps1.e-transactions.fr/PPPS.php',
        );
    }

    /**
     * Retrieve the direct test urls
     */
    public function getDirectTestUrls() {

        return array(
            'https://preprod-ppps.e-transactions.fr/PPPS.php',
        );
    }

    /**
     * Retrieve the system production urls
     */
    public function getSystemProductionUrls(WC_Order $order = null) {

        return array(
            'https://tpeweb.e-transactions.fr/php/',
            'https://tpeweb1.e-transactions.fr/php/',
        );
    }

    /**
     * Retrieve the system test urls
     */
    public function getSystemTestUrls(WC_Order $order = null) {

        return array(
            'https://recette-tpeweb.e-transactions.fr/php/',
        );
    }

}