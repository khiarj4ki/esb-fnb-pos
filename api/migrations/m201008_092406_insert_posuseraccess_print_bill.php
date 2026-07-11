<?php

use app\models\PosUserAccess;
use app\models\PosUserRole;
use yii\db\Migration;

/**
 * Class m201008_092406_insert_posuseraccess_print_bill
 */
class m201008_092406_insert_posuseraccess_print_bill extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!PosUserAccess::find()->where(['filterAccessID' => 'A14'])->exists()) {
            $this->delete(PosUserAccess::tableName(), 'filterAccessID = "A14"');
        }
        
        $posUserRoleIDs = PosUserRole::find()
            ->select('posUserRoleID')
            ->column();

        if ($posUserRoleIDs) {
            foreach ($posUserRoleIDs as $posUserRoleID) {
                $posUserAccessModel = new PosUserAccess;
                $posUserAccessModel->posUserRoleID = $posUserRoleID;
                $posUserAccessModel->filterAccessID = "A14";
                $posUserAccessModel->hasAccess = 1;
                $posUserAccessModel->save();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        $this->delete(PosUserAccess::tableName(), 'filterAccessID = "A14"');
    }
}
