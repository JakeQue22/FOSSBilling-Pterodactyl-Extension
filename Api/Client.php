<?php

namespace Modules\Servicepterodactyl\Api;

use \FOSSBilling\InjectionAwareInterface;
use Box\Di;
use Illuminate\Http\Request;
use Modules\PterodactylService\Service\Service as PterodactylService;

class Client extends \Api_Abstract implements InjectionAwareInterface
{
    protected $di;

    /**
     * Set the dependency injection container on the client.
     *
     * @param Di $di
     */
    public function setDi(Di $di)
    {
        $this->di = $di;
    }

    /**
     * Get the dependency injection container from the client.
     *
     * @return Di
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
     * Create a new server for the client.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        try {
            // Assuming the order ID is passed in the request
            $orderId = $request->input('order_id');
            $order = $this->di['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');

            // Ensure the client making the request owns this order
            if ($order->client_id != $request->user()->id) {
                throw new \Box_Exception('You do not have permission to perform this action on this order.', null, 403);
            }

            $pterodactylService = $this->di['mod_service']('pterodactylservice');
            $result = $pterodactylService->create($order);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Suspend a server for the client.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function suspend(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $order = $this->di['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');

            if ($order->client_id != $request->user()->id) {
                throw new \Box_Exception('You do not have permission to perform this action on this order.', null, 403);
            }

            $pterodactylService = $this->di['mod_service']('pterodactylservice');
            $result = $pterodactylService->suspendServer($order);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Unsuspend a server for the client.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unsuspend(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $order = $this->di['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');

            if ($order->client_id != $request->user()->id) {
                throw new \Box_Exception('You do not have permission to perform this action on this order.', null, 403);
            }

            $pterodactylService = $this->di['mod_service']('pterodactylservice');
            $result = $pterodactylService->unsuspendServer($order);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a server for the client.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $order = $this->di['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');

            if ($order->client_id != $request->user()->id) {
                throw new \Box_Exception('You do not have permission to perform this action on this order.', null, 403);
            }

            $pterodactylService = $this->di['mod_service']('pterodactylservice');
            $result = $pterodactylService->deleteServer($order);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}
