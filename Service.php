<?php

namespace Modules\PterodactylService\Service;

use Illuminate\Support\Facades\Http;
use Modules\PterodactylService\Exceptions\PterodactylException;
use \FOSSBilling\InjectionAwareInterface;
use Box\Di;

class Service implements InjectionAwareInterface
{
    protected $di;

    /**
     * Set the dependency injection container on the service.
     *
     * @param Di $di
     */
    public function setDi(Di $di)
    {
        $this->di = $di;
    }

    /**
     * Get the dependency injection container from the service.
     *
     * @return Di
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
     * Method to install module. Creates necessary database tables.
     *
     * @return bool
     * @throws \Box_Exception
     */
    public function install()
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS `service_pterodactyl` (
            `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
            `client_id` BIGINT(20) DEFAULT NULL,
            `order_id` BIGINT(20) DEFAULT NULL,
            `server_id` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `client_id_idx` (`client_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1 ;";
        $this->di['db']->exec($sql);

        return true;
    }

    /**
     * Method to uninstall module. Drops the created tables.
     *
     * @return bool
     * @throws \Box_Exception
     */
    public function uninstall()
    {
        $this->di['db']->exec("DROP TABLE IF EXISTS `service_pterodactyl`");
        return true;
    }

    /**
     * Create a server in Pterodactyl for the given order.
     *
     * @param \Model_ClientOrder $order
     * @return array Server creation result from Pterodactyl
     * @throws \Box_Exception|PterodactylException
     */
    public function create($order)
    {
        $config = json_decode($order->config, true);

        if (!$config) {
            throw new \Box_Exception("Invalid configuration for order {$order->id}");
        }

        $product = $this->di['db']->getExistingModelById('Product', $order->product_id, 'Product not found');

        $pterodactylConfig = $this->di['config']['pterodactyl'];
        $pterodactylUrl = rtrim($pterodactylConfig['pterodactyl_url'], '/');
        $pterodactylApiKey = $pterodactylConfig['pterodactyl_api_key'];

        $data = [
            'name' => 'Server for User ' . $order->client_id,
            'user' => $order->client_id,
            'egg' => $config['egg'] ?? $product->config['default_egg'], // Assuming product config has default settings
            'docker_image' => $config['docker_image'] ?? $product->config['default_docker_image'],
            'startup' => $config['startup'] ?? $product->config['default_startup'],
            'limits' => [
                'memory' => $config['memory'] ?? $product->config['default_memory'],
                'swap' => $config['swap'] ?? $product->config['default_swap'],
                'disk' => $config['disk'] ?? $product->config['default_disk'],
                'io' => $config['io'] ?? $product->config['default_io'],
                'cpu' => $config['cpu'] ?? $product->config['default_cpu'],
            ],
            'environment' => $config['environment'] ?? [],
            'start_on_completion' => $config['start_on_completion'] ?? false,
            'skip_scripts' => $config['skip_scripts'] ?? false,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $pterodactylApiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($pterodactylUrl . '/api/application/servers', $data);

            if ($response->failed()) {
                throw new PterodactylException($response->body());
            }

            $result = $response->json();

            // Save server details to database
            $model = $this->di['db']->dispense('service_pterodactyl');
            $model->client_id = $order->client_id;
            $model->order_id = $order->id;
            $model->server_id = $result['attributes']['identifier'];
            $this->di['db']->store($model);

            return $result;
        } catch (\Exception $e) {
            throw new \Box_Exception("Failed to create server in Pterodactyl: " . $e->getMessage());
        }
    }

       /**
     * Suspend a server in Pterodactyl for the given order.
     *
     * @param \Model_ClientOrder $order
     * @return array Server suspension result from Pterodactyl
     * @throws \Box_Exception|PterodactylException
     */
    public function suspendServer($order)
    {
        $pterodactylConfig = $this->di['config']['pterodactyl'];
        $pterodactylUrl = rtrim($pterodactylConfig['pterodactyl_url'], '/');
        $pterodactylApiKey = $pterodactylConfig['pterodactyl_api_key'];

        // Retrieve the server ID from the database
        $service = $this->di['db']->findOne('service_pterodactyl', 'order_id = ?', [$order->id]);
        if (!$service) {
            throw new \Box_Exception("No server found for order {$order->id}");
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $pterodactylApiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($pterodactylUrl . '/api/client/servers/' . $service->server_id . '/suspend');

            if ($response->failed()) {
                throw new PterodactylException($response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            throw new \Box_Exception("Failed to suspend server in Pterodactyl: " . $e->getMessage());
        }
    }

    /**
     * Unsuspend a server in Pterodactyl for the given order.
     *
     * @param \Model_ClientOrder $order
     * @return array Server unsuspension result from Pterodactyl
     * @throws \Box_Exception|PterodactylException
     */
    public function unsuspendServer($order)
    {
        $pterodactylConfig = $this->di['config']['pterodactyl'];
        $pterodactylUrl = rtrim($pterodactylConfig['pterodactyl_url'], '/');
        $pterodactylApiKey = $pterodactylConfig['pterodactyl_api_key'];

        // Retrieve the server ID from the database
        $service = $this->di['db']->findOne('service_pterodactyl', 'order_id = ?', [$order->id]);
        if (!$service) {
            throw new \Box_Exception("No server found for order {$order->id}");
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $pterodactylApiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($pterodactylUrl . '/api/client/servers/' . $service->server_id . '/unsuspend');

            if ($response->failed()) {
                throw new PterodactylException($response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            throw new \Box_Exception("Failed to unsuspend server in Pterodactyl: " . $e->getMessage());
        }
    }

    /**
     * Delete a server in Pterodactyl for the given order.
     *
     * @param \Model_ClientOrder $order
     * @return array Server deletion result from Pterodactyl
     * @throws \Box_Exception|PterodactylException
     */
    public function deleteServer($order)
    {
        $pterodactylConfig = $this->di['config']['pterodactyl'];
        $pterodactylUrl = rtrim($pterodactylConfig['pterodactyl_url'], '/');
        $pterodactylApiKey = $pterodactylConfig['pterodactyl_api_key'];

        // Retrieve the server ID from the database
        $service = $this->di['db']->findOne('service_pterodactyl', 'order_id = ?', [$order->id]);
        if (!$service) {
            throw new \Box_Exception("No server found for order {$order->id}");
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $pterodactylApiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->delete($pterodactylUrl . '/api/client/servers/' . $service->server_id);

            if ($response->failed()) {
                throw new PterodactylException($response->body());
            }

            // Delete the server record from the database after successful API call
            $this->di['db']->trash($service);

            return $response->json();
        } catch (\Exception $e) {
            throw new \Box_Exception("Failed to delete server in Pterodactyl: " . $e->getMessage());
        }
    }
}