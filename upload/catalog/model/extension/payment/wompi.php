<?php
class ModelExtensionPaymentWompi extends Model {
    public function getMethod($address, $total) {
        $this->load->language('extension/payment/wompi');

        $method_data = array(
            'code'       => 'wompi',
            'title'      => $this->language->get('text_title'),
            'terms'      => '',
            'sort_order' => $this->config->get('payment_wompi_sort_order')
        );

        return $method_data;
    }
}
