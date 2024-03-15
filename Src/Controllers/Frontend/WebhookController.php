<?php

namespace Plugin\MonduPayment\Src\Controllers\Frontend;

use JTL\DB\DbInterface;
use JTL\Shop;
use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Models\MonduInvoice;
use Plugin\MonduPayment\Src\Models\MonduOrder;
use Plugin\MonduPayment\Src\Models\Order;
use Plugin\MonduPayment\Src\Support\Http\Request;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;

class WebhookController
{
    private MonduClient $monduClient;
    private Request $request;
    private MonduInvoice $monduInvoice;
    private MonduOrder $monduOrder;
    private Order $order;

    public function __construct(private DbInterface $db)
    {
        $this->monduClient = new MonduClient();
        $this->request = new Request;
        $this->monduInvoice = new MonduInvoice();
        $this->monduOrder = new MonduOrder();
        $this->order = new Order();
    }

    /**
     * @return array
     */
    public function handleWebhook()
    {
        $requestData = $this->request->all();

        $params = [
            'order_id' => $requestData['order_id'],
            'order_number' => $requestData['order_uuid'],
            'invoice_uuid' => $requestData['invoice_uuid'],
            'order_state' => $requestData['order_state']
        ];

        switch ($requestData['topic']) {
            case 'order/confirmed':
            case 'order/canceled':
            case 'order/declined':
            case 'order/pending':
                return $this->handleOrderStateChanged($params);
            case 'invoice/created':
                return $this->handleInvoiceStateChanged($params, 'created');
            case 'invoice/canceled':
                return $this->handleInvoiceStateChanged($params, 'canceled');
            default:
                return [['error' => 'Unregistered topic'], Response::HTTP_UNPROCESSABLE_ENTITY];
        }
    }

    /**
     * @param $params
     *
     * @return array
     */
    public function handleOrderStateChanged($params)
    {
        $monduOrder = $this->getOrder($params['order_number']);

        if ($monduOrder) {
            $monduOrder->update(['state' => $params['order_state']], $params['order_id']);

            if ($params['order_state'] == 'pending') {
                $this->updateOrderWithPendingStatus($monduOrder);
            }

            return [['order' => $monduOrder], Response::HTTP_OK];
        }

        return [['error' => 'Order not found'], Response::HTTP_BAD_REQUEST];
    }

    /**
     * @param $params
     * @param $state
     *
     * @return array
     */
    public function handleInvoiceStateChanged($params, $state)
    {
        $monduInvoice = $this->getInvoice($params['invoice_uuid']);

        if ($monduInvoice) {
            $monduInvoice->update(['state' => $state], $params['invoice_uuid']);
            return [['invoice' => $monduInvoice], Response::HTTP_OK];
        }

        return [['error' => 'Invoice not found'], Response::HTTP_BAD_REQUEST];
    }

    /**
     * @param $orderUuid
     *
     * @return mixed
     */
    private function getOrder($orderUuid)
    {
        return $this->monduOrder->select('order_uuid')->where('external_reference_id', $orderUuid)->first()[0];
    }

    /**
     * @param $invoiceUuid
     *
     * @return mixed
     */
    private function getInvoice($invoiceUuid)
    {
        return $this->monduInvoice->select('invoice_uuid, order_id')->where('external_reference_id', $invoiceUuid)->first()[0];
    }

    /**
     * @param $monduOrder
     *
     * @return int
     */
    private function updateOrderWithPendingStatus($monduOrder)
    {
        return Shop::Container()->getDB()->update(
            'tbestellung',
            'cKommentar',
            'Mondu order is on pending state.',
            (object)['kBestellung' => $monduOrder['order_id']]
        );
    }
}
