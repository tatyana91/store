<?php
namespace App\Controllers;

use Core\Controller as CoreController;
use Core\View as CoreView;
use App\Models\Page as PageModel;

class Page extends CoreController{
    private $model;

    public function __construct(){
        parent::__construct();
        $this->model = new PageModel();
    }

    public function index($url){
        $url_params = explode('/', $url);
        $page_url = $url_params[count($url_params) - 1];
        $page = $this->model->getPageInfo($page_url);

        if ($page['error']) {
            throw new \Exception("404 Маршрут не найден", 404);
        }
        else if (!$page['page'] || $page['page']['full_url'] != $url){
            throw new \Exception("404 Маршрут не найден", 404);
        }
        else {
            if ($page_url == 'dostavka'){
                CoreView::renderTemplate('Page/delivery.html', [
                    'settings' => $this->settings,
                    'header_pages' => $this->header_pages,
                    'catalog_menu_pages' => $this->catalog_menu_pages,
                    'page' => $page['page'],
                    'catalog_menu' => $this->catalog_menu,
                    'params' => $this->params
                ]);
            }
            else if ($page_url == 'akcii'){
                $page_childs = $this->model->getPageChilds(54);
                $page_childs = $page_childs['pages'];
                foreach($page_childs as &$page_child) {
                    $get_image_url = $this->model->getSaleBanner($page_child['full_url']);
                    $page_child['path'] = $get_image_url['path'];
                }
                CoreView::renderTemplate('Page/sales.html', [
                    'settings' => $this->settings,
                    'header_pages' => $this->header_pages,
                    'catalog_menu_pages' => $this->catalog_menu_pages,
                    'page' => $page['page'],
                    'catalog_menu' => $this->catalog_menu,
                    'params' => $this->params,
                    'page_childs' => $page_childs
                ]);
            }
            else {
                $show_price_btn = ($url == 'korporativnym_klientam') ? 1 : 0;
                CoreView::renderTemplate('Page/page.html', [
                    'settings' => $this->settings,
                    'header_pages' => $this->header_pages,
                    'catalog_menu_pages' => $this->catalog_menu_pages,
                    'page' => $page['page'],
                    'catalog_menu' => $this->catalog_menu,
                    'params' => $this->params,
                    'show_price_btn' => $show_price_btn
                ]);
            }
        }
    }
}