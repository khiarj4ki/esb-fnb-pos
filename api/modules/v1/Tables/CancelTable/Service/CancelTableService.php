<?php
namespace app\modules\V1\Tables\CancelTable\Service;

use app\modules\V1\Tables\CancelTable\Dto\CancelTableRequestDto;
use app\modules\V1\Tables\CancelTable\Service\Action\CancelTableServiceAction;

class CancelTableService implements CancelTableServiceInterface
{
    /**
     * @var CancelTableRequestDto
     */
    protected $cancelTableRequestDto;

    /**
     * @var CancelTableServiceAction
     */
    protected $cancelTableServiceAction;

    /**
     * @param CancelTableRequestDto $cancelTableRequestDto
     * @param CancelTableServiceAction $cancelTableServiceAction
     */
    public function __construct(
        CancelTableRequestDto $cancelTableRequestDto,
        CancelTableServiceAction $cancelTableServiceAction
    ) {
        $this->cancelTableRequestDto = $cancelTableRequestDto;
        $this->cancelTableServiceAction = $cancelTableServiceAction;
    }

    /**
     * Summary of cancelTable
     * @param array $input
     * @return CancelTableRequestDto
     */
    public function cancelTable(array $input): ?string
    {
        $this->cancelTableRequestDto->setAttributes($input);
        if (!$this->cancelTableRequestDto->validate()) {
            return false;
        }

        return $this->cancelTableServiceAction->handle($this->cancelTableRequestDto);
    }

}