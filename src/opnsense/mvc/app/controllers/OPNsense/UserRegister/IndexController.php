<?php
namespace OPNsense\UserRegister;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->title = gettext("Cadastro de alunos");
        $this->view->pick('OPNsense/UserRegister/index');
    }
}