<?php
namespace app\modules\v1\controllers;

use app\models\forms\UseTable;
use app\models\Table;
use Yii;
use yii\db\Exception;
use yii\web\HttpException;

class TableController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
            'index', 'get-dropdown-data'
        ]);
        return $behaviors;
    }

    public function actionIndex() {
        $token = null;
        if ($this->request->headers->get('authorization')) {
            $token = str_replace('Bearer ', '',
                $this->request->headers->get('authorization'));
        }

        $terminalCode = $this->request->post('terminalCode');
        $activatedDate = $this->request->post('activatedDate');

        return Table::findAllAsArray($token, 0, $terminalCode, $activatedDate);
    }

    public function actionTableEsbBook() {
        $token = null;
        $flagAvailableForBooking = $this->request->post('flagAvailableForBooking');
        if ($this->request->headers->get('authorization')) {
            $token = str_replace('Bearer ', '',
                $this->request->headers->get('authorization'));
        }

        return Table::findAllAsArray($token, $flagAvailableForBooking);
    }

    public function actionUse() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $useTableModel = new UseTable([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$useTableModel->save()) {
                throw new Exception(json_encode($useTableModel->errors));
            }
        } catch (Exception $ex) {
            throw new HttpException(500, $ex->getMessage());
        }
    }
    
    public function actionCheckTable() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }
        $checkTableModel = new Table([
            'attributes' => $this->request->post()
        ]);
        
        try {
            $result = $checkTableModel->checkActive($this->request->post('tableID'));
            return $result;
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }
    
    public function actionCheckTableBook() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }
        $checkTableModel = new Table([
            'attributes' => $this->request->post()
        ]);
        
        try {
            $result = $checkTableModel->checkBookActive($this->request->post('tableID'));
            return $result;
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }
    
    public function actionCheckTableLocked() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }
        $checkTableModel = new Table([
            'attributes' => $this->request->post()
        ]);
        
        try {
            $result = $checkTableModel->checkBookLocked($this->request->post('tableID'));
            return $result;
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }
    
    public function actionGetTable() {
        return Table::findActive()
                ->all();
    }
    
    public function actionGetDropdownData() {
        return Table::findDropdownTableData();
    }

    public function actionCheckTableOrder() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }
        $checkTableModel = new Table([
            'attributes' => $this->request->post()
        ]);
        
        try {
            $result = $checkTableModel->onCheckTableOrder($this->request->post('tableID'));
            return $result;
        } catch (Exception $ex) {
            $this->returnSaveError($ex);
        }
    }

}
