<?php

namespace app\modules\v1\controllers;

use app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Service\AddOnsMalaysiaService;

class EInvoiceController extends BaseController {
    /**
     * @var AddOnsMalaysiaService $service
     */
    private $service;

    /**
     * @param $id
     * @param $module
     * @param AddOnsMalaysiaService $service
     * @param array $config
     */
    public function __construct($id, $module, AddOnsMalaysiaService $service, array $config = []) {
        parent::__construct($id, $module, $config);

        $this->service = $service;

    }

    /**
     * @return array
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge(
            $behaviors['authenticator']['except'],
            []
        );
        return $behaviors;
    }

    /*
     * @return array
     */
    public function actionGenerate(): array
    {
        return $this->service->submitDocument(
            $this->request->post()
        )
            ->transform();

    }

    /**
     * @return array
     */
    public function actionInquiry(): array
    {
        return $this->service->inquiryDocument(
            $this->request->post()
        )
            ->transform();
    }
}
