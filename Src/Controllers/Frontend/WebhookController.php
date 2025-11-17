<?php

namespace Plugin\MonduPayment\Src\Controllers\Frontend;

use JTL\Shop;
use Plugin\MonduPayment\Src\Exceptions\DatabaseQueryException;
use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Models\MonduInvoice;
use Plugin\MonduPayment\Src\Models\MonduOrder;
use Plugin\MonduPayment\Src\Support\Http\Request;

class WebhookController
{
    public const MONDU_JTL_MAPPING = [
        'confirmed' => \BESTELLUNG_STATUS_BEZAHLT,
        'pending' => \BESTELLUNG_STATUS_IN_BEARBEITUNG,
        'canceled' => \BESTELLUNG_STATUS_STORNO,
        'declined' => \BESTELLUNG_STATUS_STORNO,
    ];

    private Request $request;
    private MonduInvoice $monduInvoice;
    private MonduOrder $monduOrder;

    public function __construct()
    {
        $this->request = new Request;
        $this->monduInvoice = new MonduInvoice();
        $this->monduOrder = new MonduOrder();
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
        try {
            $requestData = $this->request->all();

            // Проверяем наличие topic
            if (!isset($requestData['topic'])) {
                return [['message' => 'Missing topic parameter', 'received_data' => $requestData], Response::HTTP_BAD_REQUEST];
            }

            switch ($requestData['topic']) {
                case 'order/confirmed':
                case 'order/declined':
                case 'order/pending':
                    return $this->handleOrderStateChanged($requestData);
                case 'invoice/canceled':
                    return $this->handleInvoiceStateChanged($requestData, 'canceled');
                default:
                    return [['message' => 'Unregistered topic: ' . $requestData['topic'], 'available_topics' => ['order/confirmed', 'order/declined', 'order/pending', 'invoice/canceled']], Response::HTTP_OK];
            }
        } catch (\Exception $e) {
            return [['message' => 'Error processing webhook', 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR];
        }
    }

    /**
     * @param $requestData
     * @return array
     * @throws DatabaseQueryException
     */
    public function handleOrderStateChanged($requestData)
    {
        $params = [
            'order_id' => $requestData['external_reference_id'],
            'order_state' => $requestData['order_state']
        ];

        $monduOrder = $this->getOrder($params['order_id']);

        if (!$monduOrder) {
            return [['message' => 'Order not found'], Response::HTTP_NOT_FOUND];
        }

        // If order found via fallback (id is null), create mondu_orders record
        if (empty($monduOrder->id)) {
            $monduOrderData = [
                'order_id' => $monduOrder->order_id,
                'external_reference_id' => $monduOrder->external_reference_id,
                'order_uuid' => $requestData['order_uuid'] ?? null,
                'state' => $params['order_state']
            ];
            
            // Use direct SQL insert
            $pdo = new \PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
                DB_USER,
                DB_PASS
            );
            
            $stmt = $pdo->prepare("
                INSERT INTO mondu_orders 
                (order_id, external_reference_id, order_uuid, state, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $monduOrderData['order_id'],
                $monduOrderData['external_reference_id'],
                $monduOrderData['order_uuid'],
                $monduOrderData['state']
            ]);
            
            $newId = $pdo->lastInsertId();
            
            // Update monduOrder with new ID
            $monduOrder->id = $newId;
            $monduOrder->order_uuid = $monduOrderData['order_uuid'];
        } else {
            // Update existing record
            $this->monduOrder->update(['state' => $params['order_state']], $monduOrder->id);
        }

        if (isset(self::MONDU_JTL_MAPPING[$params['order_state']])) {
            $this->updateOrderStatus($monduOrder, self::MONDU_JTL_MAPPING[$params['order_state']]);
        }

        if ($params['order_state'] === 'confirmed') {
            $this->unlockOrderForWawiSync($monduOrder);
        }

        return [['message' => 'ok'], Response::HTTP_OK];
    }

    /**
     * @param $requestData
     * @param $state
     * @return array
     */
    public function handleInvoiceStateChanged($requestData, $state): array
    {
        $params = [
            'invoice_uuid' => $requestData['invoice_uuid']
        ];

        $monduInvoice = $this->getInvoice($params['invoice_uuid']);

        if (!$monduInvoice) {
            return [['message' => 'Invoice not found'], Response::HTTP_NOT_FOUND];
        }

        try {
            $this->monduInvoice->update(['state' => $state], $monduInvoice->id);
        } catch (DatabaseQueryException $e) {
            return [['message' => 'Invoice not found'], Response::HTTP_NOT_FOUND];
        }

        return [['message' => 'ok'], Response::HTTP_OK];
    }

    /**
     * @param $orderUuid
     *
     * @return mixed
     */
    private function getOrder($orderUuid)
    {
        // Try to find in mondu_orders by external_reference_id
        $query = $this->monduOrder->select('id', 'order_uuid', 'order_id')->where('external_reference_id', $orderUuid);
        $result = $query->first();
        
        if (is_array($result) && isset($result[0])) {
            return $result[0];
        }
        
        // Fallback: Search in tbestellung
        $jtlOrderId = null;
        
        // Extract JTL order ID from external_reference_id (e.g. "JTL5-10005" -> 10005)
        if (preg_match('/^[A-Z0-9]+-(\d+)$/', $orderUuid, $matches)) {
            $jtlOrderId = (int)$matches[1];
        } elseif (is_numeric($orderUuid)) {
            $jtlOrderId = (int)$orderUuid;
        }
        
        // Search in tbestellung
        try {
            $pdo = new \PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            
            // Search by cBestellNr first (most reliable), then by kBestellung
            $stmt = $pdo->prepare("SELECT kBestellung FROM tbestellung WHERE cBestellNr = ? OR kBestellung = ?");
            $stmt->execute([$orderUuid, $jtlOrderId ?: 0]);
            
            $jtlOrder = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($jtlOrder) {
                // Return a compatible format (object with order_id property)
                $compatibleResult = new \stdClass();
                $compatibleResult->id = null; // No mondu_orders record yet
                $compatibleResult->order_id = $jtlOrder['kBestellung'];
                $compatibleResult->order_uuid = null;
                $compatibleResult->external_reference_id = $orderUuid;
                
                return $compatibleResult;
            }
            
        } catch (\Exception $e) {
            // Silent fail
        }
        
        return null;
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
     * @return void
     */
    private function updateOrderStatus($monduOrder, $status)
    {
        Shop::Container()->getDB()->update(
            'tbestellung',
            'kBestellung',
            $monduOrder->order_id,
            (object) ['cStatus' => $status]
        );
    }

    /**
     * @param $monduOrder
     * @return void
     */
    private function unlockOrderForWawiSync($monduOrder)
    {
        Shop::Container()->getDB()->update(
            'tbestellung',
            'kBestellung',
            $monduOrder->order_id,
            (object) ['cAbgeholt' => 'N']
        );
    }
}
