<?php

namespace Plugin\MonduPayment\Src\Controllers\Frontend;

use JTL\Shop;
use Plugin\MonduPayment\PaymentMethod\MonduPayment;
use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Middlewares\CheckWebhookSecret;
use Plugin\MonduPayment\Src\Models\MonduInvoice;
use Plugin\MonduPayment\Src\Models\MonduOrder;
use Plugin\MonduPayment\Src\Models\Order;
use Plugin\MonduPayment\Src\Services\ConfigService;
use Plugin\MonduPayment\Src\Support\Http\Request;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;

class WebhookController
{
    public const MONDU_JTL_MAPPING = [
        MonduPayment::STATE_CONFIRMED => \BESTELLUNG_STATUS_BEZAHLT,
        MonduPayment::STATE_PENDING => \BESTELLUNG_STATUS_IN_BEARBEITUNG,
        MonduPayment::STATE_CANCELED => \BESTELLUNG_STATUS_STORNO,
        MonduPayment::STATE_DECLINED => \BESTELLUNG_STATUS_STORNO,
    ];

    private MonduClient $monduClient;
    private Request $request;
    private MonduInvoice $monduInvoice;
    private MonduOrder $monduOrder;
    private Order $order;
    private CheckWebhookSecret $checkWebhookSecret;
    private ConfigService $configService;

    public function __construct()
    {
        $this->monduClient = new MonduClient();
        $this->request = new Request;
        $this->monduInvoice = new MonduInvoice();
        $this->monduOrder = new MonduOrder();
        $this->order = new Order();
        $this->checkWebhookSecret = new CheckWebhookSecret();
        $this->configService = new ConfigService();
    }

    public function index()
    {
        [$response, $status] = $this->handleWebhook();

        Response::json($response, $status);
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function handleWebhook()
    {
        $requestData = $this->request->all();

        switch ($requestData['topic']) {
            case 'order/confirmed':
            case 'order/declined':
            case 'order/pending':
                return $this->handleOrderStateChanged($requestData);
            case 'invoice/canceled':
                return $this->handleInvoiceStateChanged($requestData, 'canceled');
            default:
                return [['message' => 'Unregistered topic'], Response::HTTP_OK];
        }
    }

    /**
     * @param $params
     *
     * @return array
     */
    public function handleOrderStateChanged($requestData)
    {
        $params = [
            'order_id' => $requestData['external_reference_id'],
            'order_state' => $requestData['order_state']
        ];

        $monduOrder = $this->getOrder($params['order_id']);

        if ($monduOrder) {
            $this->monduOrder->update(['state' => $params['order_state']], $monduOrder->id);

            if (isset(self::MONDU_JTL_MAPPING[$params['order_state']])) {
                $this->updateOrderStatus($monduOrder, self::MONDU_JTL_MAPPING[$params['order_state']]);
            }

            return [['message' => 'ok'], Response::HTTP_OK];
        }

        return [['message' => 'Order not found'], Response::HTTP_NOT_FOUND];
    }

    /**
     * @param $params
     * @param $state
     *
     * @return array
     */
    public function handleInvoiceStateChanged($requestData, $state)
    {
        $params = [
            'invoice_uuid' => $requestData['invoice_uuid']
        ];

        $monduInvoice = $this->getInvoice($params['invoice_uuid']);

        if ($monduInvoice) {
            $this->monduInvoice->update(['state' => $state], $monduInvoice->id);
            return [['message' => 'ok'], Response::HTTP_OK];
        }

        return [['message' => 'Invoice not found'], Response::HTTP_NOT_FOUND];
    }

    /**
     * @param $orderUuid
     *
     * @return mixed
     */
    private function getOrder($orderUuid)
    {
        return $this->monduOrder->select('id', 'order_uuid', 'order_id')->where('external_reference_id', $orderUuid)->first()[0];
    }

    /**
     * @param $invoiceUuid
     *
     * @return mixed
     */
    private function getInvoice($invoiceUuid)
    {
        return $this->monduInvoice->select('id', 'invoice_uuid')->where('invoice_uuid', $invoiceUuid)->first()[0];
    }

    /**
     * @param $monduOrder
     * @param $status
     * @return int
     */
    private function updateOrderStatus($monduOrder, $status)
    {
        Shop::Container()->getDB()->update(
            'tbestellung',
            'kBestellung',
            $monduOrder->order_id,
            (object)['cStatus' => $status]
        );
    }
}
