<?php
/**
 * WC_Etransactions_Config_Cards trait file.
 */
trait WC_Etransactions_Config_Cards {

    /**
     * Retrieve cards for the current env & payment method
     *
     * @param string $env
     * @param string $paymentMethod
     * @param bool $forceDisplayOnly
     * @return array
     */
    public function getCards($env, $paymentMethod, $forceDisplayOnly = true) {

        global $wpdb;

        // Do not return anyt card for 3x payment method
        if ($this->isThreeTimePayment()) {
            return array();
        }

        return $wpdb->get_results($wpdb->prepare("select * from `{$wpdb->prefix}wc_etransactions_cards`
        WHERE `env` = %s
        AND `payment_method` = %s" .
        ($forceDisplayOnly ? " AND `force_display`=1 " : "") . "
        ORDER BY `position` ASC, `type_payment`, `type_card`", $env, $paymentMethod));
    }

    /**
     * Retrieve a specific card on the current env & payment method
     *
     * @param string $paymentMethod
     * @param int $cardId
     * @return array
     */
    public function getCard($paymentMethod, $cardId) {

        global $wpdb;

        return $wpdb->get_row($wpdb->prepare("select * from `{$wpdb->prefix}wc_etransactions_cards`
        WHERE `env` = %s
        AND `payment_method` = %s
        AND `id_card` = %d", ($this->isProduction() ? 'production' : 'test'), $paymentMethod, $cardId));
    }

    /**
     * Retrieve a specific card (by its type) on the current env & payment method
     *
     * @param string $paymentMethod
     * @param string $cardType
     * @return array
     */
    public function getCardByType($paymentMethod, $cardType) {

        global $wpdb;

        return $wpdb->get_row($wpdb->prepare("select * from `{$wpdb->prefix}wc_etransactions_cards`
        WHERE `env` = %s
        AND `payment_method` = %s
        AND `type_card` = %s", ($this->isProduction() ? 'production' : 'test'), $paymentMethod, $cardType));
    }

    /**
     * Get the prefered payment card associated to the current order
     *
     * @param WC_Order $order
     * @return object|null
     */
    public function getOrderCard(WC_Order $order) {

        // If a specific card type is used, check the payment UX on the card
        $cardId = (int)get_post_meta($order->get_id(), $order->get_payment_method() . '_card_id', true);
        if (empty($cardId)) {
            return null;
        }
        $card = $this->getCard($order->get_payment_method(), $cardId);
        if (empty($card)) {
            return null;
        }

        return $card;
    }

    /**
     * Get the associated tokenized card to the current order
     *
     * @param WC_Order $order
     * @return WC_Payment_Token_CC|null
     */
    public function getTokenizedCard(WC_Order $order) {

        // Check if a specific saved card type is used
        $tokenId = (int)get_post_meta($order->get_id(), $order->get_payment_method() . '_token_id', true);
        if (empty($tokenId)) {
            return null;
        }

        $token = WC_Payment_Tokens::get($tokenId);
        if (empty($token)) {
            return null;
        }

        return $token;
    }

    /**
     * Check if the current order needs 3DS exemption, depending on the order amount
     *
     * @param WC_Order $order
     * @return bool
     */
    public function orderNeeds3dsExemption(WC_Order $order) {

        if (!$this->_getOption('3ds_exemption_max_amount')) {
            return false;
        }
        
        $orderAmount = floatval($order->get_total());
        if ($orderAmount <= $this->_getOption('3ds_exemption_max_amount')) {
            return true;
        }

        return false;
    }

    /**
     * Update card information
     *
     * @param object $card
     * @param array $data
     * @return bool
     */
    public function updateCard($card, $data) {

        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'wc_etransactions_cards',
            $data,
            array(
                'id_card' => $card->id_card,
            )
        );
    }

    /**
     * Retrieve all "type_card" that are allowing tokenization
     *
     * @return array
     */
    private function getTokenizableCards() {

        return array(
            'CB',
            'VISA',
            'EUROCARD_MASTERCARD',
            'E_CARD',
            'MAESTRO',
        );
    }

    /**
     * Retrieve all cards managed by the payment gateway
     *
     * @return array
     */
    public static function getDefaultCards() {

        return array(
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'CB',
                'label' => 'Carte bancaire',
                'debit_differe' => 1,
                '3ds' => 2,
                'position' => 0,
                'force_display' => 1,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'AMEX',
                'label' => 'Carte American Express',
                'debit_differe' => 1,
                '3ds' => 2,
                'position' => 2,
            ),
            array(
                'type_payment' => 'PAYPAL',
                'type_card' => 'PAYPAL',
                'label' => 'PayPal',
                'debit_differe' => 0,
                '3ds' => 0,
                'allow_iframe' => 0,
                'position' => 3,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'JCB',
                'label' => 'JCB',
                'debit_differe' => 1,
                '3ds' => 2,
                'position' => 4,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'DINERS',
                'label' => 'Diner\'s',
                'debit_differe' => 1,
                '3ds' => 0,
                'position' => 5,
            ),
            array(
                'type_payment' => 'LIMONETIK',
                'type_card' => 'BIMPLY',
                'label' => 'Bimply',
                'debit_differe' => 0,
                '3ds' => 0,
                'allow_iframe' => 0,
                'position' => 6,
            ),
            array(
                'type_payment' => 'LIMONETIK',
                'type_card' => 'SODEXO',
                'label' => 'Pluxee',
                'debit_differe' => 0,
                '3ds' => 0,
                'allow_iframe' => 0,
                'position' => 6,
            ),
            array(
                'type_payment' => 'LIMONETIK',
                'type_card' => 'UPCHEQUDEJ',
                'label' => 'Up Chèque Déjeuner',
                'debit_differe' => 0,
                '3ds' => 0,
                'allow_iframe' => 0,
                'position' => 7,
            ),
            array(
                'type_payment' => 'LIMONETIK',
                'type_card' => 'CVCONNECT',
                'label' => 'Chèque-Vacances Connect',
                'debit_differe' => 0,
                '3ds' => 0,
                'allow_iframe' => 0,
                'position' => 8,
            ),
        );
    }

}