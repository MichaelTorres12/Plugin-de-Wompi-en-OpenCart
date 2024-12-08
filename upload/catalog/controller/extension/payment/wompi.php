<?php
class ControllerExtensionPaymentWompi extends Controller {
    public function index() {
        $this->load->language('extension/payment/wompi');
        $data['action'] = $this->url->link('extension/payment/wompi/confirm', '', true);
        $data['text_description'] = $this->language->get('text_description');
        return $this->load->view('extension/payment/wompi', $data);
    }

    public function confirm() {
        if (!isset($this->session->data['order_id'])) {
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }

        $this->load->model('checkout/order');
        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);

        $api_secret   = $this->config->get('payment_wompi_api_secret');
        $app_id       = $this->config->get('payment_wompi_app_id');
        $test_mode    = $this->config->get('payment_wompi_test_mode');
        $debug        = $this->config->get('payment_wompi_debug');

        $identificador = 'ORDER-' . $order_id;
        $monto         = (float)$order_info['total'];
        if ($monto < 0.01) {
            $monto = 0.01; // mínimo requerido
        }

        $nombreProducto = 'Order #' . $order_id;

        $notification_email = $this->config->get('payment_wompi_notification_email');
        $webhook_url        = $this->config->get('payment_wompi_webhook_url');

        $configuracion = [
            "notificarTransaccionCliente" => true,
            "esMontoEditable" => false,
            "esCantidadEditable" => false,
            "cantidadPorDefecto" => 1
        ];

        if (!empty($notification_email)) {
            $configuracion["emailsNotificacion"] = $notification_email;
        }

        if (!empty($webhook_url)) {
            $configuracion["urlWebhook"] = $webhook_url;
        }

        $data = [
            "idAplicativo" => $app_id,
            "identificadorEnlaceComercio" => $identificador,
            "monto" => $monto,
            "nombreProducto" => $nombreProducto,
            "formaPago" => [
                "permitirTarjetaCreditoDebido" => true,
                "permitirPagoConPuntoAgricola" => false,
                "permitirPagoEnCuotasAgricola" => false,
                "permitirPagoEnBitcoin" => false,
                "permitePagoQuickPay" => false
            ],
            "configuracion" => $configuracion
        ];

        $response = $this->sendToWompi($data, $api_secret, $test_mode, $debug);

        if (isset($response['urlEnlace'])) {
            // Redirigir al enlace de pago
            $this->response->redirect($response['urlEnlace']);
        } else {
            $this->session->data['error'] = "No se pudo generar el enlace de pago Wompi.";
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
    }

    public function callback() {
        // Este método será invocado por Wompi vía webhook cuando se confirme el pago.
        // Ajustar según la respuesta real del webhook.
        $json = json_decode(file_get_contents('php://input'), true);

        if (isset($json['identificadorEnlaceComercio'])) {
            $parts = explode('-', $json['identificadorEnlaceComercio']);
            $order_id = end($parts);

            $this->load->model('checkout/order');

            // Revisa la doc del webhook Wompi para determinar estado.
            // Suponiendo un campo 'aprobado' en el webhook (esto puede variar)
            if (isset($json['aprobado']) && $json['aprobado'] === true) {
                $order_status_id = $this->config->get('payment_wompi_order_status_id');
                $this->model_checkout_order->addOrderHistory($order_id, $order_status_id, 'Pago aprobado por Wompi', true);
            } else {
                $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_wompi_order_status_id'), 'Pago no aprobado', false);
            }

            http_response_code(200);
            echo json_encode(['status' => 'ok']);
            exit;
        }

        http_response_code(400);
        echo json_encode(['error' => 'Bad request']);
    }

    private function sendToWompi($data, $api_secret, $test_mode, $debug) {
        // Ajustar endpoints según doc oficial. Ejemplo:
        $endpoint = "https://api.wompi.sv/EnlacePago";

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $api_secret
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $error = curl_error($ch);

        curl_close($ch);

        if ($debug) {
            $this->log->write('Wompi Request: ' . json_encode($data));
            $this->log->write('Wompi Response: ' . $response);
            if ($error) {
                $this->log->write('Wompi cURL Error: ' . $error);
            }
        }

        if ($error) {
            return ['error' => $error];
        }

        return json_decode($response, true);
    }
}