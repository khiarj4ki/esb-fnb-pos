<?php
namespace app\models;

use Exception;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_branchmenutransaction".
 *
 * @property int $ID
 * @property string $transactionDate
 * @property int $branchID
 * @property string $salesNum
 * @property int $menuID
 * @property string $qty
 * @property string $syncDate
 */
class BranchMenuTransaction extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_branchmenutransaction';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['transactionDate', 'branchID', 'salesNum', 'menuID', 'qty'], 'required'],
            [['branchID'], 'integer'],
            [['transactionDate', 'syncDate'], 'safe'],
            [['salesNum'], 'string', 'max' => 20],
        ];
    }

    public function getProductDetailMenu() {
        return $this->hasOne(ProductDetailMenu::class,
        ['menuID' => 'menuID']);
    }

    public function findActiveProductDetailMenu($branchID) {
        $query = BranchMenuTransaction::find()
            ->innerJoinWith('productDetailMenu')
            ->andWhere(['branchID' => $branchID])
            ->andWhere(['IS', 'syncDate', null])
            ->all();
        return $query;
    }

    public static function syncUpdate($salesNum, $syncDate) {
        $branchID = Setting::getCurrentBranch();
        $transaction = Yii::$app->db->beginTransaction();
        try {
            BranchMenuTransaction::updateAll([
                'syncDate' => $syncDate
                ],
                [
                    'AND',
                    ['branchID' => $branchID],
                    ['salesNum' => $salesNum],
                    ['IS','syncDate', null]
            ]);

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex);
            return false;
        }
    }

}
