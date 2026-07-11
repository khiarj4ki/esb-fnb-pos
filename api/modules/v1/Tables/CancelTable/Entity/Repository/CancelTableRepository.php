<?php
namespace app\modules\V1\Tables\CancelTable\Entity\Repository;

use app\models\SalesHead;
use app\models\SalesLink;
use app\models\SalesMenu;
use app\models\SalesMenuExtra;
use app\models\Table;
use Yii;
use yii\db\Expression;
use yii\db\Query;

class CancelTableRepository
{
    /**
     * @param string $salesNum
     * @return SalesLink|null
     */
    public function getSalesLink(string $salesNum): ?SalesLink
    {
        return SalesLink::findOne(['salesNum' => $salesNum]);
    }
    /**
     * @param string $salesNum
     * @return array
     */
    public function getDataSalesLink(string $salesNum): array
    {
        return (new Query())
            ->select('sh.tableID, t.tableName, sh.salesNum')
            ->from(SalesLink::tableName() . " sl")
            ->innerJoin(['sh' => SalesHead::tableName()], ['AND', "sh.salesNum = sl.linkSalesNum", ['sl.salesNum' => $salesNum]])
            ->innerJoin(['t' => Table::tableName()], "t.tableID = sh.tableID")
            ->all();
    }
    /**
     * @param string $salesNum
     * @param string $cancelNotes
     * @return void
     */
    public function deleteSalesHead(string $salesNum, string $cancelNotes): void
    {
        // @Notes: 12 = Cancel
        SalesHead::updateAll([
            'salesDateOut' => new Expression('NOW()'),
            'additionalInfo' => $cancelNotes,
            'statusID' => 12,
            'editedBy' => Yii::$app->get('user')->id,
            'editedDate' => new Expression('NOW()'),
            'syncDate' => null
        ], ['salesNum' => $salesNum]);
    }
    /**
     * @param string $salesNum
     * @return void
     */
    public function deleteSalesMenu(string $salesNum): void
    {
        SalesMenu::updateAll(
            [
                'statusID' => 19,
                'editedBy' => Yii::$app->get('user')->id,
                'editedDate' => new Expression('NOW()')
            ],
            [
                'AND',
                ['salesNum' => $salesNum],
                ['<>', 'statusID', 19]
            ]
        );
    }
    /**
     * @param string $salesNum
     * @return void
     */
    public function deleteSalesExtra(string $salesNum): void
    {
        SalesMenuExtra::updateAll(
            ['statusID' => 19],
            ['salesNum' => $salesNum]
        );
    }
    /**
     * @param string $salesNum
     * @return SalesHead|null
     */
    public function getSalesHead(string $salesNum): ?SalesHead
    {
        return SalesHead::findOne(['salesNum' => $salesNum]);
    }
    /**
     * @param string $salesNum
     * @return SalesMenu
     */
    public function getSalesMenu(string $salesNum): array
    {
        return SalesMenu::find()
            ->where(['salesNum' => $salesNum])
            ->andWhere(['NOT IN', 'statusID', [12, 19]])
            ->all();
    }

    public function getSalesHeadMenuPackage(string $salesNum, string $localID): object
    {
        return SalesMenu::find()
            ->where(['salesNum' => $salesNum])
            ->andWhere(['localID' => $localID])
            ->one();
    }
}
