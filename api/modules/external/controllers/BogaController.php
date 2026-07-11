<?php
namespace app\modules\external\controllers;

use Yii;
use yii\web\Controller;

class BogaController extends Controller {
    public function actionGetTable($branch) {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $tableData = \Yii::$app->db->createCommand("CALL spESBTable(:branchParams)")
                ->bindValue(':branchParams', $branch)
                ->queryAll();

            $transaction->commit();
            return $tableData;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->returnError($ex);
        }
    }

    public function actionGetOutstandingByTable($branch, $table, $date) {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $rows = \Yii::$app->db->createCommand("CALL spESBOutstandingTable(:salesDateParams, :tableNameParams, :branchParams)")
                ->bindValue(':salesDateParams', $date)
                ->bindValue(':tableNameParams', $table)
                ->bindValue(':branchParams', $branch)
                ->queryAll();

            $transaction->commit();
            return $rows;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->returnError($ex);
        }
    }

    public function actionGetOutstandingByOrder($salesnum) {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $rows = \Yii::$app->db->createCommand("CALL spESBOutstandingOrder(:salesNumParams)")
                ->bindValue(':salesNumParams', $salesnum)
                ->queryAll();

            $transaction->commit();
            return $rows;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->returnError($ex);
        }
    }

    public function actionGetBilledOrder($salesnum) {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $rows = \Yii::$app->db->createCommand("CALL spESBBilledOrder(:salesNumParams)")
                ->bindValue(':salesNumParams', $salesnum)
                ->queryAll();

            $transaction->commit();
            return $rows;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->returnError($ex);
        }
    }

    private function returnError($message, $code = 500) {
        throw new HttpException($code, $message);
    }

}