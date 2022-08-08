<?php
namespace Core;

use Core\Model;

class Controller
{
    protected $notice = '';
    protected $error = '';
    private $core_model;
    protected $catalog_menu = array();
    protected $settings = array();
    protected $header_pages = array();
    protected $catalog_menu_pages = array();
    protected $params = array();

    public function __construct(){
        if (isset($_SESSION['notice'])){
            $this->notice = $_SESSION['notice'];
            unset($_SESSION['notice']);
        }

        $this->error = '';
        if ((isset($_SESSION['error']))) {
            $this->error = $_SESSION['error'];
            unset($_SESSION['error']);
        }

        $this->core_model = new Model();

        $catalog_menu_records = $this->core_model->getCatalogMenu();
        if ($catalog_menu_records['error']) {
            $this->error = $catalog_menu_records['error'];
        }
        $this->catalog_menu = $catalog_menu_records['menu'];

        $this->params['auth'] = isset($_SESSION['user']);
        $this->params['admin_auth'] = isset($_SESSION['admin']);
        $this->params['admin_id'] = (isset($_SESSION['admin']['id'])) ? $_SESSION['admin']['id'] : 0;
        $this->params['cart_count'] = (isset($_SESSION['cart'])) ? count($_SESSION['cart']) : 0;

        $this->params['pvz'] = $this->core_model->getPvzItems()['pvz'];
        $this->params['promocodes'] = $this->core_model->getPromocodes()['promocodes'];
        $this->params['all_promocodes'] = $this->core_model->getAllPromocodes()['promocodes'];
        $this->params['promo'] = $_SESSION['promo'] ?? '';

        $settings_record = $this->core_model->getSettings();
        if ($settings_record['error']) {
            $this->error = $settings_record['error'];
        }
        $this->settings = $settings_record['settings'];

        $header_pages_records = $this->core_model->getHeaderPages();
        if ($header_pages_records['error']) {
            $this->error = $header_pages_records['error'];
        }
        $this->header_pages = $header_pages_records['pages'];

        $catalog_menu_pages_records = $this->core_model->getCatalogMenuPages();
        if ($catalog_menu_pages_records['error']) {
            $this->error = $catalog_menu_pages_records['error'];
        }
        $this->catalog_menu_pages = $catalog_menu_pages_records['pages'];

        $this->params['brands'] = array();
        $brands_records = $this->core_model->getBrands();
        if ($brands_records['error']) {
            $this->error = $brands_records['error'];
        }
        else {
            $brands = $brands_records['brands'];
            foreach ($brands as $key => $brand) {
                if ($brand['archived'] == 1){
                    unset($brands[$key]);
                }
            }
            $this->params['brands'] = $brands;
        }

        $this->params['show_policy'] = !isset($_COOKIE['accept_policy']);

        $this->params['request_url'] = "https://site.ru";
        $this->params['request_url'] .= ($_SERVER['REQUEST_URI'] == "/") ? "" : $_SERVER['REQUEST_URI'];

        $user = array();
        if (isset($_SESSION['user']['id'])) {
            $user_record = $this->core_model->getSiteUser($_SESSION['user']['id']);
            if ($user_record['error']) {
                $this->error = $user_record['error'];
            }
            $user = $user_record['user'];
        }
        $this->params['user'] = $user;

        $this->params['regions'] = $this->core_model->getRefRegions()['regions'];

        $this->params['order_statuses'] = $this->core_model->getRefOrderStatuses()['statuses'];

        $this->params['ref_counters'] = $this->core_model->getRefCounters()['counters'];
    }

    /**
     * Страница 404
     */
    public function getNotFoundPage(){
        View::renderTemplate("404.html", [
            'settings' => $this->settings,
            'header_pages' => $this->header_pages,
            'catalog_menu_pages' => $this->catalog_menu_pages,
            'catalog_menu' => $this->catalog_menu,
            'params' => $this->params
        ]);
    }
}