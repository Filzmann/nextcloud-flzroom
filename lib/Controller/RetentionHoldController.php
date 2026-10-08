<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Controller;

use DomainException;
use InvalidArgumentException;
use OCA\FlzRoom\AppInfo\AppId;
use OCA\FlzRoom\Service\RetentionHoldService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

final class RetentionHoldController extends Controller {
    public function __construct(IRequest $request,private RetentionHoldService $holds){parent::__construct(AppId::VALUE,$request);}
    #[NoAdminRequired]
    public function place(string $policyId,string $recordReference,string $reasonCode,string $evidenceReference):JSONResponse{try{return new JSONResponse(['hold'=>$this->holds->place($policyId,$recordReference,$reasonCode,$evidenceReference)]);}catch(DomainException $error){return new JSONResponse(['message'=>$error->getMessage()],Http::STATUS_FORBIDDEN);}catch(InvalidArgumentException $error){return new JSONResponse(['message'=>$error->getMessage()],Http::STATUS_BAD_REQUEST);}catch(\Throwable){return new JSONResponse(['message'=>'Hold konnte nicht angelegt werden.'],Http::STATUS_INTERNAL_SERVER_ERROR);}}
    #[NoAdminRequired]
    public function release(string $policyId,string $recordReference):JSONResponse{try{$this->holds->release($policyId,$recordReference);return new JSONResponse(['released'=>true]);}catch(DomainException $error){return new JSONResponse(['message'=>$error->getMessage()],Http::STATUS_FORBIDDEN);}catch(InvalidArgumentException $error){return new JSONResponse(['message'=>$error->getMessage()],Http::STATUS_BAD_REQUEST);}catch(\Throwable){return new JSONResponse(['message'=>'Hold konnte nicht aufgehoben werden.'],Http::STATUS_INTERNAL_SERVER_ERROR);}}
}
