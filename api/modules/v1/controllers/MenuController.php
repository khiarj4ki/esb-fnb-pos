<?php

namespace app\modules\v1\controllers;

use app\models\forms\CheckVisitPurpose;
use app\models\MaxOrder;
use app\models\Menu;
use app\models\TentCard;

class MenuController extends BaseController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge(
            $behaviors['authenticator']['except'],
            [
                'index', 'menu-kiosk', 'get-tent-card', 'get-all-tent-card',
                'get-menu-extra', 'get-menu-for-ezo-payment', 'validate-menu',
                'get-menu-package'
            ]
        );
        return $behaviors;
    }

    public function actionIndex()
    {
        ini_set('memory_limit', '-1');
        $menuModel = NULL;
        if ($this->request->post()) {
            $menuModel = $this->request->post();
        }
        if (!empty($menuModel['salesNum'])) {
            // Check visit purpose
            CheckVisitPurpose::validateOrder($menuModel['salesNum'], $menuModel['visitPurposeID']);
        }        
        return Menu::findActiveAsArray($menuModel);
    }

    public function actionMenuKiosk()
    {
        $visitPurposeID = NULL;
        $menuKiosk = 1;
        if ($this->request->post()) {
            $visitPurposeID = $this->request->post();
        }
        return Menu::findActiveAsArray($visitPurposeID, $menuKiosk);
    }

    public function actionMenuRecommendation()
    {
        $visitPurposeID = $this->request->post('visitPurposeID') ?? null;
        return Menu::findActiveMenuRecommendation($visitPurposeID);
    }

    public function actionMaxOrder(){
        $visitPurposeID = NULL;
        if ($this->request->post()) {
            $visitPurposeID = $this->request->post();
        }
        $maxOrders = MaxOrder::getKioskMaxOrder($visitPurposeID);
        foreach ($maxOrders as $key => $mo) {
            $maxOrders[$key]['maxOrderID'] = (integer)$mo['maxOrderID'];
            $maxOrders[$key]['visitPurposeID'] = (integer)$mo['visitPurposeID'];
            $maxOrders[$key]['maxOrder'] = (integer)$mo['maxOrder'];
            $maxOrders[$key]['usedQty'] = 0;
            $maxOrders[$key]['lock'] = false;
            $arrMenuCategoryDetailID = [];
            $menuCategoryDetailIDs = explode(",", $mo['menuCategoryDetailIDs']);
            foreach($menuCategoryDetailIDs as $id){
                $arrMenuCategoryDetailID[] = (integer)$id;
            }
            $maxOrders[$key]['menuCategoryDetailIDs'] = $arrMenuCategoryDetailID;
        }
        return $maxOrders;
    }

    public function actionGetTentCard()
    {
        return TentCard::findActiveMenuTentCard();
    }

    public function actionGetAllTentCard()
    {
        return TentCard::findActiveMenuAllTentCard();
    }

    public function actionGetMenuExtra()
    {
        $menuID = NULL;
        if ($this->request->post()) {
            $visitPurposeID = $this->request->post('visitPurposeID');
            $menuID = $this->request->post('menuID');
        }
        return Menu::findExtraMenu($visitPurposeID, $menuID);
    }

    public function actionGetMenuPackage()
    {
        $menuID = NULL;
        if ($this->request->post()) {
            $visitPurposeID = $this->request->post('visitPurposeID');
            $menuID = $this->request->post('menuID');
        }
        return Menu::findMenuPackage($visitPurposeID, $menuID);
    }

    public function actionGetMenuForEzoPayment()
    {
        if (!$this->request->post('salesMenu')) {
            return [];
        }
        return Menu::findMenuForEzoPayment($this->request->post('salesMenu'));
    }

    public function actionValidateMenu()
    {
        if ($this->request->post()) {
            $visitPurposeID = $this->request->post('visitPurposeID');
            $menuID = $this->request->post('menuID');
        }

        return Menu::findMenu($visitPurposeID, $menuID);
    }

    public function actionCheckMenuAvailable()
    {
        $salesMenu = $this->request->post('salesMenu');
        return Menu::findMenuAvailableInPOS($salesMenu);        
    }
}
