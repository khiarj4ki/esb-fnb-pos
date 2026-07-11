<?php
namespace app\modules\v1\controllers;

use app\models\forms\ApplyBillPromo;
use app\models\forms\ApplyOrderPromo;
use app\models\forms\MapValidate;
use app\models\MenuGroup;
use app\models\PromotionDetail;
use app\models\PromotionHead;
use app\models\SalesPromotionBin;
use app\models\Menu;
use app\models\PromotionPackageSub;
use Yii;
use yii\db\Exception;
use yii\web\HttpException;
use yii\web\ServerErrorHttpException;

class PromoController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
        ]);
        return $behaviors;
    }

    public function actionIndex() {
        $menuID = $this->request->post('menuID');
        $memberID = $this->request->post('memberID');
        $employeeCode = $this->request->post('employeeCode');
        $modePromotion = $this->request->post('modePromotion');
        $employeeType = $this->request->post('employeeType');
        $menuPromotionID = $this->request->post('menuPromotionID');
        $externalMemberID = $this->request->post('externalMemberID');
        $menuPackageMenuIDs = $this->request->post('menuPackageMenuIDs');

        if ($employeeType == 'map') {
            $model = new MapValidate([
                'attributes' => $this->request->post()
            ]);

            try {
                if ($model->result !== 1) {
                    throw new Exception($model->status);
                }
            } catch (Exception $ex) {
                throw new ServerErrorHttpException($ex->getMessage());
            }
        }

        if (!$menuID) {
            return PromotionHead::findActiveForBill($memberID, $employeeCode,
                        $modePromotion, $externalMemberID)
                    ->with('promotionVisitPurpose')
                    ->with('promotionCategories')
                    ->with('promotionRequirements')
                    ->with('promotionReward')
                    ->orderBy('notes')
                    ->all();
        } else {
            return PromotionHead::findActiveForMenu($menuID, $memberID,
                        $employeeCode, $menuPromotionID, $externalMemberID, null, $menuPackageMenuIDs)
                    ->with('promotionVisitPurpose')
                    ->with('promotionCategories')
                    ->with('promotionRequirements')
                    ->with('promotionReward')
                    ->orderBy('notes')
                    ->all();
        }
    }

    public function actionApplyBill() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $data = $this->request->post();
        $applyPromoModel = new ApplyBillPromo([
            'attributes' => $data
        ]);
        try {
            if (!$applyPromoModel->save()) {
                if (isset($applyPromoModel->errorMessage)) {
                    throw new HttpException(404, $applyPromoModel->errorMessage);
                }
                throw new Exception(json_encode($applyPromoModel->errors));
            }
            if($data['promotionID'] == 0){
                SalesPromotionBin::checkData($data);
            }
            if(isset($data['promotionBin'])){
                SalesPromotionBin::saveData($data);            
                $promotionBin = SalesPromotionBin::find()
                ->where([
                    'promotionID' => $data['promotionID'],
                    'salesNum' => $data['salesNum']
                ])
                ->one();
                if($promotionBin){
                    return [
                        'promotionBin' => $promotionBin['bankIdentificationNumber']
                    ];
                }
            }
            
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            throw new HttpException(500, Yii::t('app', 'Failed to save data'));
        }
    }

    public function actionApplyOrderHead() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }
        $data = $this->request->post();
        $applyPromoModel = new ApplyOrderPromo([
            'attributes' => $data
        ]);
        $applyPromoModel->mode = ApplyOrderPromo::SCENARIO_APPLY_FROM_HEAD;
        $salesType = $this->request->post('salesType');
        try {
            if (!$applyPromoModel->save()) {
                if (isset($applyPromoModel->errorMessage)) {
                    throw new HttpException(404, $applyPromoModel->errorMessage);
                }
                throw new Exception(json_encode($applyPromoModel->errors));
            }
            
            if($data['promotionID'] == 0){
                SalesPromotionBin::checkData($data);
            }
            if(isset($data['promotionBin'])){
                SalesPromotionBin::saveData($data);

                $promotionBin = SalesPromotionBin::find()
                ->where([
                    'promotionID' => $applyPromoModel['order']['promotionID'],
                    'salesNum' => $applyPromoModel['order']['salesNum']
                ])
                ->one();
 
                $applyPromoModel->order['promotionBin'] = $promotionBin ? $promotionBin['bankIdentificationNumber'] : null;
            }
            if ($salesType && $salesType == 'POS') {
                if (isset($applyPromoModel->errorMessage)) {
                    $errorMessage = $applyPromoModel->errorMessage;
                } else {
                    $errorMessage = '';
                }

                return [
                    'message' => $errorMessage,
                    'order' => $applyPromoModel['order']
                ];
            } else {
                return $applyPromoModel['order'];
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            throw new HttpException(500, Yii::t('app', 'Failed to save data'));
        }
    }

    public function actionApplyOrderMenu() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }
        $data = $this->request->post();
        $applyPromoModel = new ApplyOrderPromo([
            'attributes' => $data
        ]);
        $applyPromoModel->order = $this->request->post()['order'];
        $applyPromoModel->mode = ApplyOrderPromo::SCENARIO_APPLY_FROM_MENU;
        $salesType = $this->request->post('salesType');
        try {
            if (!$applyPromoModel->save()) {
                if (isset($applyPromoModel->errorMessage)) {
                    throw new HttpException(404, $applyPromoModel->errorMessage);
                }
                throw new Exception(json_encode($applyPromoModel->errors));
            }
            
            if($data['promotionID'] == 0){
                SalesPromotionBin::checkData($data);
            }
            if(isset($data['promotionBin']) && (isset($applyPromoModel['order']['salesNum']) && $applyPromoModel['order']['salesNum'])){
                SalesPromotionBin::saveData($data);

                $promotionBin = SalesPromotionBin::find()
                ->where([
                    'promotionID' => $applyPromoModel['order']['promotionID'],
                    'salesNum' => $applyPromoModel['order']['salesNum']
                ])
                ->one();
 
                $applyPromoModel->order['promotionBin'] = $promotionBin ? $promotionBin['bankIdentificationNumber'] : null;
            }
            

            if ($salesType && $salesType == 'POS') {
                if (isset($applyPromoModel->errorMessage)) {
                    $errorMessage = $applyPromoModel->errorMessage;
                } else {
                    $errorMessage = '';
                }

                return [
                    'message' => $errorMessage,
                    'order' => $applyPromoModel['order']
                ];
            } else {
                return $applyPromoModel['order'];
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            throw new HttpException(500, Yii::t('app', 'Failed to save data'));
        }
    }

    public function actionGetSubsMenu() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $promotionID = $this->request->post('promotionID') ? $this->request->post('promotionID') : false;
        $salesMenuID = $this->request->post('salesMenuID');
        $fullMenu = $this->request->post('fullMenu') ? $this->request->post('fullMenu') : false;
        
        if($fullMenu){
            $menuModel = Menu::find()->where(['=','menuID',$salesMenuID])->asArray()->one();
            if($menuModel){
                return $menuModel;
            }
            else{
                return false;
            }
        }
        else if (!$promotionID) {
            return false;
        } else {
            $menuPackage = MenuGroup::find()
                ->select([
                    'ms_menupackage.menuID'
                ])
                ->joinWith('activeMenuPackages')
                ->where(['=','ms_menugroup.flagActive',true])
                ->andWhere(['=','ms_menugroup.menuID', $salesMenuID])
                ->all();

            if ($menuPackage) {  
                $detailPromoHead = PromotionPackageSub::find()
                    ->where(['=', 'promotionID', $promotionID])
                    ->andWhere(['=', 'menuID', $salesMenuID])
                    ->one();
    
                return $this->findPromoActiveForPackage($promotionID, $detailPromoHead, $menuPackage);
            } else {
                $detailPromoHead = PromotionDetail::find()
                    ->where(['=','promotionID',$promotionID])
                    ->andWhere(['IN','menuID',$salesMenuID])
                    ->one();

                return $this->findPromoActiveForPackage($promotionID, $detailPromoHead);
            }
        }
    }

    public function findPromoActiveForPackage($promotionID, $promoHeadModel = null, $promoPackageModel = null) {
        $packageMenuList = [];
        if($promoPackageModel){
            foreach($promoPackageModel as $menu){
                $packageMenuList[] = $menu->menuID;
            }
        }

        $detailPromoPackage = PromotionDetail::find()
            ->where(['=','promotionID',$promotionID])
            ->andWhere(['IN','menuID',$packageMenuList])
            ->all();

        $detailList = [];
        if($detailPromoPackage){
            foreach($detailPromoPackage as $detailPck){
                $detailList['detail'][] = [
                    'menuID' => $detailPck['menuID'],
                    'menuSubsID' => $detailPck['menuSubsID'],
                ];
            }
            $detailList['menuSubsID'] = $promoHeadModel ? $promoHeadModel['menuSubsID'] : null;
            $detailList['asPackage'] = true;
            return $detailList;
        } else {
            if (!$promoHeadModel) return false;
        }
        return $promoHeadModel['menuSubsID'];
    }

    public function actionSinglePromo() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }
        $promotionID = $this->request->post('promotionID');
        return PromotionHead::find()
            ->where(['promotionID' => $promotionID])
            ->andWhere(['flagActive' => 1])
            ->one();
    }

    public function actionGetIncomingPromotion() {
        return PromotionHead::findIncomingPromotion()->all();
    }

    public function actionTodayPromotion() {
        return PromotionHead::findActiveTodayPromotion()->all();
    }
}
