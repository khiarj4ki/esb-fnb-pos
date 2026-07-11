<?php

use app\models\SpecialPriceDay;
use app\models\SpecialPriceHead;
use app\models\SpecialPriceMenu;
use app\models\SpecialPriceTime;
use yii\db\Migration;

/**
 * Class m231107_090718_add_indexing_special_price_table
 */
class m231107_090718_add_indexing_special_price_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $checkIndexSpecialPriceMenuID = "SHOW INDEX FROM " . SpecialPriceMenu::tableName() . " WHERE Key_name = 'idx_ms_specialpricemenu_menuID'";
        if (!$this->db->createCommand($checkIndexSpecialPriceMenuID)->queryScalar())
        {
            $this->createIndex('idx_ms_specialpricemenu_menuID', SpecialPriceMenu::tableName(), 'menuID');
        }

        $checkIndexSpecialPriceMenuSpecialPriceID = "SHOW INDEX FROM " . SpecialPriceMenu::tableName() . " WHERE Key_name = 'idx_ms_specialpricemenu_specialPriceID'";
        if (!$this->db->createCommand($checkIndexSpecialPriceMenuSpecialPriceID)->queryScalar())
        {
            $this->createIndex('idx_ms_specialpricemenu_specialPriceID', SpecialPriceMenu::tableName(), 'specialPriceID');
        }

        $checkIndexSpecialPriceMenuTemplate = "SHOW INDEX FROM " . SpecialPriceHead::tableName() . " WHERE Key_name = 'idx_ms_specialpricehead_menuTemplateID'";
        if (!$this->db->createCommand($checkIndexSpecialPriceMenuTemplate)->queryScalar())
        {
            $this->createIndex('idx_ms_specialpricehead_menuTemplateID', SpecialPriceHead::tableName(), 'menuTemplateID');
        }

        $checkIndexSpecialPriceTime = "SHOW INDEX FROM " . SpecialPriceTime::tableName() . " WHERE Key_name = 'idx_ms_specialpricetime_specialPriceID'";
        if (!$this->db->createCommand($checkIndexSpecialPriceTime)->queryScalar())
        {
            $this->createIndex('idx_ms_specialpricetime_specialPriceID', SpecialPriceTime::tableName(), 'specialPriceID');
        }

        $checkIndexSpecialPriceDay = "SHOW INDEX FROM " . SpecialPriceDay::tableName() . " WHERE Key_name = 'idx_ms_specialpricehead_specialPriceID'";
        if (!$this->db->createCommand($checkIndexSpecialPriceDay)->queryScalar())
        {
            $this->createIndex('idx_ms_specialpricehead_specialPriceID', SpecialPriceDay::tableName(), 'specialPriceID');
        }

    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $checkIndexSpecialPriceDay = "SHOW INDEX FROM " . SpecialPriceDay::tableName() . " WHERE Key_name = 'idx_ms_specialpricehead_specialPriceID'";
        if ($this->db->createCommand($checkIndexSpecialPriceDay)->queryScalar())
        {
            $this->dropIndex('idx_ms_specialpricehead_specialPriceID', SpecialPriceDay::tableName());
        }

        $checkIndexSpecialPriceTime = "SHOW INDEX FROM " . SpecialPriceTime::tableName() . " WHERE Key_name = 'idx_ms_specialpricetime_specialPriceID'";
        if ($this->db->createCommand($checkIndexSpecialPriceTime)->queryScalar())
        {
            $this->dropIndex('idx_ms_specialpricetime_specialPriceID', SpecialPriceTime::tableName());
        }

        $checkIndexSpecialPriceMenuTemplate = "SHOW INDEX FROM " . SpecialPriceHead::tableName() . " WHERE Key_name = 'idx_ms_specialpricehead_menuTemplateID'";
        if ($this->db->createCommand($checkIndexSpecialPriceMenuTemplate)->queryScalar())
        {
            $this->dropIndex('idx_ms_specialpricehead_menuTemplateID', SpecialPriceHead::tableName());
        }

        $checkIndexSpecialPriceMenuSpecialPriceID = "SHOW INDEX FROM " . SpecialPriceMenu::tableName() . " WHERE Key_name = 'idx_ms_specialpricemenu_specialPriceID'";
        if ($this->db->createCommand($checkIndexSpecialPriceMenuSpecialPriceID)->queryScalar())
        {
            $this->dropIndex('idx_ms_specialpricemenu_specialPriceID', SpecialPriceMenu::tableName());
        }

        $checkIndexSpecialPriceMenuID = "SHOW INDEX FROM " . SpecialPriceMenu::tableName() . " WHERE Key_name = 'idx_ms_specialpricemenu_menuID'";
        if ($this->db->createCommand($checkIndexSpecialPriceMenuID)->queryScalar())
        {
            $this->dropIndex('idx_ms_specialpricemenu_menuID', SpecialPriceMenu::tableName());
        }
    }
}
