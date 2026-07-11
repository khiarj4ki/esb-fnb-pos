<?php

use app\models\SalesDepositWithdrawal;
use app\models\SalesInfo;
use app\models\SalesLink;
use app\models\SalesMenuExtra;
use app\models\SalesProcessMenu;
use app\models\SalesVoucher;
use app\models\SalesVoucherUsage;
use yii\db\Migration;

/**
 * Class m230722_083607_add_indexing_for_some_tbl_transaction
 */
class m230722_083607_add_indexing_for_some_tbl_transaction extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        // create indexing for table sales menu extra on salesnum
        $checkIndexExtra = "SHOW INDEX FROM " . SalesMenuExtra::tableName() . " WHERE Key_name = 'idx_tr_salesmenuextra_salesNum'";
        if (!$this->db->createCommand($checkIndexExtra)->queryScalar())
        {
            $this->createIndex('idx_tr_salesmenuextra_salesNum', SalesMenuExtra::tableName(), 'salesNum');
        }

        // create indexing for table sales voucher on salesnum
        $checkIndexSalesVoucher = "SHOW INDEX FROM " . SalesVoucher::tableName() . " WHERE Key_name = 'idx_tr_salesvoucher_salesNum'";
        if (!$this->db->createCommand($checkIndexSalesVoucher)->queryScalar())
        {
            $this->createIndex('idx_tr_salesvoucher_salesNum', SalesVoucher::tableName(), 'salesNum');
        }

        // create indexing for table sales voucher usage on salesnum
        $checkIndexSalesVoucherUsage = "SHOW INDEX FROM " . SalesVoucherUsage::tableName() . " WHERE Key_name = 'idx_tr_salesvoucherusage_salesNum'";
        if (!$this->db->createCommand($checkIndexSalesVoucherUsage)->queryScalar())
        {
            $this->createIndex('idx_tr_salesvoucherusage_salesNum', SalesVoucherUsage::tableName(), 'salesNum');
        }

        // create indexing for table sales deposit withdrawal on salesnum
        $checkIndexSalesDepositWithdrawal = "SHOW INDEX FROM " . SalesDepositWithdrawal::tableName() . " WHERE Key_name = 'idx_tr_salesdepositwithdrawal_salesNum'";
        if (!$this->db->createCommand($checkIndexSalesDepositWithdrawal)->queryScalar())
        {
            $this->createIndex('idx_tr_salesdepositwithdrawal_salesNum', SalesDepositWithdrawal::tableName(), 'salesNum');
        }

        // create indexing for table sales link on salesnum
        $checkIndexSalesLink = "SHOW INDEX FROM " . SalesLink::tableName() . " WHERE Key_name = 'idx_tr_saleslink_salesNum'";
        if (!$this->db->createCommand($checkIndexSalesLink)->queryScalar())
        {
            $this->createIndex('idx_tr_saleslink_salesNum', SalesLink::tableName(), 'salesNum');
        }

        // create indexing for table sales info on salesnum
        $checkIndexSalesInfo = "SHOW INDEX FROM " . SalesInfo::tableName() . " WHERE Key_name = 'idx_tr_salesinfo_salesNum'";
        if (!$this->db->createCommand($checkIndexSalesInfo)->queryScalar())
        {
            $this->createIndex('idx_tr_salesinfo_salesNum', SalesInfo::tableName(), 'salesNum');
        }

        // create indexing for table sales process menu on salesnum
        $checkIndexSalesProcessMenu = "SHOW INDEX FROM " . SalesProcessMenu::tableName() . " WHERE Key_name = 'idx_tr_salesprocessmenu_salesNum'";
        if (!$this->db->createCommand($checkIndexSalesProcessMenu)->queryScalar())
        {
            $this->createIndex('idx_tr_salesprocessmenu_salesNum', SalesProcessMenu::tableName(), 'salesNum');
        }

    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        // drop indexing for table sales menu extra on salesnum
        $checkIndexExtra = "SHOW INDEX FROM " . SalesMenuExtra::tableName() . " WHERE Key_name = 'idx_tr_salesmenuextra_salesNum'";
        if ($this->db->createCommand($checkIndexExtra)->queryScalar())
        {
            $this->dropIndex('idx_tr_salesmenuextra_salesNum', SalesMenuExtra::tableName());
        }

        // drop indexing for table sales voucher on salesnum
        $checkIndexSalesVoucher = "SHOW INDEX FROM " . SalesVoucher::tableName() . " WHERE Key_name = 'idx_tr_salesvoucher_salesNum'";
        if ($this->db->createCommand($checkIndexSalesVoucher)->queryScalar())
        {
            $this->dropIndex('idx_tr_salesvoucher_salesNum', SalesVoucher::tableName(), 'salesNum');
        }

        // drop indexing for table sales voucher usage on salesnum
        $checkIndexSalesVoucherUsage = "SHOW INDEX FROM " . SalesVoucherUsage::tableName() . " WHERE Key_name = 'idx_tr_salesvoucherusage_salesNum'";
        if ($this->db->createCommand($checkIndexSalesVoucherUsage)->queryScalar())
        {
            $this->dropIndex('idx_tr_salesvoucherusage_salesNum', SalesVoucherUsage::tableName(), 'salesNum');
        }

        // drop indexing for table sales deposit withdrawal on salesnum
        $checkIndexSalesDepositWithdrawal = "SHOW INDEX FROM " . SalesDepositWithdrawal::tableName() . " WHERE Key_name = 'idx_tr_salesdepositwithdrawal_salesNum'";
        if ($this->db->createCommand($checkIndexSalesDepositWithdrawal)->queryScalar())
        {
            $this->dropIndex('idx_tr_salesdepositwithdrawal_salesNum', SalesDepositWithdrawal::tableName(), 'salesNum');
        }

        // drop indexing for table sales link on salesnum
        $checkIndexSalesLink = "SHOW INDEX FROM " . SalesLink::tableName() . " WHERE Key_name = 'idx_tr_saleslink_salesNum'";
        if ($this->db->createCommand($checkIndexSalesLink)->queryScalar())
        {
            $this->dropIndex('idx_tr_saleslink_salesNum', SalesLink::tableName(), 'salesNum');
        }

        // drop indexing for table sales info on salesnum
        $checkIndexSalesInfo = "SHOW INDEX FROM " . SalesInfo::tableName() . " WHERE Key_name = 'idx_tr_salesinfo_salesNum'";
        if ($this->db->createCommand($checkIndexSalesInfo)->queryScalar())
        {
            $this->dropIndex('idx_tr_salesinfo_salesNum', SalesInfo::tableName(), 'salesNum');
        }

        // drop indexing for table sales process menu on salesnum
        $checkIndexSalesProcessMenu = "SHOW INDEX FROM " . SalesProcessMenu::tableName() . " WHERE Key_name = 'idx_tr_salesprocessmenu_salesNum'";
        if ($this->db->createCommand($checkIndexSalesProcessMenu)->queryScalar())
        {
            $this->dropIndex('idx_tr_salesprocessmenu_salesNum', SalesProcessMenu::tableName(), 'salesNum');
        }
    }

}
