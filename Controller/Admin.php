<?php

namespace FOSSBilling\Mod\Servicepterodactyl\Controller;

use FOSSBilling\InjectionAwareInterface;
use Pimple\Container;
use Illuminate\Http\Request;
use Modules\Servicepterodactyl\Service as PterodactylService;

class Admin implements InjectionAwareInterface
{
    protected $di;

    /**
     * Set the dependency injection container on the admin controller.
     *
     * @param Container $di
     */
    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    /**
     * Get the dependency injection container from the admin controller.
     *
     * @return Container
     */
    public function getDi(): Container
    {
        return $this->di;
    }

    /**
     * Fetch navigation data for the admin panel.
     *
     * @return array
     */
    public function fetchNavigation()
    {
        return [
            'group'  =>  [
                'index'     => 1600,                // menu sort order
                'location'  => 'pterodactyl',       // menu group identifier for subitems
                'label'     => 'Pterodactyl Module',// menu group title
                'class'     => 'pterodactyl',       // used for CSS styling menu item
            ],
            'subpages'=> [
                [
                    'location'  => 'pterodactyl',    // place this module in extensions group
                    'label'     => 'Pterodactyl Configuration',
                    'index'     => 1500,
                    'uri'       => $this->di['url']->adminLink('pterodactyl/index'),
                    'class'     => '',
                ],
            ],
        ];
    }

    /**
     * Register routes for the admin interface.
     *
     * @param \Box_App $app
     */
    public function register(\Box_App &$app)
{
    $app->get('/pterodactyl', 'get_index', [], get_class($this));
    $app->get('/pterodactyl/index', 'get_index', [], get_class($this));
    $app->get('/pterodactyl/create', 'get_create', [], get_class($this));
    $app->post('/pterodactyl/create', 'post_create', [], get_class($this));
    $app->get('/pterodactyl/manage/{id}', 'get_manage', ['id' => '[0-9]+'], get_class($this));
    $app->post('/pterodactyl/manage/{id}', 'post_manage', ['id' => '[0-9]+'], get_class($this));
}


    /**
     * Display the main index page for Pterodactyl management.
     *
     * @param \Box_App $app
     * @return mixed
     */
    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];  // Make sure the user is logged in
        return $app->render('html_admin/mod_pterodactyl_index'); // Adjusted the template path
    }

    /**
     * Display the form to create a new server.
     *
     * @param \Box_App $app
     * @return mixed
     */
    public function get_create(\Box_App $app)
    {
        $this->di['is_admin_logged'];  // Make sure the user is logged in
        return $app->render('html_admin/mod_pterodactyl_create'); // Adjusted the template path
    }

    /**
     * Handle the creation of a new server.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function post_create(Request $request)
    {
        $this->di['is_admin_logged'];  // Make sure the user is logged in
        try {
            // Simulate the order and client details for now
            $order = (object)[
                'id' => time(),         // Simulated order ID
                'client_id' => 1,       // Simulated client ID
                'product_id' => 1,      // Simulated product ID
                'config' => json_encode($request->all()) // Assume form data is server config
            ];

            $pterodactylService = $this->di['mod_service']('pterodactylservice');
            $result = $pterodactylService->create($order);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display management options for a specific server.
     *
     * @param \Box_App $app
     * @param int $id
     * @return mixed
     */
    public function get_manage(\Box_App $app, $id)
    {
        $this->di['is_admin_logged'];  // Ensure the admin is logged in
        // Fetch server details by ID from the database
        $server = $this->di['db']->findOne('service_pterodactyl', 'id = ?', [$id]);
        if (!$server) {
            throw new \Box_Exception('Server not found');
        }
        return $app->render('html_admin/mod_pterodactyl_manage', ['server' => $server]); // Adjusted the template path
    }

    /**
     * Handle server management actions like suspend, unsuspend, or delete.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function post_manage(Request $request, $id)
    {
        $this->di['is_admin_logged'];  // Ensure the admin is logged in
        $action = $request->input('action');
        $server = $this->di['db']->findOne('service_pterodactyl', 'id = ?', [$id]);
        if (!$server) {
            throw new \Box_Exception('Server not found');
        }

        $pterodactylService = $this->di['mod_service']('pterodactylservice');
        try {
            switch ($action) {
                case 'suspend':
                    $result = $pterodactylService->suspendServer((object)['id' => $server->order_id]);
                    break;
                case 'unsuspend':
                    $result = $pterodactylService->unsuspendServer((object)['id' => $server->order_id]);
                    break;
                case 'delete':
                    $result = $pterodactylService->deleteServer((object)['id' => $server->order_id]);
                    break;
                default:
                    throw new \Box_Exception('Invalid action');
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
