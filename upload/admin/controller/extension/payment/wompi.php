<?php
class ControllerExtensionPaymentWompi extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/payment/wompi');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_wompi', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');
            
            $this->response->redirect($this->url->link('marketplace/extension', 
                'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        // Load form data
        $fields = [
            'payment_wompi_status',
            'payment_wompi_test_mode',
            'payment_wompi_app_id',
            'payment_wompi_api_secret',
            'payment_wompi_order_status_id',
            'payment_wompi_debug',
            'payment_wompi_sort_order',
            'payment_wompi_notification_email',
            'payment_wompi_webhook_url'
        ];

        foreach ($fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } else {
                $data[$field] = $this->config->get($field);
            }
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        foreach (['app_id','api_secret','notification_email'] as $errField) {
            $errKey = 'error_'.$errField;
            $data[$errKey] = isset($this->error[$errField]) ? $this->error[$errField] : '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 
                'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/wompi', 
                'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/payment/wompi', 
            'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 
            'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/wompi', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/wompi')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (empty($this->request->post['payment_wompi_app_id'])) {
            $this->error['app_id'] = $this->language->get('error_app_id');
        }

        if (empty($this->request->post['payment_wompi_api_secret'])) {
            $this->error['api_secret'] = $this->language->get('error_api_secret');
        }

        $emailProvided = !empty($this->request->post['payment_wompi_notification_email']);
        $webhookProvided = !empty($this->request->post['payment_wompi_webhook_url']);

        if (!$emailProvided && !$webhookProvided) {
            $this->error['notification_email'] = $this->language->get('error_notification_email');
        }

        return !$this->error;
    }
}
