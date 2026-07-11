<?php
namespace app\commands;

use app\models\forms\OnlineOrder;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use yii\console\Controller;

class OrderController extends Controller {
    public function actionIndex() {
        $server = IoServer::factory(
                new HttpServer(
                new WsServer(new OnlineOrder())
                ), 8080);
        $server->run();
    }

}
