<?php
namespace OPNsense\UserRegister\Api;

use OPNsense\Base\ApiControllerBase;

class SettingsController extends ApiControllerBase
{
    public function getAction()
    {
        // Aqui você processaria dados, faria queries, etc.
        return array(
            "status" => "success",
            "message" => "O backend recebeu sua requisição com sucesso!"
        );
    }
}