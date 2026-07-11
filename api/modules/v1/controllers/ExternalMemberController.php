<?php
namespace app\modules\v1\controllers;

use app\models\BrandSetting;
use app\models\forms\ExternalMember;
use app\modules\v1\Member\MemberID\Service\MemberIdService;
use yii\web\HttpException;

class ExternalMemberController extends BaseController {

    /**
     * @var MemberIdService $service
     */
    private $service;

    /**
     * @param $id
     * @param $module
     * @param MemberIdService $service
     * @param array $config
     */
    public function __construct($id, $module, MemberIdService $service, array $config = []) {
        parent::__construct($id, $module, $config);

        $this->service = $service;

    }

    /**
     * @return array
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
        ]);
        return $behaviors;
    }

    /**
     * @throws HttpException
     * @throws \Exception
     */
    public function actionView() {
        if (!$this->request->post('searchBy') || !$this->request->post('search')) {
            throw new HttpException(400);
        }

        // TODO: development member set in new module member
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        if($externalMemberSetting['Membership Type'] =='memberid'){
            return $this->service->fetchMember(
                $this->request->post()
            )->transform();
        }

        return ExternalMember::fetchMemberInfo($this->request->post('searchBy'), $this->request->post('search'));
    }

    public function actionViewVoucherMember(){
        if (!$this->request->post('memberCode') || !$this->request->post('externalMembershipTypeID')) {
            throw new HttpException(400);
        }

        return ExternalMember::fetchVoucherMemberId($this->request->post('memberCode'), $this->request->post('externalMembershipTypeID'));
    }

    /**
     * @throws HttpException
     */
    public function actionRegister() {
        if (!$this->request->post('phoneNumber')) {
            throw new HttpException(400);
        }
        return ExternalMember::registerExternalMember($this->request->post('phoneNumber'), $this->request->post('customerName'));
    }

    public function actionGetUsablePoint() {
        $payload = $this->request->post('payload');
        if (!$payload['memberCode'] || !$payload['subtotal']) {
            throw new HttpException(400);
        }
        return ExternalMember::fetchUsablePointMemberid($payload['memberCode'], $payload['subtotal']);
    }

}
