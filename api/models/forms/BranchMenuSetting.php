<?php
namespace app\models\forms;

use app\models\BranchMenu;
use app\models\BranchMenuDetail;
use app\models\Setting;
use Yii;
use yii\base\Model;
use yii\db\Exception;

/**
 * @property array $branchMenu
 */
class BranchMenuSetting extends Model {
    public $branchMenu;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['branchMenu'], 'required']
        ];
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $branchID = Setting::getCurrentBranch();

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $beforeChanges = [];
            $menuDetail = new BranchMenuDetail();
            $menuDetail->saveData();
            foreach ($this->branchMenu as $branchMenu) {
                foreach ($branchMenu['menus'] as $menu) {
                    if (isset($menu['updated'])) {
                        $branchMenuModel = BranchMenu::find()
                            ->andWhere(['branchID' => $branchID])
                            ->andWhere(['menuID' => $menu['menuID']])
                            ->one();
                        $menuID = $menu['menuID'];
                        if ($branchMenuModel) {

                            $checkerStationIDs = $branchMenuModel ? array_map('intval',
                            explode(',', $branchMenuModel->checkerStationID)) : [];
                            $checkerStations = array_filter($branchMenuModel->activeStations,
                            function($station) use ($checkerStationIDs) {
                                return in_array($station->stationID,
                                $checkerStationIDs);
                            });
                            $checkerStationName = array_values(array_map(function($v) {
                                return $v->stationName;
                            }, $checkerStations));
                            $stationIDs = $branchMenuModel ? array_map('intval',
                                    explode(',', $branchMenuModel->stationID)) : [];
                            $stations = array_filter($branchMenuModel->activeStations,
                                function($station) use ($stationIDs) {
                                    return in_array($station->stationID, $stationIDs);
                                });
                            $stationName = array_values(
                                array_map(function($v) {
                                return $v->stationName;
                            }, $stations));

                            $beforeChanges[$menuID]['menuName'] = $branchMenuModel->menu->menuName;
                            $beforeChanges[$menuID]['menuShortName'] = $branchMenuModel->menu->menuShortName;
                            $beforeChanges[$menuID]['checkerStationID'] = implode(',', $checkerStationIDs);
                            $beforeChanges[$menuID]['checkerStationName'] = implode(',', $checkerStationName);
                            $beforeChanges[$menuID]['stationID'] = implode(',', $stationIDs);
                            $beforeChanges[$menuID]['stationName'] = implode(',', $stationName);
                            $beforeChanges[$menuID]['qty'] = $branchMenuModel->qty;
                            $beforeChanges[$menuID]['flagSoldOut'] = $branchMenuModel->flagSoldOut;

                            $branchMenuModel->checkerStationID = (is_array($menu['checkerStationID']) && count($menu['checkerStationID']) > 0) ? implode(',', $menu['checkerStationID']) : 0;
                            $branchMenuModel->stationID = (is_array($menu['stationID']) && count($menu['stationID']) > 0) ? implode(',', $menu['stationID']) : 0;
                            $branchMenuModel->qty = $menu['qty'];
                            $branchMenuModel->flagSoldOut = $menu['flagSoldOut'];
                            if (!$branchMenuModel->save()) {
                                throw new Exception('Failed to update branch menu');
                            }
                        }
                    }
                }
            }
            Logging::save('-', Logging::EDIT_BRANCH_MENU, $this->getAttributes(), $beforeChanges );

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('branchMenu', $ex->getMessage());
            return false;
        }
    }

}
