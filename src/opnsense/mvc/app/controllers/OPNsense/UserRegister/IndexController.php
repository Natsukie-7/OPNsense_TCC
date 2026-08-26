<?php
namespace OPNsense\UserRegister;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        // Define o título da aba do navegador
        $this->view->title = gettext("User Register Panel");
        
        // Aponta para o arquivo index.volt 
        $this->view->pick('OPNsense/UserRegister/index');
    }
}