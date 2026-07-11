<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_branch".
 *
 * @property int $branchID
 * @property string $companyCode
 * @property int $branchTypeID
 * @property string $branchCode
 * @property string $extBranchCode
 * @property string $branchName
 * @property string $address
 * @property string $phone
 * @property string $printingHeader
 * @property string $printingFooter
 * @property string $printingCheckerFooter
 * @property string $additionalTaxName
 * @property string $additionalTaxValue
 * @property int $flagOtherTaxVat
 * @property int $posModeID
 * @property int $brandID
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 */
class Branch extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_branch';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['vatName', 'vatSubject','branchTypeID', 'branchName', 'printingHeader', 'printingFooter', 'printingCheckerFooter', 'additionalTaxName', 'additionalTaxValue', 'flagOtherTaxVat', 'flagActive', 'createdBy', 'createdDate', 'flagHeaderImageOriginalSize', 'flagFooterImageOriginalSize'], 'required'],
            [['vatName', 'branchTypeID', 'posModeID', 'flagOtherTaxVat', 'flagActive', 'posTaxCalculationID', 'posOtherTaxCalculationID', 'brandID'], 'integer'],
            [['additionalTaxValue', 'vatSubject'], 'number'],
            [['branchID', 'createdDate', 'editedDate', 'image', 'imageFooter', 'flagHeaderImageOriginalSize', 'flagFooterImageOriginalSize'], 'safe'],
            [['branchCode', 'phone', 'extBranchCode'], 'string', 'max' => 20],
            [['branchName'], 'string', 'max' => 50],
            [['companyCode'], 'string', 'max' => 5],
            [['address'], 'string', 'max' => 200],
            [['printingHeader', 'printingFooter', 'printingCheckerFooter'], 'string', 'max' => 500],
            [['additionalTaxName', 'createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'branchID' => 'Branch ID',
            'branchTypeID' => 'Branch Type ID',
            'companyCode' => 'Company Code',
            'branchCode' => 'Branch Code',
            'extBranchCode' => 'External Branch Code',
            'branchName' => 'Branch Name',
            'address' => 'Address',
            'phone' => 'Phone',
            'printingHeader' => 'Printing Header',
            'printingFooter' => 'Printing Footer',
            'printingCheckerFooter' => 'Printing Checker Footer',
            'additionalTaxName' => 'Additional Tax Name',
            'additionalTaxValue' => 'Additional Tax Value',
            'flagOtherTaxVat' => 'Flag Other Tax Vat',
            'vatName' => 'Vat Name',
            'flagHeaderImageOriginalSize' => 'Flag Header Image Original Size',
            'flagFooterImageOriginalSize' => 'Flag Footer Image Original Size',
            'imageFooter' => 'Footer Image',
            'posModeID' => 'Pos Mode',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date'
        ];
    }

    public static function findActive() {
        return Branch::find()->andWhere([Branch::tableName() . '.flagActive' => 1])
                ->orderBy(Branch::tableName() . '.branchName');
    }
    
    public static function getPosTaxCalculationType($branchID) {
        return Branch::findOne(['branchID' => $branchID])->posTaxCalculationID;
    }
    
    public static function getPosOtherTaxCalculationType($branchID) {
        return Branch::findOne(['branchID' => $branchID])->posOtherTaxCalculationID;
    }

    public static function getVatName() {
        return Branch::find()->select(['vatName'])->from(Branch::tableName())->scalar();
    }

}
