<?php
namespace app\modules\v1\controllers;

use app\models\forms\BookingList;

class BookingListController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = 
            array_merge($behaviors['authenticator']['except'],
            [
                'get-booking-info'
            ]
        );
        return $behaviors;
    }

    // @notes: fetch all booking
    public function actionIndex() { 
        $bookingListModel = new BookingList([
            'attributes' => $this->request->post()
        ]);
        return $bookingListModel->getBookingList();
    }

    // @notes: fetch one booking
    public function actionGetBookingOne() { 
        $bookingListModel = new BookingList([
            'attributes' => $this->request->post()
        ]);
        return $bookingListModel->getBookingOne();
    }

    // @notes: update status at book
    public function actionUpdateStatus() {
        $bookingListModel = new BookingList([
            'attributes' => $this->request->post()
        ]);
        return $bookingListModel->updateStatus();
    }

    // @notes: update data at book
    public function actionUpdateData() {
        $bookingListModel = new BookingList([
            'attributes' => $this->request->post()
        ]);
        return $bookingListModel->updateData();
    }

    // @notes: fetch all booking at table
    public function actionGetBookingInfo() {
        $bookingListModel = new BookingList([
            'attributes' => $this->request->post()
        ]);
        return $bookingListModel->getBookingInfo();
    }

    // @notes: check available table on pos local
    public function actionCheckStatusTable()
    {
        $bookingListModel = new BookingList([
            'attributes' => $this->request->post()
        ]);
        return $bookingListModel->checkStatusTable();
    }

    // @notes: store data queue book
    public function actionInsertBookQueue()
    {
        $bookingListModel = new BookingList([
            'attributes' => $this->request->post()
        ]);
        return $bookingListModel->insertBookQueue();
    }

    // @notes: get sales links
    public function actionGetSalesLinks() {
        $bookingListModel = new BookingList([
            'attributes' => $this->request->post()
        ]);
        return $bookingListModel->getSalesLinks();
    }

}
