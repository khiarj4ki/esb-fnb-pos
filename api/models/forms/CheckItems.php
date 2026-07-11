<?php

namespace app\models\forms;

use app\models\Menu;
use yii\base\Model;

class CheckItems extends Model {

    public $salesMenus;
    public $soldOutItems = [];
    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesMenus'], 'required']
        ];
    }

    public function checkItems() {
        foreach ($this->salesMenus as $salesMenu) {
            $menu = Menu::find()
                    ->with('branchMenu')
                    ->where(['menuID' => $salesMenu['menuID']])
                    ->one();
            $this->checkSoldOut($menu, $salesMenu['qty']);
            foreach ($salesMenu['packages'] AS $menuPackage) {
                $menu = Menu::find()
                        ->with('branchMenu')
                        ->where(['menuID' => $menuPackage['menuID']])
                        ->one();
                $this->checkSoldOut($menu, $menuPackage['qty']);
            }
        }
        return !$this->hasErrors();
    }

    public function checkSoldOut($menu, $qty) {
		$flagSoldOut = $menu->branchMenu ? $menu->branchMenu->flagSoldOut : 0;
        if ($flagSoldOut == 1) {
            $this->addError('salesMenus', "$menu->menuName is sold out");
            $this->soldOutItems[$menu->menuName] = 0;            
        }
		
		if ($menu->branchMenu) {
			if ($menu->branchMenu->qty > 0 && $menu->branchMenu->qty < $qty) {
				$this->addError('salesMenus', "$menu->menuName is sold out ({$menu->branchMenu->qty} left)");
				$this->soldOutItems[$menu->menuName] = $menu->branchMenu->qty ;
			}
		}
    }

}
