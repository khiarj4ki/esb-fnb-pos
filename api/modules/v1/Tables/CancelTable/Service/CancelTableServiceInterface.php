<?php
namespace app\modules\V1\Tables\CancelTable\Service;

interface CancelTableServiceInterface
{

    /**
     * @param array $input
     * @return mixed
     */
    public function cancelTable(array $input);

}